<?php

namespace App\Models\Evidence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'evidence_reviews';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Evidence originals and history are immutable.'));
        static::deleting(fn () => throw new \LogicException('Use the authorized retention workflow.'));
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
