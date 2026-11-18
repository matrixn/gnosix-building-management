<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildingDeclarationDraft extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    protected function casts(): array
    {
        return ['state' => 'array', 'version' => 'integer', 'base_version' => 'integer', 'completed_at' => 'datetime'];
    }
}
