<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DnaRelation extends Model
{
    protected $guarded = [];

    protected $attributes = ['revision' => 1, 'backing' => 'new'];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'reviewed_at' => 'datetime', 'review_basis' => 'array'];
    }

    public function sources()
    {
        return $this->hasMany(DnaRelationSource::class);
    }

    public function revisions()
    {
        return $this->hasMany(DnaRelationRevision::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    protected static function booted(): void
    {
        static::updating(function ($r) {
            if ($r->isDirty(['uuid', 'organization_id', 'property_id', 'type_key', 'source', 'target', 'origin_key', 'dna_relation_type_id', 'created_by'])) {
                throw new \LogicException('Create a replacement relationship.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Supersede relationships.'));
    }
}
