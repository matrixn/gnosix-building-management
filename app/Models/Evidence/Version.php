<?php

namespace App\Models\Evidence;

use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    protected $table = 'evidence_versions';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['bytes' => 'integer', 'number' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Evidence originals and history are immutable.'));
        static::deleting(fn () => throw new \LogicException('Use the authorized retention workflow.'));
    }

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function extraction()
    {
        return $this->hasOne(Extraction::class, 'version_id');
    }
}
