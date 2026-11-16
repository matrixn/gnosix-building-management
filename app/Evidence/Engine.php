<?php

namespace App\Evidence;

use App\Jobs\ExtractEvidence;
use App\Models\Building;
use App\Models\Evidence\Assertion;
use App\Models\Evidence\Audit;
use App\Models\Evidence\Conflict;
use App\Models\Evidence\Document;
use App\Models\Evidence\Extraction;
use App\Models\Evidence\Reference;
use App\Models\Evidence\Review;
use App\Models\Evidence\Source;
use App\Models\Evidence\Version;
use App\Models\Property;
use App\Models\User;
use App\Policies\MaintenancePolicy;
use App\Support\CapabilityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Engine
{
    public function authorize(User $user, Building $building, bool $write = false, string $capability = 'evidence.upload'): void
    {
        abort_if($building->trashed(), 404);
        if ($capability === 'maintenance.report') {
            abort_unless(app(MaintenancePolicy::class)->report($user, $building->property), 403);
            abort_if($building->state === 'archived', 403);

            return;
        }
        Gate::forUser($user)->authorize($write ? 'update' : 'view', $building->property);
        abort_unless($capability === 'evidence.read' ? (app(CapabilityService::class)->allows($user, $building->property->organization, 'evidence.upload') || app(CapabilityService::class)->allows($user, $building->property->organization, 'evidence.review')) : app(CapabilityService::class)->allows($user, $building->property->organization, $capability), 403);
        if ($write) {
            abort_if($building->state === 'archived', 403);
        }
    }

    public function audit(Building $b, ?User $u, string $action, string $reference, array $details = []): void
    {
        Audit::create(['building_id' => $b->id, 'actor_id' => $u?->id, 'action' => $action, 'entity_reference' => $reference, 'details' => $details, 'created_at' => now()]);
    }

    private function lock(Building $b): Building
    {
        Property::whereKey($b->property_id)->lockForUpdate()->firstOrFail();

        return Building::whereKey($b->id)->lockForUpdate()->firstOrFail();
    }

    public function uploads(User $u, Building $b, array $files, string $type, ?string $documentId = null, string $capability = 'evidence.upload'): array
    {
        $this->authorize($u, $b, true, $capability);
        $settings = app(Settings::class)->all();
        $prepared = [];
        if (count($files) < 1 || count($files) > config('evidence.max_files') || ($documentId && count($files) !== 1)) {
            throw ValidationException::withMessages(['files' => __('evidence.upload_invalid')]);
        }
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid() || $file->getSize() > $settings['max_file_mb'] * 1048576) {
                throw ValidationException::withMessages(['files' => __('evidence.upload_invalid')]);
            }
            $bytes = file_get_contents($file->getRealPath());
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            $pdf = $mime === 'application/pdf' && str_starts_with($bytes, '%PDF-') && str_contains(substr($bytes, -2048), '%%EOF');
            if ($pdf && preg_match('~/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA|RichMedia|XFA|Encrypt)\b~i', $bytes)) {
                throw ValidationException::withMessages(['files' => __('evidence.active_pdf')]);
            }
            $image = in_array($mime, ['image/png', 'image/jpeg'], true);
            if ($image) {
                $size = @getimagesizefromstring($bytes);
                $image = $size && $size[0] > 0 && $size[1] > 0 && $size[0] * $size[1] <= 24000000 && $size['mime'] === $mime && ($mime === 'image/png' ? str_starts_with($bytes, "\x89PNG\r\n\x1a\n") : str_starts_with($bytes, "\xff\xd8\xff"));
            }
            if (! $pdf && ! $image) {
                throw ValidationException::withMessages(['files' => __('evidence.upload_invalid')]);
            }
            $prepared[] = ['bytes' => $bytes, 'mime' => $mime, 'sha' => hash('sha256', $bytes), 'name' => self::filename($file->getClientOriginalName())];
        }
        $written = [];
        try {
            return DB::transaction(function () use ($u, $b, $prepared, $type, $documentId, $settings, &$written, $capability) {
                $b = $this->lock($b);
                $this->authorize($u, $b, true, $capability);
                $result = [];
                $target = $documentId ? Document::where('building_id', $b->id)->whereNull('archived_at')->findOrFail($documentId) : null;
                foreach ($prepared as $file) {
                    $existing = Version::where('building_id', $b->id)->where('sha256', $file['sha'])->first();
                    if ($existing) {
                        $this->audit($b, $u, 'duplicate_upload', $existing->id);
                        $result[] = ['version' => $existing, 'duplicate' => true];

                        continue;
                    }
                    if (Version::where('building_id', $b->id)->count() >= 500 || Version::where('building_id', $b->id)->sum('bytes') + strlen($file['bytes']) > $settings['building_quota_mb'] * 1048576) {
                        throw ValidationException::withMessages(['files' => __('evidence.quota')]);
                    }
                    $doc = $target;
                    if (! $doc) {
                        $source = Source::create(['building_id' => $b->id, 'kind' => 'upload', 'label' => $file['name'], 'created_by' => $u->id]);
                        $doc = Document::create(['id' => (string) Str::uuid(), 'building_id' => $b->id, 'source_id' => $source->id, 'title' => $file['name'], 'document_type' => $type]);
                    }
                    $id = (string) Str::uuid();
                    $path = $b->property->organization_id.'/'.$b->uuid.'/'.$id.'/original.bin';
                    $written[] = $path;
                    Storage::disk('evidence')->put($path, $file['bytes']);
                    $version = Version::create(['id' => $id, 'building_id' => $b->id, 'document_id' => $doc->id, 'number' => ($doc->versions()->max('number') ?? 0) + 1, 'original_name' => $file['name'], 'path' => $path, 'mime' => $file['mime'], 'bytes' => strlen($file['bytes']), 'sha256' => $file['sha'], 'uploaded_by' => $u->id]);
                    $this->audit($b, $u, 'uploaded', $version->id, ['sha256' => $version->sha256, 'version' => $version->number]);
                    $result[] = ['version' => $version, 'duplicate' => false];
                }

                return $result;
            });
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                try {
                    Storage::disk('evidence')->delete($path);
                } catch (\Throwable) {
                    DB::table('evidence_file_deletions')->insertOrIgnore(['path' => $path, 'created_at' => now()]);
                }
            }throw $e;
        }
    }

    public static function filename(string $name): string
    {
        return mb_substr(preg_replace('~[\x00-\x1F\x7F/\\\\]~u', '_', trim($name)), 0, 180) ?: 'document';
    }

    public function propose(User $u, Building $b, array $input, ?Version $version = null, ?Extraction $extraction = null, string $capability = 'evidence.upload'): Assertion
    {
        return DB::transaction(function () use ($u, $b, $input, $version, $extraction, $capability) {
            $b = $this->lock($b);
            $this->authorize($u, $b, true, $capability);
            if ($version) {
                abort_unless($version->building_id === $b->id, 404);
            }
            if (Assertion::where('building_id', $b->id)->count() >= 10000) {
                throw ValidationException::withMessages(['assertion' => __('evidence.review_capacity')]);
            }
            $data = app(Facts::class)->validate($b, $input);
            $reference = array_intersect_key($data, array_flip(['quote', 'page', 'region']));
            unset($data['quote'],$data['page'],$data['region']);
            if ($extraction && ! filled($reference['quote'])) {
                throw ValidationException::withMessages(['quote' => __('evidence.quote_required')]);
            }
            if ($version && $version->mime !== 'application/pdf' && ($reference['page'] ?? 1) !== 1) {
                throw ValidationException::withMessages(['page' => __('evidence.invalid_value')]);
            }
            $data['ai_confidence'] = $extraction ? $data['ai_confidence'] : null;
            $a = Assertion::create(['id' => (string) Str::uuid(), 'building_id' => $b->id, 'version_id' => $version?->id, 'extraction_id' => $extraction?->id, 'method' => $extraction ? 'ai' : 'manual', 'documentary_support' => $version ? 'source_linked' : 'none', 'created_by' => $u->id, ...$data]);
            if ($version) {
                Reference::create(['assertion_id' => $a->id, 'version_id' => $version->id, ...$reference, 'created_at' => now()]);
            }
            $this->detect($b, $a);
            $this->audit($b, $u, 'proposed', $a->id, ['method' => $a->method]);

            return $a;
        });
    }

    private function detect(Building $b, Assertion $a): void
    {
        $openCount = Conflict::where('building_id', $b->id)->where('status', 'open')->count();
        Assertion::where('building_id', $b->id)->where('id', '!=', $a->id)->where('subject_type', $a->subject_type)->where('subject_reference', $a->subject_reference)->where('semantic_key', $a->semantic_key)->whereIn('status', ['proposed', 'confirmed', 'disputed'])->orderBy('id')->chunkById(100, function ($peers) use ($a, $b, &$openCount) {
            foreach ($peers as $peer) {
                if (! Facts::same($peer->value, $a->value, $a->value_type)) {
                    if (++$openCount > 2000) {
                        throw ValidationException::withMessages(['assertion' => __('evidence.review_capacity')]);
                    }Conflict::create(['building_id' => $b->id, 'assertion_id' => $a->id, 'existing_assertion_id' => $peer->id]);
                }
            }
        });
        if ($a->semantic_key === 'building.construction_year' && $b->construction_year !== null && ! Facts::same($b->construction_year, $a->value)) {
            Conflict::create(['building_id' => $b->id, 'assertion_id' => $a->id, 'baseline' => ['source' => 'manual_declaration', 'value' => $b->construction_year, 'reference' => $b->reference]]);
        }
    }

    public function conflicts(Assertion $a)
    {
        return Conflict::where('building_id', $a->building_id)->where(fn ($q) => $q->where('assertion_id', $a->id)->orWhere('existing_assertion_id', $a->id))->where('status', 'open');
    }

    public function review(User $u, Building $b, string $id, int $revision, string $action, ?string $note = null, ?string $value = null): Assertion
    {
        return DB::transaction(function () use ($u, $b, $id, $revision, $action, $note, $value) {
            $b = $this->lock($b);
            $this->authorize($u, $b, true, 'evidence.review');
            $a = Assertion::where('building_id', $b->id)->lockForUpdate()->findOrFail($id);
            $note = Facts::clean($note, 2000);
            if ($a->revision !== $revision || ! in_array($a->status, ['proposed', 'disputed'], true)) {
                throw ValidationException::withMessages(['assertion' => __('evidence.review_stale')]);
            }
            if (! in_array($action, ['confirm', 'reject', 'dispute', 'supersede', 'correct'], true)) {
                abort(422);
            }
            $open = $this->conflicts($a)->lockForUpdate()->get();
            if (in_array($action, ['supersede', 'correct', 'dispute'], true) && ! filled($note)) {
                throw ValidationException::withMessages(['justification' => __('evidence.justify')]);
            }
            if ($open->count() && $action === 'confirm') {
                throw ValidationException::withMessages(['assertion' => __('evidence.resolve_first')]);
            }
            if ($action === 'supersede') {
                abort_unless(app(CapabilityService::class)->allows($u, $b->property->organization, 'evidence.conflicts'), 403);
                foreach ($open as $conflict) {
                    $otherId = $conflict->assertion_id === $a->id ? $conflict->existing_assertion_id : $conflict->assertion_id;
                    if ($otherId) {
                        $other = Assertion::where('building_id', $b->id)->lockForUpdate()->findOrFail($otherId);
                        if (in_array($other->status, ['proposed', 'confirmed', 'disputed'], true)) {
                            $this->transition($b, $u, $other, 'superseded', 'superseded', $note, $a->id);
                            $this->conflicts($other)->update(['status' => 'resolved', 'resolved_by' => $u->id, 'justification' => $note, 'updated_at' => now()]);
                        }
                    }
                }
            }
            $before = $a->attributesToArray();
            $corrected = null;
            if ($action === 'correct') {
                if ($value === null) {
                    throw ValidationException::withMessages(['value' => __('evidence.invalid_value')]);
                }$ref = $a->references()->first();
                $corrected = $this->propose($u, $b, ['semantic_key' => $a->semantic_key, 'subject_type' => $a->subject_type, 'subject_reference' => $a->subject_reference, 'space_id' => $a->space_id, 'value' => $value, 'quote' => $ref?->quote, 'page' => $ref?->page, 'region' => $ref?->region, 'effective_on' => $a->effective_on?->format('Y-m-d')], $a->version_id ? Version::findOrFail($a->version_id) : null, null, 'evidence.review');
            }
            $status = match ($action) {
                'confirm','supersede' => 'confirmed','reject' => 'rejected','dispute' => 'disputed','correct' => 'superseded'
            };
            $this->transition($b, $u, $a, $status, $action, $note, $corrected?->id);
            if ($action !== 'dispute') {
                $this->conflicts($a)->update(['status' => 'resolved', 'resolved_by' => $u->id, 'justification' => $note ?? $action, 'updated_at' => now()]);
            }

            return $corrected ?? $a;
        });
    }

    private function transition(Building $b, User $u, Assertion $a, string $status, string $action, ?string $note, ?string $replacement = null): void
    {
        $before = $a->attributesToArray();
        $a->status = $status;
        $a->human_verification = $status === 'confirmed' ? 'reviewed_confirmed' : 'reviewed_'.$status;
        $a->reviewed_by = $u->id;
        $a->reviewed_at = now();
        $a->revision++;
        $a->save();
        Review::create(['building_id' => $b->id, 'assertion_id' => $a->id, 'reviewer_id' => $u->id, 'action' => $action, 'justification' => $note, 'before_data' => $before, 'after_data' => array_merge($a->attributesToArray(), $replacement ? ['replacement_assertion_id' => $replacement] : []), 'created_at' => now()]);
        $this->audit($b, $u, 'review_'.$action, $a->id);
    }

    public function extract(User $u, Building $b, Version $version, bool $consent): Extraction
    {
        return DB::transaction(function () use ($u, $b, $version, $consent) {
            $b = $this->lock($b);
            $this->authorize($u, $b, true);
            abort_unless($version->building_id === $b->id, 404);
            if (! $consent) {
                throw ValidationException::withMessages(['consent' => __('evidence.consent_required')]);
            }if (! app(Settings::class)->available()) {
                throw ValidationException::withMessages(['provider' => __('evidence.provider_unavailable')]);
            }abort_unless($version->document()->whereNull('archived_at')->exists(), 403);
            $existing = Extraction::where('version_id', $version->id)->first();
            if ($existing && in_array($existing->status, ['queued', 'processing', 'retrying', 'succeeded'], true)) {
                return $existing;
            }
            if (! Providers::supports(app(Settings::class)->all()['provider'], $version->mime)) {
                throw ValidationException::withMessages(['provider' => __('evidence.unsupported_format')]);
            }
            $s = app(Settings::class)->all();
            $job = Extraction::updateOrCreate(['version_id' => $version->id], ['id' => $existing?->id ?? (string) Str::uuid(), 'building_id' => $b->id, 'status' => 'queued', 'provider' => $s['provider'], 'model' => $s['model'], 'requested_by' => $u->id, 'consented_at' => now(), 'error_code' => null, 'started_at' => null, 'finished_at' => null]);
            $this->audit($b, $u, 'extraction_queued', $job->id, ['provider' => $job->provider, 'model' => $job->model]);
            DB::afterCommit(fn () => ExtractEvidence::dispatch($job->id)->onConnection('evidence')->onQueue('evidence'));

            return $job;
        });
    }

    public function archive(User $u, Building $b, Document $doc, bool $archived): void
    {
        DB::transaction(function () use ($u, $b, $doc, $archived) {
            $b = $this->lock($b);
            $this->authorize($u, $b, true);
            abort_unless($doc->building_id === $b->id, 404);
            $doc->archived_at = $archived ? now() : null;
            $doc->save();
            $this->audit($b, $u, $archived ? 'document_archived' : 'document_restored', $doc->id);
        });
    }
}
