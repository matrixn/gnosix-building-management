<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Building extends Model
{
    use SoftDeletes;

    protected $fillable = ['property_id', 'name', 'display_name', 'reference', 'classification', 'custom_classification', 'address', 'country', 'timezone', 'latitude', 'longitude', 'construction_year', 'state'];

    protected function casts(): array
    {
        return ['construction_year' => 'integer', 'declaration_version' => 'integer', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    protected static function booted(): void
    {
        static::creating(function ($m) {
            $m->uuid = (string) Str::uuid();
            if ($m->reference === null) {
                do {
                    $reference = 'GNX-'.implode('-', str_split(strtoupper(Str::random(16)), 4));
                } while (static::withTrashed()->where('reference', $reference)->exists());
                $m->reference = $reference;
            }
        });
        static::updating(function ($m) {
            if ($m->isDirty(['uuid', 'property_id'])) {
                throw new \LogicException('Building identity and ownership are immutable.');
            }
        });
    }

    public function revisions()
    {
        return $this->hasMany(BuildingRevision::class);
    }

    public function assertions()
    {
        return $this->hasMany(BuildingAssertion::class);
    }

    public function accommodationUnits()
    {
        return $this->hasMany(AccommodationUnit::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function floors()
    {
        return $this->hasMany(Floor::class);
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
