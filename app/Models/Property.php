<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
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
