<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

class PlatformAdmin extends Authenticatable implements HasPasskeys
{
    use InteractsWithPasskeys;

    protected $guarded = ['id'];
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'auth_version' => 'integer'];
    }

    public function getPasskeyId(): string
    {
        return 'platform-admin:'.$this->id;
    }
}
