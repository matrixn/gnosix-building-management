<?php

namespace App\Models\Evidence;

use Illuminate\Database\Eloquent\Model;

class Conflict extends Model
{
    protected $table = 'evidence_conflicts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['baseline' => 'array'];
    }
}
