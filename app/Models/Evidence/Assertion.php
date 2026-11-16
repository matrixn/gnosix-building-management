<?php

namespace App\Models\Evidence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Assertion extends Model
{
    protected $table = 'evidence_assertions';

    protected $guarded = [];

    protected $attributes = ['status' => 'proposed', 'revision' => 1, 'human_verification' => 'not_reviewed', 'professional_verification' => 'not_assessed'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['value' => 'json', 'revision' => 'integer', 'ai_confidence' => 'float', 'reviewed_at' => 'datetime', 'effective_on' => 'date'];
    }

    public function references()
    {
        return $this->hasMany(Reference::class, 'assertion_id');
    }

    public function conflicts()
    {
        return $this->hasMany(Conflict::class, 'assertion_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function booted(): void
    {
        static::updating(function ($a) {
            if ($a->isDirty(['building_id', 'space_id', 'version_id', 'extraction_id', 'subject_type', 'subject_reference', 'semantic_key', 'value_type', 'value', 'original_value', 'method', 'ai_confidence', 'documentary_support', 'professional_verification', 'effective_on', 'created_by'])) {
                throw new \LogicException('Create a correction; assertion provenance and values are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Use authorized retention.'));
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'assertion_id');
    }
}
