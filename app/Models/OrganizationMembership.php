<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationMembership extends Model
{
    public const ROLES = ['owner', 'admin', 'manager', 'member', 'technician', 'reporter'];

    protected $fillable = ['organization_id', 'user_id', 'role', 'access_scope'];

    protected $attributes = ['access_scope' => 'all'];

    public function propertyAccesses()
    {
        return $this->hasMany(PropertyAccess::class, 'membership_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
