<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attribute_schema' => 'array', 'revision' => 'integer', 'archived_at' => 'datetime'];
    }

    public function label(): string
    {
        return $this->organization_id ? $this->name : __('asset.type_'.$this->key);
    }
}
