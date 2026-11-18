<?php

namespace App\TimeMachine;

use App\Models\Building;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class Sources
{
    private function url(Building $b, ?string $version, ?string $fact = null, ?int $page = null): ?string
    {
        if (! $version && ! $fact) {
            return null;
        }

        return route('app.evidence.show', [$b->property->organization, $b->property, $b->uuid, 'version' => $version, 'fact' => $fact, 'page' => $page]);
    }

    public function read(Building $b, string $valid, string $known, array $access): array
    {
        $assertions = DB::table('evidence_assertions as fact')->where('fact.building_id', $b->id)->where('fact.created_at', '<=', $known)
            ->when(! $access['structure'], fn ($q) => $q->where('fact.subject_type', '!=', 'space')->whereNull('fact.space_id'))
            ->when(! $access['assets'], fn ($q) => $q->where('fact.subject_type', '!=', 'equipment'))
            ->when(! $access['maintenance'], fn ($q) => $q->where('fact.semantic_key', 'not like', 'maintenance.%'))
            ->leftJoin('evidence_versions as version', 'version.id', '=', 'fact.version_id')
            ->leftJoin('evidence_documents as document', 'document.id', '=', 'version.document_id')
            ->addSelect('fact.*', 'version.created_at as upload_time', 'version.number as version_number', 'version.original_name as source_name')
            ->selectSub(DB::table('evidence_reviews')->select('after_data')->whereColumn('assertion_id', 'fact.id')->where('created_at', '<=', $known)->latest('id')->limit(1), 'decision')
            ->selectSub(DB::table('evidence_references')->select('page')->whereColumn('assertion_id', 'fact.id')->whereColumn('version_id', 'fact.version_id')->orderBy('id')->limit(1), 'page')
            ->selectSub($this->documentDecision($b, $known, 'document.id'), 'document_state')
            ->orderBy('fact.created_at')->orderBy('fact.id')->limit(Projection::LIMIT + 1)->get();

        $rows = [];
        $groups = [];
        foreach ($assertions->take(Projection::LIMIT) as $fact) {
            $review = Projection::json($fact->decision);
            $state = $review['status'] ?? 'proposed';
            $value = json_decode($fact->value, true);
            $effective = Projection::date($fact->effective_on);
            if ($fact->semantic_key === 'equipment.installation_date' || $fact->semantic_key === 'maintenance.performed_on') {
                $effective ??= Projection::date($value);
            }
            $key = 'assertion:'.$fact->id;
            $page = (int) $fact->page;
            $rows[$key] = Projection::row($key, 'assertion', $fact->subject_reference.' · '.__('evidence.key_'.$fact->semantic_key), $state, ['value' => $value, 'document_state' => $fact->document_state === 'document_archived' ? 'archived' : null], $fact->created_at, $fact->source_name ? $fact->source_name.' · R'.$fact->version_number.($page ? ' · p. '.$page : '') : 'Evidence #'.$fact->id, [
                'effective' => $effective,
                'reviewed' => $review['reviewed_at'] ?? null,
                'uploaded' => $fact->upload_time,
                'support' => $state === 'confirmed' ? 'human_reviewed' : ($fact->source_name ? 'document_supported' : 'declared'),
                'url' => $this->url($b, $fact->version_id, $fact->id, $page >= 1 && $page <= 300 ? $page : null),
                'future_fact' => $effective && $effective > $valid,
            ]);
            if (in_array($state, ['proposed', 'confirmed', 'disputed'], true) && $value !== null) {
                $overlap = $fact->semantic_key === 'equipment.installation_date' ? null : $effective;
                $group = json_encode([$fact->subject_type, $fact->subject_reference, $fact->semantic_key, $overlap]);
                $groups[$group][json_encode($value)][] = $key;
            }
        }
        // Immutable claims and the reviews visible at the cut-off, independent of today's conflict cache.
        foreach ($groups as $values) {
            if (count($values) > 1) {
                foreach ($values as $keys) {
                    foreach ($keys as $key) {
                        $rows[$key]['fields']['competing_values'] = true;
                    }
                }
            }
        }
        $rows = array_filter($rows, fn ($row) => ! $row['future_fact'] || ! empty($row['fields']['competing_values']));

        $dateEnd = CarbonImmutable::parse($valid, $b->property->timezone)->endOfDay()->utc()->format('Y-m-d H:i:s');
        $versions = DB::table('evidence_versions as version')->where('version.building_id', $b->id)
            ->where('version.created_at', '<=', min($dateEnd, $known))->select('version.id', 'version.document_id', 'version.original_name', 'version.number', 'version.created_at')
            ->selectSub($this->documentDecision($b, $known, 'version.document_id'), 'document_state')
            ->orderBy('version.created_at')->orderBy('version.id')->limit(Projection::LIMIT + 1)->get();
        foreach ($versions->take(Projection::LIMIT) as $version) {
            $key = 'document:'.$version->id;
            $rows[$key] = Projection::row($key, 'document', $version->original_name, $version->document_state === 'document_archived' ? 'archived' : 'recorded', ['version' => $version->number], $version->created_at, $version->original_name.' · R'.$version->number, [
                'uploaded' => $version->created_at,
                'support' => 'document_supported',
                'url' => $this->url($b, $version->id),
            ]);
        }

        return ['rows' => $rows, 'partial' => max($assertions->count(), $versions->count()) > Projection::LIMIT];
    }

    private function documentDecision(Building $b, string $known, string $documentColumn)
    {
        return DB::table('evidence_audits')->select('action')->where('building_id', $b->id)
            ->whereColumn('entity_reference', $documentColumn)->where('created_at', '<=', $known)
            ->whereIn('action', ['document_archived', 'document_restored'])->latest('id')->limit(1);
    }

    public function reports(User $user, Building $b, string $cutoff, bool $evidence, bool $manager): array
    {
        $role = $b->property->organization->memberships()->where('user_id', $user->id)->value('role');
        if ($b->state === 'archived' || (! $manager && $role === 'reporter')) {
            return ['rows' => [], 'partial' => false];
        }
        $query = DB::table('service_reports as report')->join('work_orders as work', 'work.id', '=', 'report.work_order_id')
            ->where('work.organization_id', $b->property->organization_id)->where('work.property_id', $b->property_id)
            ->where('work.state', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNull('work.building_id')->orWhereExists(fn ($building) => $building->selectRaw('1')->from('buildings')->whereColumn('buildings.id', 'work.building_id')->whereNull('buildings.deleted_at')->where('buildings.state', '!=', 'archived')))
            ->when(! $manager, fn ($q) => $q->where('work.assigned_to', $user->id))
            ->where('report.organization_id', $b->property->organization_id)->where('report.property_id', $b->property_id)
            ->where('report.snapshot->building_uuid', $b->uuid)->where('report.state', 'submitted')->where('report.created_at', '<=', $cutoff)
            ->select('report.uuid', 'report.number', 'report.created_at', 'work.reference', 'report.state')
            ->selectSub(DB::table('service_report_reviews')->select('decision')->whereColumn('service_report_id', 'report.id')->where('created_at', '<=', $cutoff)->latest('sequence')->limit(1), 'decision')
            ->selectSub(DB::table('service_report_reviews')->select('created_at')->whereColumn('service_report_id', 'report.id')->where('created_at', '<=', $cutoff)->latest('sequence')->limit(1), 'review_at');
        if ($evidence) {
            $query->leftJoin('evidence_versions as version', function ($join) use ($b) {
                $join->on('version.id', '=', 'report.version_id')->where('version.building_id', $b->id);
            })->addSelect('version.id as source_version', 'version.created_at as upload_time');
        }
        $reports = $query->orderBy('report.id')->limit(Projection::LIMIT + 1)->get();
        $rows = [];
        foreach ($reports->take(Projection::LIMIT) as $report) {
            $key = 'report:'.$report->uuid;
            $rows[$key] = Projection::row($key, 'report', $report->reference.' · R'.$report->number, $report->decision ?? $report->state, ['version' => $report->number], $report->created_at, $report->reference.' · R'.$report->number, [
                'reviewed' => $report->review_at,
                'uploaded' => $report->upload_time ?? null,
                'url' => $this->url($b, $report->source_version ?? null),
                'support' => $report->review_at ? 'human_reviewed' : 'declared',
            ]);
        }

        return ['rows' => $rows, 'partial' => $reports->count() > Projection::LIMIT];
    }
}
