<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetPhoto extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
