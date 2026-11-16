<?php

namespace App\Field;

use App\Evidence\Engine;
use App\Maintenance\Workflow;
use App\Models\AssetDocumentLink;
use App\Models\AssetEvidenceLink;
use App\Models\Evidence\Version;
use App\Models\Property;
use App\Models\ServiceReport;
use App\Models\ServiceReportReview;
use App\Models\User;
use App\Models\WorkOrder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Reports
{
    public function pdf(array $snapshot, ?array $verification = null): string
    {
        $cache = storage_path('app/private/service-renderer');
        if (! is_dir($cache)) {
            mkdir($cache, 0700, true);
        }
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'fontCache' => $cache, 'tempDir' => $cache, 'chroot' => [base_path('vendor/dompdf/dompdf/lib/fonts'), $cache]]);
        $pdf = new Dompdf($options);
        $previousLocale = app()->getLocale();
        try {
            app()->setLocale(in_array($snapshot['locale'] ?? null, ['ro', 'en']) ? $snapshot['locale'] : $previousLocale);
            $html = view('field.pdf', ['s' => $snapshot, 'verification' => $verification])->render();
            $footer = __('field.independent');
        } finally {
            app()->setLocale($previousLocale);
        }
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pdf->getCanvas()->page_text(52, 812, 'GNOSIX · '.$snapshot['reference'].' · R'.$snapshot['number'].' · '.$footer, $font, 6.5, [0.35, 0.40, 0.45]);
        $pdf->getCanvas()->page_text(520, 812, '{PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.35, 0.40, 0.45]);

        return $pdf->output();
    }

    private function storePdf(User $u, WorkOrder $w, array $snapshot, ?array $verification = null, ?string $document = null)
    {
        $path = tempnam(sys_get_temp_dir(), 'gnosix-service-');
        try {
            file_put_contents($path, $this->pdf($snapshot, $verification));
            $upload = new UploadedFile($path, 'GNOSIX-'.$w->reference.'-R'.$snapshot['number'].($verification ? '-verified' : '').'.pdf', 'application/pdf', null, true);

            return app(Engine::class)->uploads($u, $w->building, [$upload], 'maintenance', $document, 'maintenance.report', $w)[0]['version'];
        } finally {
            @unlink($path);
        }
    }

    public function create(User $u, WorkOrder $w, array $input): ServiceReport
    {
        // Document/file changes are tracked for cleanup if the encompassing report transaction rolls back.
        $newPaths = [];
        try {
            return DB::transaction(function () use ($u, $w, $input, &$newPaths) {
                $d = Validator::make($input, ['revision' => 'required|integer|min:1', 'submit' => 'required|boolean', 'ack' => ! empty($input['submit']) ? 'required|accepted' : 'nullable|boolean', 'observed_serial' => 'nullable|string|max:160', 'observed_installation' => 'nullable|date_format:Y-m-d'])->validate();
                $w = app(Workflow::class)->lock($w, (int) $d['revision']);
                $ctx = app(Access::class)->authorize($u, $w, true);
                $latest = $w->serviceReports()->latest('number')->first();
                if ($latest && in_array($latest->status(), ['submitted', 'reviewed'])) {
                    throw ValidationException::withMessages(['report' => __('field.review_pending')]);
                }
                app(Workflow::class)->perform($u, $w, $input);
                $w->refresh();
                if ($d['submit']) {
                    abort_unless($w->building, 422, __('field.building_required'));
                    if ($w->require_photo && ! $w->attachments()->when($ctx['external'], fn ($q) => $q->whereIn('version_id', $ctx['grant']->documents()->whereNotNull('version_id')->select('version_id')))->whereHas('version', fn ($q) => $q->whereIn('mime', ['image/jpeg', 'image/png']))->exists()) {
                        throw ValidationException::withMessages(['photos' => __('field.photo_required')]);
                    }
                    app(Workflow::class)->transition($u, $w, ['revision' => $w->revision, 'state' => 'resolved', 'reason' => __('field.technician_ack')]);
                    $w->refresh();
                }
                $number = ($latest?->number ?? 0) + 1;
                $snapshot = ['number' => $number, 'reference' => $w->reference, 'work_uuid' => $w->uuid, 'organization' => $w->organization->name, 'property' => $w->property->name, 'building' => $w->building?->name, 'building_uuid' => $w->building?->uuid, 'space' => $w->space?->name, 'asset' => $w->asset?->name, 'asset_uuid' => $w->asset?->uuid, 'asset_reference' => $w->asset?->reference, 'technician' => $u->name, 'technician_id' => $u->id, 'company' => $ctx['grant']?->company_name, 'at' => now()->toIso8601String(), 'problem' => $w->description ?? $w->title, 'work_performed' => $w->work_performed, 'condition' => $w->observed_condition, 'minutes' => $w->minutes_spent, 'checklist' => $w->checklist ?? [], 'parts' => $w->parts ?? [], 'labor_cost' => $w->labor_cost, 'external_cost' => $w->external_cost, 'materials_cost' => $w->materials_cost, 'currency' => $w->currency, 'costs_permitted' => $ctx['costs'], 'serial' => $d['observed_serial'] ?? null, 'installation' => $d['observed_installation'] ?? null, 'ack' => (bool) $d['submit'], 'attachments' => $w->attachments()->when($ctx['external'], fn ($q) => $q->whereIn('version_id', $ctx['grant']->documents()->whereNotNull('version_id')->select('version_id')))->with('version')->limit(100)->get()->map(fn ($a) => ['source_uuid' => $a->source_uuid, 'name' => $a->version?->original_name, 'sha256' => $a->version?->sha256])->all()];
                $snapshot['locale'] = app()->getLocale();
                if (! $ctx['costs']) {
                    foreach (['labor_cost', 'external_cost', 'materials_cost', 'currency'] as $k) {
                        $snapshot[$k] = null;
                    }$snapshot['parts'] = array_map(function ($p) {
                        unset($p['cost']);

                        return $p;
                    }, $snapshot['parts']);
                }
                $version = null;
                if ($d['submit']) {
                    $before = Version::where('building_id', $w->building_id)->pluck('id');
                    $version = $this->storePdf($u, $w, $snapshot);
                    if (! $before->contains($version->id)) {
                        $newPaths[] = $version->path;
                    }
                    $w->attachments()->firstOrCreate(['source_uuid' => $version->id], ['version_id' => $version->id, 'uploaded_by' => $u->id, 'created_at' => now()]);
                    if ($ctx['grant']) {
                        $ctx['grant']->documents()->firstOrCreate(['source_uuid' => $version->id], ['version_id' => $version->id, 'created_at' => now()]);
                    }
                }
                $r = ServiceReport::create(['uuid' => (string) Str::uuid(), 'organization_id' => $w->organization_id, 'property_id' => $w->property_id, 'work_order_id' => $w->id, 'author_id' => $u->id, 'field_invitation_id' => $ctx['grant']?->id, 'number' => $number, 'work_revision' => $w->revision, 'state' => $d['submit'] ? 'submitted' : 'draft', 'snapshot' => $snapshot, 'version_id' => $version?->id, 'source_uuid' => $version?->id, 'created_at' => now()]);
                if ($version && $w->asset) {
                    foreach (['serial' => 'equipment.serial_number', 'installation' => 'equipment.installation_date'] as $k => $semantic) {
                        if (! filled($snapshot[$k])) {
                            continue;
                        }$a = app(Engine::class)->propose($u, $w->building, ['semantic_key' => $semantic, 'subject_type' => 'equipment', 'subject_reference' => $w->asset->external_reference ?: $w->asset->reference, 'value' => $snapshot[$k], 'quote' => $snapshot[$k]], $version, null, 'maintenance.report', $w);
                        AssetEvidenceLink::firstOrCreate(['asset_id' => $w->asset_id, 'source_uuid' => $a->id], ['assertion_id' => $a->id, 'linked_by' => $u->id, 'created_at' => now()]);
                    }
                }
                app(Workflow::class)->audit($w, $u, $d['submit'] ? 'report_submitted' : 'report_draft', null, $r->uuid);

                return $r;
            });
        } catch (\Throwable $e) {
            $this->cleanup($newPaths);
            throw $e;
        }
    }

    public function review(User $u, ServiceReport $report, array $input): void
    {
        $newPaths = [];
        try {
            DB::transaction(function () use ($u, $report, $input, &$newPaths) {
                Property::whereKey($report->property_id)->lockForUpdate()->firstOrFail();
                $w = WorkOrder::lockForUpdate()->findOrFail($report->work_order_id);
                app(Workflow::class)->allowed($u, $w->property, true);
                Gate::forUser($u)->authorize('view', $w);
                $d = Validator::make($input, ['sequence' => 'required|integer|min:0', 'decision' => 'required|in:reviewed,accepted,returned', 'reason' => 'required|string|max:2000'])->validate();
                $last = $report->reviews()->latest('sequence')->first();
                $sequence = $last?->sequence ?? 0;
                if ((int) $d['sequence'] !== $sequence || $report->state !== 'submitted' || $w->serviceReports()->max('number') !== $report->number || in_array($last?->decision, ['accepted', 'returned']) || ($d['decision'] !== 'returned' && $w->revision !== $report->work_revision) || ! $report->version_id) {
                    throw ValidationException::withMessages(['report' => __('field.report_stale')]);
                }
                if ($d['decision'] === 'accepted' && $last?->decision !== 'reviewed') {
                    throw ValidationException::withMessages(['report' => __('field.review_first')]);
                }
                $version = null;
                if ($d['decision'] === 'accepted') {
                    abort_unless($w->state === 'resolved', 422);
                    app(Workflow::class)->transition($u, $w, ['revision' => $w->revision, 'state' => 'verified', 'reason' => $d['reason']]);
                    $w->refresh();
                    app(Workflow::class)->transition($u, $w, ['revision' => $w->revision, 'state' => 'closed', 'reason' => $d['reason']]);
                    $w->refresh();
                    $before = Version::where('building_id', $w->building_id)->pluck('id');
                    $source = $report->version;
                    $version = $this->storePdf($u, $w, $report->snapshot, ['by' => $u->name, 'at' => now()->toIso8601String(), 'reason' => $d['reason']], $source->document_id);
                    if (! $before->contains($version->id)) {
                        $newPaths[] = $version->path;
                    }
                    $w->attachments()->firstOrCreate(['source_uuid' => $version->id], ['version_id' => $version->id, 'uploaded_by' => $u->id, 'created_at' => now()]);
                    if ($w->asset_id) {
                        AssetDocumentLink::firstOrCreate(['asset_id' => $w->asset_id, 'source_uuid' => $version->id], ['version_id' => $version->id, 'linked_by' => $u->id, 'created_at' => now()]);
                    }
                }
                if ($d['decision'] === 'returned' && $w->state !== 'in_progress') {
                    app(Workflow::class)->transition($u, $w, ['revision' => $w->revision, 'state' => 'in_progress', 'reason' => $d['reason']]);
                    $w->refresh();
                }
                ServiceReportReview::create(['service_report_id' => $report->id, 'sequence' => $sequence + 1, 'decision' => $d['decision'], 'reviewed_by' => $u->id, 'reason' => $d['reason'], 'version_id' => $version?->id, 'source_uuid' => $version?->id, 'created_at' => now()]);
                app(Workflow::class)->audit($w, $u, 'report_'.$d['decision'], null, $report->uuid.' · '.$d['reason']);
            });
        } catch (\Throwable $e) {
            $this->cleanup($newPaths);
            throw $e;
        }
    }

    private function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('evidence')->delete($path);
            } catch (\Throwable) {
                DB::table('evidence_file_deletions')->insertOrIgnore(['path' => $path, 'created_at' => now()]);
            }
        }
    }
}
