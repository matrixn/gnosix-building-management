<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingDeclarationDraft;
use App\Models\BuildingRevision;
use App\Models\Organization;
use App\Models\Property;
use App\Support\BuildingDeclarationStudio;
use App\Support\CapabilityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BuildingDeclarationController extends Controller
{
    private function property(Request $r, Organization $o, Property $p, BuildingDeclarationStudio $studio, bool $write = false): void
    {
        abort_unless($p->organization_id === $o->id, 404);
        $studio->authorize($r->user('web'), $p, $write);
    }

    private function building(Request $r, Organization $o, Property $p, Building $b, BuildingDeclarationStudio $studio, bool $write = false): void
    {
        $this->property($r, $o, $p, $studio, $write);
        abort_unless($b->property_id === $p->id, 404);
    }

    public function create(Request $r, Organization $organization, Property $property, BuildingDeclarationStudio $studio)
    {
        $this->property($r, $organization, $property, $studio, true);
        $draft = $studio->resume($r->user('web'), $property);

        return redirect()->route('app.declarations.editor', [$organization, $property, $draft->id]);
    }

    public function edit(Request $r, Organization $organization, Property $property, Building $building, BuildingDeclarationStudio $studio)
    {
        $this->building($r, $organization, $property, $building, $studio, true);
        $draft = $studio->resume($r->user('web'), $property, $building);
        $focus = $r->validate(['space' => 'nullable|uuid']);

        return redirect()->route('app.declarations.editor', [$organization, $property, $draft->id, ...array_filter($focus)]);
    }

    public function editor(Request $r, Organization $organization, Property $property, string $draft, BuildingDeclarationStudio $studio)
    {
        $this->property($r, $organization, $property, $studio, true);
        $draft = BuildingDeclarationDraft::where('property_id', $property->id)->where('user_id', $r->user('web')->id)->findOrFail($draft);
        if ($draft->completed_at) {
            $b = Building::findOrFail($draft->building_id);

            return redirect()->route('app.declarations.show', [$organization, $property, $b->uuid]);
        }
        $studio->rooms($r->user('web'), $property, $draft->state);

        return view('buildings.editor', compact('organization', 'property', 'draft'));
    }

    public function save(Request $r, Organization $organization, Property $property, string $draft, BuildingDeclarationStudio $studio)
    {
        $this->property($r, $organization, $property, $studio, true);
        $data = $r->validate(['version' => 'required|integer|min:1', 'state' => 'required', 'stage' => ['sometimes', Rule::in(['identity', 'structure', 'review'])], 'source_kind' => ['sometimes', Rule::in(['manual', 'import'])]]);
        $state = $data['state'];
        if (is_string($state)) {
            if (strlen($state) > config('building-studio.max_payload_bytes')) {
                throw ValidationException::withMessages(['state' => __('building.too_large')]);
            }try {
                $state = json_decode($state, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['state' => __('building.invalid_structure')]);
            }
        }
        if (! is_array($state)) {
            throw ValidationException::withMessages(['state' => __('building.invalid_structure')]);
        }
        $draft = $studio->save($r->user('web'), $property, $draft, (int) $data['version'], $state, $r->routeIs('app.declarations.commit'), $data['source_kind'] ?? 'manual', $data['stage'] ?? 'identity');
        $redirect = $draft->completed_at ? route('app.declarations.show', [$organization, $property, Building::findOrFail($draft->building_id)->uuid]) : null;
        if ($r->expectsJson()) {
            return response()->json(['version' => $draft->version, 'redirect' => $redirect, 'state' => ($data['source_kind'] ?? 'manual') === 'import' ? $draft->state : null]);
        }

        return redirect($redirect ?? route('app.declarations.editor', [$organization, $property, $draft->id]))->with('status', __('building.saved'));
    }

    public function show(Request $r, Organization $organization, Property $property, Building $building, BuildingDeclarationStudio $studio)
    {
        $this->building($r, $organization, $property, $building, $studio);
        $state = $studio->snapshot($building);
        $rooms = app(CapabilityService::class)->allows($r->user('web'), $organization, 'hospitality.rooms');
        if (! $rooms) {
            foreach ($state['levels'] as &$level) {
                $level['spaces'] = [];
            }unset($level);
            $state['unplaced_spaces'] = [];
            $state['units'] = [];
        }
        $revisions = $building->revisions()->select(['id', 'building_id', 'actor_id', 'action', 'source_kind', 'entity_type', 'entity_reference', 'created_at'])->selectRaw('CASE WHEN before_data IS NULL THEN 0 ELSE 1 END AS has_before')->with('actor')->latest('id')->paginate(15);

        return view('buildings.show', compact('organization', 'property', 'building', 'state', 'revisions', 'rooms'));
    }

    public function export(Request $r, Organization $organization, Property $property, Building $building, BuildingDeclarationStudio $studio, ?BuildingRevision $revision = null)
    {
        $this->building($r, $organization, $property, $building, $studio);
        if ($revision) {
            abort_unless($revision->building_id === $building->id && $revision->entity_type === 'declaration', 404);
            $r->validate(['side' => ['sometimes', Rule::in(['before', 'after'])]]);
            $state = $r->query('side') === 'before' ? $revision->before_data : $revision->after_data;
            abort_unless(is_array($state) && isset($state['levels']), 404);
        } else {
            $state = $studio->snapshot($building);
        }
        $studio->rooms($r->user('web'), $property, $state);

        return response()->json(['format' => 'gnosix.building.v1', 'building_uuid' => $building->uuid, 'structure' => $state], 200, ['Content-Disposition' => 'attachment; filename="'.$building->uuid.'.json"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
