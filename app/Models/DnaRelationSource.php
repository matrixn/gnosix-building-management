<?php

namespace App\Models;

use App\Models\Evidence\Assertion;
use App\Models\Evidence\Version;
use Illuminate\Database\Eloquent\Model;

class DnaRelationSource extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['source_revision' => 'integer', 'page' => 'integer'];
    }

    public function version()
    {
        return $this->belongsTo(Version::class);
    }

    public function assertion()
    {
        return $this->belongsTo(Assertion::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Provenance is immutable.'));
        static::deleting(fn () => throw new \LogicException('Preserve source provenance.'));
    }
}
