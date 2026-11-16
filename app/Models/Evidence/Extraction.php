<?php

namespace App\Models\Evidence;

use Illuminate\Database\Eloquent\Model;

class Extraction extends Model
{
    protected $table = 'evidence_extractions';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'consented_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
