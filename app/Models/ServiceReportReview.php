<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceReportReview extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'created_at' => 'datetime'];
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Reviews are append-only.'));
        static::deleting(fn () => throw new \LogicException('Preserve reviews.'));
    }
}
