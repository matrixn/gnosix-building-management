<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    protected $fillable = ['property_id', 'building_id', 'floor_id', 'name', 'space_type', 'number', 'bed_count', 'capacity', 'area_m2'];
    protected function casts(): array { return ['bed_count' => 'integer', 'capacity' => 'integer', 'area_m2' => 'decimal:3']; }
    public function property() { return $this->belongsTo(Property::class); }
    public function building() { return $this->belongsTo(Building::class); }
    public function floor() { return $this->belongsTo(Floor::class); }
}
