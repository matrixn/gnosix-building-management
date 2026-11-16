<?php

namespace App\Models;

use App\Models\Evidence\Version;
use Illuminate\Database\Eloquent\Model;

class AssetDocumentLink extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function version()
    {
        return $this->belongsTo(Version::class, 'version_id');
    }
}
