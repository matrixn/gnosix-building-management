<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Floor extends Model
{
    protected $fillable = ['building_id', 'name', 'position'];
    public function building() { return $this->belongsTo(Building::class); }
    public function spaces() { return $this->hasMany(Space::class); }
}
