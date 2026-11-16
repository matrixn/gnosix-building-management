<?php

namespace App\Assets;

use App\Evidence\Engine;
use App\Evidence\Facts;
use App\Models\Asset;
use App\Models\AssetDocumentLink;
use App\Models\AssetEvidenceLink;
use App\Models\AssetFactApplication;
use App\Models\Building;
use App\Models\Evidence\Assertion;
use App\Models\Evidence\Version;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvidenceBridge
{
    public function source(User $u, Asset $a, Assertion $fact, bool $write = false): void
    {
        $b = Building::whereKey($fact->building_id)->firstOrFail();
        abort_unless($b->property_id === $a->property_id, 404);
        app(Engine::class)->authorize($u, $b, $write, $write ? 'evidence.review' : 'evidence.read');
    }

    public function link(User $u, Asset $asset, Assertion $fact, int $revision): AssetEvidenceLink
    {
        return DB::transaction(function () use ($u, $asset, $fact, $revision) {
            $registry = app(Registry::class);
            $a = $registry->lock($asset->property, $asset);
            $registry->writable($u, $a);
            $registry->revision($a, $revision);
            $this->source($u, $a, $fact);
            $link = AssetEvidenceLink::firstOrCreate(['asset_id' => $a->id, 'source_uuid' => $fact->id], ['assertion_id' => $fact->id, 'linked_by' => $u->id]);
            if ($link->withdrawn_at) {
                $link->update(['withdrawn_at' => null]);
            }$a->increment('revision');
            $registry->audit($a, $u, 'evidence_linked', null, ['assertion_uuid' => $fact->id]);

            return $link;
        });
    }

    public function document(User $u, Asset $asset, Version $v, int $revision): void
    {
        DB::transaction(function () use ($u, $asset, $v, $revision) {
            $r = app(Registry::class);
            $a = $r->lock($asset->property, $asset);
            $r->writable($u, $a);
            $r->revision($a, $revision);
            $b = Building::findOrFail($v->building_id);
            abort_unless($b->property_id === $a->property_id, 404);
            app(Engine::class)->authorize($u, $b, false, 'evidence.read');
            AssetDocumentLink::firstOrCreate(['asset_id' => $a->id, 'source_uuid' => $v->id], ['version_id' => $v->id, 'linked_by' => $u->id, 'created_at' => now()]);
            $a->increment('revision');
            $r->audit($a, $u, 'document_linked', null, ['version_uuid' => $v->id]);
        });
    }

    public function valid(AssetFactApplication $app): bool
    {
        $link = $app->link;
        $f = $link?->assertion;

        return $app->status === 'active' && $link && ! $link->withdrawn_at && $f && $f->status === 'confirmed' && $f->revision === $app->source_revision && ! app(Engine::class)->conflicts($f)->exists() && Building::whereKey($f->building_id)->exists();
    }

    public function apply(User $u, Asset $asset, AssetEvidenceLink $link, int $revision, int $sourceRevision, string $note, bool $replace = false): void
    {
        DB::transaction(function () use ($u, $asset, $link, $revision, $sourceRevision, $note, $replace) {
            $r = app(Registry::class);
            $a = $r->lock($asset->property, $asset);
            $r->writable($u, $a, 'review');
            $r->revision($a, $revision);
            abort_unless($link->asset_id === $a->id && ! $link->withdrawn_at && $link->assertion_id, 404);
            $f = Assertion::lockForUpdate()->findOrFail($link->assertion_id);
            $this->source($u, $a, $f, true);
            $field = config('assets.evidence_fields')[$f->semantic_key] ?? null;
            if (! $field || $f->status !== 'confirmed' || $f->revision !== $sourceRevision || app(Engine::class)->conflicts($f)->exists()) {
                throw ValidationException::withMessages(['evidence' => __('asset.source_not_ready')]);
            }$note = Facts::clean($note, 2000);
            if (! filled($note)) {
                throw ValidationException::withMessages(['justification' => __('asset.justify')]);
            }
            $prior = $a->applications()->where('field', $field)->where('status', 'active')->lockForUpdate()->get();
            $manual = $a->manual_data[$field] ?? null;
            $contradiction = $manual !== null && ! Facts::same($manual, $f->value, $f->value_type);
            foreach ($prior as $old) {
                if (! Facts::same($old->value, $f->value, $f->value_type)) {
                    $contradiction = true;
                }
            }
            if ($contradiction && ! $replace) {
                throw ValidationException::withMessages(['replace' => __('asset.replace_explicit')]);
            }
            $a->applications()->where('field', $field)->where('status', 'active')->update(['status' => 'superseded']);
            AssetFactApplication::create(['asset_id' => $a->id, 'link_id' => $link->id, 'field' => $field, 'value' => $f->value, 'serial_key' => $field === 'serial_number' ? Registry::serial((string) $f->value) : null, 'source_revision' => $f->revision, 'justification' => $note, 'reviewed_by' => $u->id, 'created_at' => now()]);
            $a->increment('revision');
            $r->audit($a, $u, 'fact_applied', ['field' => $field, 'manual' => $manual, 'prior' => $prior->map->attributesToArray()->all()], ['field' => $field, 'value' => $f->value, 'assertion_uuid' => $f->id], $note);
        });
    }

    public function withdraw(User $u, Asset $asset, AssetEvidenceLink $link, int $revision, string $note): void
    {
        DB::transaction(function () use ($u, $asset, $link, $revision, $note) {
            $r = app(Registry::class);
            $a = $r->lock($asset->property, $asset);
            $r->writable($u, $a, 'review');
            $r->revision($a, $revision);
            abort_unless($link->asset_id === $a->id, 404);
            $note = Facts::clean($note, 2000);
            if (! filled($note)) {
                throw ValidationException::withMessages(['justification' => __('asset.justify')]);
            }$link->update(['withdrawn_at' => now()]);
            $a->applications()->where('link_id', $link->id)->where('status', 'active')->update(['status' => 'withdrawn']);
            $a->increment('revision');
            $r->audit($a, $u, 'evidence_withdrawn', null, ['assertion_uuid' => $link->source_uuid], $note);
        });
    }
}
