<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class MaintenanceRecord extends Model
{
    protected $guarded = [];

    protected $attributes = ['revision' => 1];

    protected function casts(): array
    {
        return ['require_photo' => 'boolean', 'minutes_spent' => 'integer', 'revision' => 'integer', 'checklist' => 'array', 'parts' => 'array', 'anchor_on' => 'immutable_date', 'next_on' => 'immutable_date', 'next_at' => 'datetime', 'scheduled_for' => 'immutable_date', 'reported_at' => 'datetime', 'closed_at' => 'datetime', 'resolved_at' => 'datetime', 'verified_at' => 'datetime', 'due_at' => 'datetime', 'notify_assignee' => 'boolean', 'labor_cost' => 'decimal:2', 'external_cost' => 'decimal:2', 'materials_cost' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function ($m) {
            if ($m instanceof MaintenancePlan) {
                $m->next_at = $m->next_on ? CarbonImmutable::parse($m->next_on->format('Y-m-d'), $m->property->timezone)->endOfDay()->utc() : null;
            }
        });
        static::creating(function ($m) {
            $m->uuid = (string) Str::uuid();
            $m->reference = ($m instanceof WorkOrder ? 'WO' : ($m instanceof MaintenancePlan ? 'PM' : 'ISS')).'-'.strtoupper(substr(str_replace('-', '', $m->uuid), 0, 20));
        });
        static::updating(function ($m) {
            if ($m->isDirty(['uuid', 'reference', 'organization_id', 'property_id'])) {
                throw new \LogicException('Operational identity is immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Preserve operational history.'));
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(MaintenanceAttachment::class, $this instanceof WorkOrder ? 'work_order_id' : 'issue_id');
    }

    public function scopeAvailable($q)
    {
        return $q->where(fn ($q) => $q->whereNull('building_id')->orWhereHas('building'));
    }

    public function history()
    {
        return AssetAudit::where('entity_type', $this->kind())->where('entity_uuid', $this->uuid)->orderByDesc('id');
    }

    public function kind(): string
    {
        return $this instanceof WorkOrder ? 'work' : ($this instanceof MaintenancePlan ? 'plan' : 'issue');
    }
}
