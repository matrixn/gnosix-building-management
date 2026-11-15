<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalEntity extends Model
{
    protected $fillable = ['organization_id', 'name', 'country'];
    public function properties() { return $this->hasMany(Property::class); }
}
