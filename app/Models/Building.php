<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    protected $fillable = ['property_id', 'name'];
    public function property() { return $this->belongsTo(Property::class); }
    public function floors() { return $this->hasMany(Floor::class); }
    public function spaces() { return $this->hasMany(Space::class); }
}
