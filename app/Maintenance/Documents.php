<?php

namespace App\Maintenance;

use App\Evidence\Engine;
use App\Models\AssetEvidenceLink;
use App\Models\Building;
use App\Models\Evidence\Version;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class Documents
{
    public function upload(User $u, MaintenanceRecord $m, array $files): void
    {
        Gate::forUser($u)->authorize('attach', $m);
        abort_unless($m->building, 422);
        $versions = app(Engine::class)->uploads($u, $m->building, $files, 'maintenance', null, 'maintenance.report', $m instanceof WorkOrder ? $m : null);
        DB::transaction(function () use ($u, $m, $versions) {
            Gate::forUser($u)->authorize('attach', $m);
            foreach ($versions as $v) {
                $m->attachments()->firstOrCreate(['source_uuid' => $v['version']->id], ['version_id' => $v['version']->id, 'uploaded_by' => $u->id, 'created_at' => now()]);
            }app(Workflow::class)->audit($m, $u, 'attachment');
        });
    }

    public function link(User $u, MaintenanceRecord $m, string $id): void
    {
        DB::transaction(function () use ($u, $m, $id) {
            Gate::forUser($u)->authorize('update', $m);
            $v = Version::where('building_id', $m->building_id)->findOrFail($id);
            app(Engine::class)->authorize($u, Building::findOrFail($v->building_id));
            $m->attachments()->firstOrCreate(['source_uuid' => $id], ['version_id' => $id, 'uploaded_by' => $u->id, 'created_at' => now()]);
            app(Workflow::class)->audit($m, $u, 'document_link');
        });
    }

    public function propose(User $u, WorkOrder $m, array $input): void
    {
        Gate::forUser($u)->authorize('update', $m);
        app(Workflow::class)->allowed($u, $m->property, true);
        abort_unless($m->asset && $m->building && in_array($m->state, ['resolved', 'verified', 'closed']), 422);
        $d = Validator::make($input, ['semantic_key' => 'required|in:equipment.serial_number,equipment.installation_date,maintenance.performed_on', 'value' => 'required|string|max:160', 'version_id' => 'required|uuid', 'quote' => 'required|string|max:4000', 'page' => 'nullable|integer|min:1|max:300'])->validate();
        DB::transaction(function () use ($u, $m, $d) {
            $v = $m->attachments()->where('version_id', $d['version_id'])->firstOrFail()->version;
            abort_unless($v, 404);
            $a = app(Engine::class)->propose($u, $m->building, ['subject_type' => 'equipment', 'subject_reference' => $m->asset->external_reference ?: $m->asset->reference, ...$d], $v);
            AssetEvidenceLink::firstOrCreate(['asset_id' => $m->asset_id, 'source_uuid' => $a->id], ['assertion_id' => $a->id, 'linked_by' => $u->id, 'created_at' => now()]);
            app(Workflow::class)->audit($m, $u, 'candidate', null, $a->id);
        });
    }
}
