<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Organization;
use App\Models\Property;
use App\Support\CapabilityService;
use App\Support\PropertyPermissions;
use App\TimeMachine\Projection;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TimeMachineController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        $user = $request->user('web');
        Gate::forUser($user)->authorize('view', $organization);
        abort_unless(app(CapabilityService::class)->allows($user, $organization, 'time.view'), 403);
        $properties = app(PropertyPermissions::class)->visible($user, $organization);
        $buildings = Building::withTrashed()->whereIn('property_id', $properties->select('id'))->with('property')->orderBy('name')->orderBy('id')->simplePaginate(24);

        return response()->view('time-machine.index', compact('organization', 'buildings'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Organization $organization, Property $property, Building $building, Projection $projection)
    {
        return $this->render($request, $organization, $property, $building, $projection, false);
    }

    public function export(Request $request, Organization $organization, Property $property, Building $building, Projection $projection)
    {
        return $this->render($request, $organization, $property, $building, $projection, true);
    }

    private function render(Request $request, Organization $organization, Property $property, Building $building, Projection $projection, bool $pdf)
    {
        abort_unless($property->organization_id === $organization->id && $building->property_id === $property->id, 404);
        $user = $request->user('web');
        Gate::forUser($user)->authorize('view', $property);
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $today = $now->setTimezone($property->timezone)->toDateString();
        $input = $request->validate([
            'at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:'.$today],
            'compare' => [$pdf ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:'.$today],
            'mode' => ['nullable', Rule::in(['documented', 'then'])],
            'known' => ['nullable', 'date_format:Y-m-d\TH:i:s', 'before_or_equal:'.$now->format('Y-m-d\TH:i:s')],
            'section' => ['nullable', Rule::in(['all', 'structure', 'equipment', 'relations', 'maintenance', 'evidence'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'history_cursor' => ['nullable', 'integer', 'min:1'],
        ]);
        $at = $input['at'] ?? $today;
        $compare = $input['compare'] ?? null;
        $mode = $input['mode'] ?? 'documented';
        $section = $input['section'] ?? 'all';
        $known = isset($input['known']) ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s', $input['known'], 'UTC') : $now;
        $knowledgeAt = fn ($day) => $mode === 'then' ? min($known, CarbonImmutable::parse($day, $property->timezone)->endOfDay()->utc()->startOfSecond()) : $known;

        // Use one consistent database snapshot for every adapter and both sides of a comparison.
        if (DB::getDriverName() === 'mysql' && DB::transactionLevel() === 0) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
        [$state, $other, $history, $hasOlder] = DB::transaction(function () use ($projection, $user, $organization, $property, $building, $at, $compare, $knowledgeAt, $input) {
            $building = Building::withTrashed()->where('property_id', $property->id)->findOrFail($building->id);
            $currentProperty = Property::where('organization_id', $organization->id)->findOrFail($property->id);
            Gate::forUser($user)->authorize('view', $currentProperty);
            abort_unless(app(CapabilityService::class)->allows($user, $organization, 'time.view'), 403);
            $building->setRelation('property', $currentProperty);
            $state = $projection->reconstruct($user, $building, $at, $knowledgeAt($at));
            $other = $compare ? $projection->reconstruct($user, $building, $compare, $knowledgeAt($compare)) : null;
            $events = $building->revisions()->where('created_at', '<=', $state['observed'])
                ->when(! $state['access']['structure'], fn ($q) => $q->whereIn('entity_type', ['building', 'declaration']))
                ->when(isset($input['history_cursor']), fn ($q) => $q->where('id', '<', $input['history_cursor']))
                ->latest('id')->limit(13)->get(['id', 'created_at', 'action', 'entity_type']);

            return [$state, $other, $events->take(12), $events->count() > 12];
        });
        $entries = collect($other ? $projection->compare($state, $other) : $state['rows']);
        $pdfParts = max(1, (int) ceil($entries->count() / 80));
        $kinds = [
            'structure' => ['building', 'floor', 'space', 'unit'],
            'equipment' => ['asset', 'system', 'installation'],
            'relations' => ['relation'],
            'maintenance' => ['work', 'issue', 'intervention', 'report'],
            'evidence' => ['document', 'assertion'],
        ];
        if ($section !== 'all' && ! $pdf) {
            $entries = $entries->whereIn('kind', $kinds[$section]);
        }
        $query = ['at' => $at, 'compare' => $compare, 'known' => $known->format('Y-m-d\TH:i:s'), 'mode' => $mode, 'section' => $section];
        $size = $pdf ? 80 : 24;
        $page = (int) ($input['page'] ?? 1);
        $results = new LengthAwarePaginator($entries->forPage($page, $size)->values(), $entries->count(), $size, $page, ['path' => $request->url(), 'query' => $query]);
        $latest = $other ?? $state;
        $markers = collect($latest['rows'])->whereIn('kind', ['building', 'asset', 'system', 'installation', 'relation', 'intervention', 'report', 'assertion', 'document'])
            ->sortByDesc('recorded')->take(12);
        $data = compact('organization', 'property', 'building', 'today', 'query', 'state', 'other', 'history', 'hasOlder', 'results', 'markers', 'pdfParts');
        if (! $pdf) {
            return response()->view('time-machine.show', $data)->header('Cache-Control', 'private, no-store');
        }

        $data['generated'] = $now->toIso8601String();
        $cache = storage_path('app/private/time-machine-renderer');
        if (! is_dir($cache) && ! @mkdir($cache, 0700, true) && ! is_dir($cache)) {
            abort(500);
        }
        $document = new Dompdf(new Options([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
            'fontCache' => $cache,
            'tempDir' => $cache,
            'chroot' => [$cache, base_path('vendor/dompdf/dompdf/lib/fonts')],
        ]));
        $document->loadHtml(view('time-machine.pdf', $data)->render(), 'UTF-8');
        $document->setPaper('A4');
        $document->render();
        $document->getCanvas()->page_text(500, 815, '{PAGE_NUM} / {PAGE_COUNT}', null, 8);

        return response($document->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="GNOSIX-Time-Machine-'.$at.'-'.$compare.'-part-'.$page.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
