<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationCapability extends Model
{
    protected $fillable = ['organization_id', 'capability', 'enabled', 'updated_by'];

    protected function casts(): array { return ['enabled' => 'boolean']; }
}
