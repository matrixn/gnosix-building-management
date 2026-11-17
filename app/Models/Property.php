<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Property extends Model
{
    protected static function booted(): void
    {
        static::creating(fn ($p) => $p->uuid = (string) Str::uuid());
        // An old release may insert a property during a zero-downtime migration.
        // Repair only missing technical identities; never replace an existing UUID.
        static::retrieved(function ($p) {
            if (array_key_exists('uuid', $p->getAttributes()) && $p->uuid === null) {
                $uuid = DB::transaction(function () use ($p) {
                    $query = DB::table('properties')->where('id', $p->id);
                    $current = (clone $query)->lockForUpdate()->value('uuid');
                    if ($current === null) {
                        $current = (string) Str::uuid();
                        $query->whereNull('uuid')->update(['uuid' => $current]);
                    }

                    return $current;
                });
                $p->uuid = $uuid;
                $p->syncOriginalAttribute('uuid');
            }
        });
        static::updating(function ($p) {
            if ($p->isDirty('uuid')) {
                throw new \LogicException('Property identity is immutable.');
            }
        });
    }

    protected $fillable = ['organization_id', 'legal_entity_id', 'name', 'property_type', 'country', 'timezone', 'address', 'amenities'];

    protected function casts(): array
    {
        return ['amenities' => 'array'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }

    public function spaces()
    {
        return $this->hasMany(Space::class);
    }

    public function groups()
    {
        return $this->belongsToMany(WorkspaceGroup::class, 'property_group');
    }

    public function legalEntity()
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function maintenanceIssues()
    {
        return $this->hasMany(MaintenanceIssue::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function maintenancePlans()
    {
        return $this->hasMany(MaintenancePlan::class);
    }
}
