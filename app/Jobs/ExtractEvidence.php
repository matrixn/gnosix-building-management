<?php

namespace App\Jobs;

use App\Evidence\Engine;
use App\Evidence\Extractor;
use App\Evidence\Settings;
use App\Models\Building;
use App\Models\Evidence\Extraction;
use App\Models\Evidence\Version;
use App\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExtractEvidence implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(public string $extractionId) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(Extractor $extractor, Engine $engine): void
    {
        $row = Extraction::find($this->extractionId);
        if (! $row) {
            return;
        }$b = Building::find($row->building_id);
        if (! $b) {
            $row->update(['status' => 'failed', 'error_code' => 'building_unavailable', 'finished_at' => now()]);

            return;
        }
        $claimed = DB::transaction(function () use ($row, $b) {
            Property::whereKey($b->property_id)->lockForUpdate()->firstOrFail();
            Building::whereKey($b->id)->lockForUpdate()->firstOrFail();
            $j = Extraction::lockForUpdate()->find($row->id);
            if (! $j || $j->status === 'succeeded' || ($j->status === 'processing' && $j->started_at?->gt(now()->subSeconds(150)))) {
                return false;
            }$j->update(['status' => 'processing', 'started_at' => now(), 'attempts' => $j->attempts + 1, 'error_code' => null]);

            return true;
        });
        if (! $claimed) {
            return;
        }
        try {
            $row->refresh();
            $u = User::find($row->requested_by);
            if (! $u) {
                throw new \RuntimeException('access_revoked');
            }$engine->authorize($u, $b, true);
            if (! app(Settings::class)->available()) {
                throw new \RuntimeException('provider_unavailable');
            }$s = app(Settings::class)->all();
            if ($s['provider'] !== $row->provider || $s['model'] !== $row->model) {
                throw new \RuntimeException('provider_changed');
            }
            $version = Version::findOrFail($row->version_id);
            if ($version->document->archived_at) {
                throw new \RuntimeException('document_archived');
            }
            if (! hash_equals($version->sha256, hash('sha256', Storage::disk('evidence')->get($version->path)))) {
                throw new \RuntimeException('integrity_failed');
            }
            $engine->audit($b, $u, 'extraction_started', $row->id, ['attempt' => $row->attempts]);
            $result = $extractor->extract($version);
            $result = Validator::make($result, ['document_type' => ['required', Rule::in(config('evidence.document_types'))], 'facts' => 'present|array|max:100'])->validate();
            DB::transaction(function () use ($engine, $u, $b, $version, $row, $result) {
                Property::whereKey($b->property_id)->lockForUpdate()->firstOrFail();
                $b = Building::whereKey($b->id)->lockForUpdate()->firstOrFail();
                $engine->authorize($u, $b, true);
                $current = Extraction::lockForUpdate()->find($row->id);
                if (! $current || $current->status === 'succeeded') {
                    return;
                }
                if ($version->document->fresh()->archived_at) {
                    throw new \RuntimeException('document_archived');
                }
                foreach ($result['facts'] as $fact) {
                    if (! is_array($fact)) {
                        throw new \RuntimeException('invalid_output');
                    }$engine->propose($u, $b, $fact, $version, $current);
                }
                DB::table('evidence_versions')->where('id', $version->id)->update(['classification' => $result['document_type']]);
                $current->update(['status' => 'succeeded', 'finished_at' => now(), 'error_code' => null]);
                $engine->audit($b, $u, 'extraction_succeeded', $row->id, ['candidates' => count($result['facts'])]);
            });
        } catch (\Throwable $e) {
            $code = in_array($e->getMessage(), ['provider_unavailable', 'provider_changed', 'document_archived', 'integrity_failed', 'access_revoked'], true) ? $e->getMessage() : ($e instanceof ValidationException ? 'invalid_output' : 'provider_failed');
            $permanent = in_array($code, ['provider_unavailable', 'provider_changed', 'document_archived', 'integrity_failed', 'access_revoked'], true) || $e instanceof AuthorizationException || $e instanceof HttpException;
            Extraction::whereKey($row->id)->update(['status' => $permanent || $row->attempts >= 3 ? 'failed' : 'retrying', 'error_code' => $code, 'finished_at' => now()]);
            if ($live = Building::find($row->building_id)) {
                $engine->audit($live, $u ?? null, 'extraction_failed', $row->id, ['code' => $code, 'attempt' => $row->attempts]);
            }
            if (! $permanent) {
                throw new \RuntimeException('Evidence extraction failed; retry is safe.');
            }
        }
    }

    public function failed(?\Throwable $e): void
    {
        Extraction::whereKey($this->extractionId)->where('status', '!=', 'succeeded')->update(['status' => 'failed', 'error_code' => 'worker_failed', 'finished_at' => now()]);
    }
}
