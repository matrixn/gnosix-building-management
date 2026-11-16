<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = ['name', 'legal_name', 'country', 'timezone', 'locale', 'unit_system', 'contact_email', 'contact_phone', 'contact_address', 'logo_path'];

    protected $attributes = ['country' => 'RO', 'timezone' => 'Europe/Bucharest', 'locale' => 'ro', 'unit_system' => 'metric'];

    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function profile()
    {
        return $this->hasOne(OrganizationProfile::class);
    }

    public function capabilityOverrides()
    {
        return $this->hasMany(OrganizationCapability::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function groups()
    {
        return $this->hasMany(WorkspaceGroup::class);
    }

    public function legalEntities()
    {
        return $this->hasMany(LegalEntity::class);
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
