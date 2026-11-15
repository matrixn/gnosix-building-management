<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceDraft extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'state', 'step', 'version', 'guided', 'result', 'completed_at'];
    protected $attributes = ['version' => 1, 'guided' => true];
    protected function casts(): array { return ['state' => 'array', 'result' => 'array', 'version' => 'integer', 'guided' => 'boolean', 'completed_at' => 'datetime']; }
}
