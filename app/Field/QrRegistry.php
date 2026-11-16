<?php

namespace App\Field;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Property;
use App\Models\QrIdentity;
use App\Models\QrLocator;
use App\Models\Space;
use App\Models\User;
use App\Support\CapabilityService;
use App\Support\SiteSettings;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class QrRegistry
{
    public function target(Property $p, string $kind, string $uuid)
    {
        $class = match ($kind) {
            'asset' => Asset::class,'building' => Building::class,'floor' => Floor::class,'space' => Space::class,default => abort(404)
        };
        $q = $class::where('uuid', $uuid);
        if ($kind === 'floor') {
            $q->whereHas('building', fn ($q) => $q->where('property_id', $p->id));
        } else {
            $q->where($kind === 'building' ? 'property_id' : 'property_id', $p->id);
        }$target = $q->firstOrFail();
        abort_unless($this->available($target, $kind), 404);

        return $target;
    }

    public function available($t, string $kind): bool
    {
        if (! $t) {
            return false;
        }if ($kind === 'building') {
            return ! $t->trashed() && $t->state !== 'archived';
        }if ($kind === 'asset' && $t->archived_at) {
            return false;
        }

        return ! $t->building_id || $t->building()->where('state', '!=', 'archived')->exists();
    }

    public function manage(User $u, Property $p): void
    {
        Gate::forUser($u)->authorize('update', $p);
        abort_unless(app(CapabilityService::class)->allows($u, $p->organization, 'hospitality.maintenance'), 403);
    }

    public function generate(User $u, Property $p, string $kind, string $uuid, string $action = 'generate'): QrIdentity
    {
        return DB::transaction(function () use ($u, $p, $kind, $uuid, $action) {
            Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $this->manage($u, $p);
            $t = $this->target($p, $kind, $uuid);
            $id = QrIdentity::firstOrCreate(['target_type' => $kind, 'target_uuid' => $uuid], ['uuid' => (string) Str::uuid(), 'organization_id' => $p->organization_id, 'property_id' => $p->id, $kind.'_id' => $t->id]);
            $old = $id->active();
            if ($old && in_array($action, ['rotate', 'revoke'])) {
                $old->update(['revoked_at' => now()]);
            }
            if ($action !== 'revoke' && (! $old || $action === 'rotate')) {
                $id->locators()->create(['locator' => bin2hex(random_bytes(24)), 'created_at' => now()]);
            }
            AssetAudit::create(['organization_id' => $p->organization_id, 'property_id' => $p->id, 'asset_id' => $kind === 'asset' ? $t->id : null, 'entity_type' => 'qr', 'entity_uuid' => $id->uuid, 'actor_id' => $u->id, 'action' => 'field.qr_'.$action, 'after_data' => ['target_uuid' => $uuid], 'created_at' => now()]);

            return $id;
        });
    }

    public function url(QrLocator $l): string
    {
        return rtrim(SiteSettings::value('canonical_url'), '/').'/q/'.$l->locator;
    }

    public function render(QrLocator $l, string $format = 'svg'): string
    {
        $qr = new QrCode(data: $this->url($l), size: 320, margin: 16, errorCorrectionLevel: ErrorCorrectionLevel::Medium);

        return ($format === 'png' ? new PngWriter : new SvgWriter)->write($qr)->getString();
    }
}
