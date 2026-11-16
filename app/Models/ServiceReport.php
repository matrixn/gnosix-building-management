<?php

namespace App\Models;

use App\Models\Evidence\Version;
use Illuminate\Database\Eloquent\Model;

class ServiceReport extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['number' => 'integer', 'work_revision' => 'integer', 'snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviews()
    {
        return $this->hasMany(ServiceReportReview::class);
    }

    public function version()
    {
        return $this->belongsTo(Version::class, 'version_id');
    }

    public function status(): string
    {
        return $this->reviews()->latest('sequence')->value('decision') ?? $this->state;
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Create a new report revision.'));
        static::deleting(fn () => throw new \LogicException('Preserve service reports.'));
    }
}
