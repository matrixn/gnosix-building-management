<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyAccess extends Model
{
    protected $fillable = ['membership_id', 'property_id', 'permission'];
    public function property() { return $this->belongsTo(Property::class); }
}
