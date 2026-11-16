<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Space extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('current', fn ($q) => $q->whereNull($q->getModel()->qualifyColumn('archived_at')));
        static::creating(fn ($m) => $m->uuid = (string) Str::uuid());
        static::updating(function ($m) {
            if ($m->isDirty('uuid')) {
                throw new \LogicException('Spatial identity is immutable.');
            }
        });
    }

    protected $fillable = ['property_id', 'building_id', 'floor_id', 'name', 'space_type', 'number', 'bed_count', 'capacity', 'area_m2', 'archived_at'];

    protected function casts(): array
    {
        return ['bed_count' => 'integer', 'capacity' => 'integer', 'area_m2' => 'decimal:3'];
    }

    public function beds()
    {
        return $this->hasMany(SpaceBed::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }
}
