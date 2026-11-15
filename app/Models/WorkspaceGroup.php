<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceGroup extends Model
{
    protected $fillable = ['organization_id', 'name', 'kind'];
    public function properties() { return $this->belongsToMany(Property::class, 'property_group'); }
}
