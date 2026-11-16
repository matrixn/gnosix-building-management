<?php

namespace App\Models\Evidence;

use Illuminate\Database\Eloquent\Model;

class Reference extends Model
{
    protected $table = 'evidence_references';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['region' => 'array', 'page' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Evidence originals and history are immutable.'));
        static::deleting(fn () => throw new \LogicException('Use the authorized retention workflow.'));
    }

    public function version()
    {
        return $this->belongsTo(Version::class, 'version_id');
    }
}
