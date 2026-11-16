<?php

namespace App\Models;

use App\Models\Evidence\Version;
use Illuminate\Database\Eloquent\Model;

class MaintenanceAttachment extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function version()
    {
        return $this->belongsTo(Version::class, 'version_id');
    }
}
