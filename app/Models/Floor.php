<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Floor extends Model
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

    protected $fillable = ['building_id', 'name', 'position', 'kind', 'archived_at'];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function spaces()
    {
        return $this->hasMany(Space::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
