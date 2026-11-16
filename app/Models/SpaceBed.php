<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaceBed extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope('current', fn ($q) => $q->whereNull($q->getModel()->qualifyColumn('archived_at')));
    }

    public function space()
    {
        return $this->belongsTo(Space::class);
    }
}
