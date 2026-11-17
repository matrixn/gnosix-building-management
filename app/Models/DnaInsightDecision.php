<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DnaInsightDecision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'snapshot' => 'array'];
    }
}
