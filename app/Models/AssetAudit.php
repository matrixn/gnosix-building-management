<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAudit extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array', 'created_at' => 'datetime'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Asset audit is append-only.'));
        static::deleting(fn () => throw new \LogicException('Asset audit is append-only.'));
    }
}
