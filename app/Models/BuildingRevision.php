<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildingRevision extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Revisions are append-only.'));
        static::deleting(fn () => throw new \LogicException('Revisions are append-only.'));
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }
}
