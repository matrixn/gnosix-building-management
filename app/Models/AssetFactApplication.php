<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetFactApplication extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json', 'source_revision' => 'integer', 'created_at' => 'datetime'];
    }

    public function link()
    {
        return $this->belongsTo(AssetEvidenceLink::class, 'link_id');
    }

    protected static function booted(): void
    {
        static::updating(function ($m) {
            if ($m->isDirty(['asset_id', 'link_id', 'field', 'value', 'serial_key', 'source_revision', 'justification', 'reviewed_by', 'created_at'])) {
                throw new \LogicException('Applications retain their original values and provenance.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Withdraw approvals instead.'));
    }
}
