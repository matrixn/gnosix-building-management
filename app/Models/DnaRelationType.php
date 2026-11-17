<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DnaRelationType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['semantics' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Create a new semantic type.'));
        static::deleting(fn () => throw new \LogicException('Preserve semantic history.'));
    }
}
