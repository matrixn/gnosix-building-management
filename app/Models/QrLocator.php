<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrLocator extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function identity()
    {
        return $this->belongsTo(QrIdentity::class, 'qr_identity_id');
    }
}
