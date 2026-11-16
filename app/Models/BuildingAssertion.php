<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildingAssertion extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Assertions are append-only.'));
        static::deleting(fn () => throw new \LogicException('Assertions are append-only.'));
    }
}
