<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DnaRelationRevision extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Append revisions.'));
        static::deleting(fn () => throw new \LogicException('Preserve revisions.'));
    }
}
