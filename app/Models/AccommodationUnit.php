<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccommodationUnit extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope('current', fn ($q) => $q->whereNull($q->getModel()->qualifyColumn('archived_at')));
    }

    public function spaces()
    {
        return $this->belongsToMany(Space::class, 'accommodation_unit_space');
    }

    public function bed()
    {
        return $this->belongsTo(SpaceBed::class, 'bed_id');
    }
}
