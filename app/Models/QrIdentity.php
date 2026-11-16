<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrIdentity extends Model
{
    protected $guarded = [];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function locators()
    {
        return $this->hasMany(QrLocator::class);
    }

    public function active()
    {
        return $this->locators()->whereNull('revoked_at')->latest('id')->first();
    }

    public function target()
    {
        return match ($this->target_type) {
            'building' => Building::find($this->building_id),'floor' => Floor::find($this->floor_id),'space' => Space::find($this->space_id),'asset' => Asset::find($this->asset_id),default => null
        };
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('QR target identity is immutable.'));
        static::deleting(fn () => throw new \LogicException('Revoke the locator.'));
    }
}
