<?php

namespace App\Models\Evidence;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $table = 'evidence_sources';

    protected $guarded = [];

    protected function casts(): array
    {
        return [];
    }
}
