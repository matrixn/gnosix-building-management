<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Asset extends Model
{
    protected $guarded = [];

    protected $attributes = ['status' => 'unknown', 'revision' => 1, 'is_system' => false];

    protected function casts(): array
    {
        return ['manual_data' => 'array', 'revision' => 'integer', 'is_system' => 'boolean', 'archived_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function ($a) {
            $a->uuid = (string) Str::uuid();
            $a->reference = 'AST-'.strtoupper(substr(str_replace('-', '', $a->uuid), 0, 20));
        });
        static::updating(function ($a) {
            if ($a->isDirty(['uuid', 'reference', 'organization_id', 'property_id'])) {
                throw new \LogicException('Asset identity and property ownership are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Archive assets instead.'));
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function type()
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function system()
    {
        return $this->belongsTo(self::class, 'system_id');
    }

    public function components()
    {
        return $this->hasMany(self::class, 'system_id');
    }

    public function servedSpaces()
    {
        return $this->belongsToMany(Space::class, 'asset_served_spaces');
    }

    public function evidenceLinks()
    {
        return $this->hasMany(AssetEvidenceLink::class);
    }

    public function applications()
    {
        return $this->hasMany(AssetFactApplication::class);
    }

    public function documents()
    {
        return $this->hasMany(AssetDocumentLink::class);
    }

    public function photos()
    {
        return $this->hasMany(AssetPhoto::class);
    }

    public function audits()
    {
        return $this->hasMany(AssetAudit::class);
    }

    public function scopeAvailable($q)
    {
        return $q->where(fn ($q) => $q->whereNull('building_id')->orWhereHas('building'));
    }
}
