<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingSession extends Model
{
    protected $fillable = ['user_id', 'organization_id', 'state', 'current_step', 'version', 'completed_at'];

    protected $attributes = ['version' => 1];
    protected function casts(): array { return ['state' => 'array', 'version' => 'integer', 'completed_at' => 'datetime']; }
}
