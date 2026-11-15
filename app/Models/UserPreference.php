<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $fillable = ['user_id', 'active_organization_id', 'guided_setup'];

    public function organization() { return $this->belongsTo(Organization::class, 'active_organization_id'); }
}
