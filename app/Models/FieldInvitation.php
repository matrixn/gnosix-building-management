<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldInvitation extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash', 'email'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'redeemed_at' => 'datetime', 'revoked_at' => 'datetime', 'allow_costs' => 'boolean'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function workOrders()
    {
        return $this->belongsToMany(WorkOrder::class, 'field_invitation_work');
    }

    public function documents()
    {
        return $this->hasMany(FieldDocument::class);
    }
}
