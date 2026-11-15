<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = ['name'];

    public function memberships() { return $this->hasMany(OrganizationMembership::class); }
    public function profile() { return $this->hasOne(OrganizationProfile::class); }
    public function capabilityOverrides() { return $this->hasMany(OrganizationCapability::class); }
}
