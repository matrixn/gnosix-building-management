<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationProfile extends Model
{
    protected $fillable = ['organization_id', 'profile_key', 'organization_kind', 'schema_version'];

    public function organization() { return $this->belongsTo(Organization::class); }
}
