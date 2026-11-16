<?php

namespace App\Models;

use App\Models\Evidence\Assertion;
use Illuminate\Database\Eloquent\Model;

class AssetEvidenceLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['withdrawn_at' => 'datetime'];
    }

    public function assertion()
    {
        return $this->belongsTo(Assertion::class, 'assertion_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
