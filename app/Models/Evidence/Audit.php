<?php

namespace App\Models\Evidence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    protected $table = 'evidence_audits';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Evidence originals and history are immutable.'));
        static::deleting(fn () => throw new \LogicException('Use the authorized retention workflow.'));
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
