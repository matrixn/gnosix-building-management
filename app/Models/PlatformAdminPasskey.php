<?php

namespace App\Models;

use Spatie\LaravelPasskeys\Models\Passkey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\PublicKeyCredentialSource;

class PlatformAdminPasskey extends Passkey
{
    protected $table = 'platform_admin_passkeys';
    protected $hidden = ['data', 'credential_id'];

    public function data(): Attribute
    {
        $attribute = parent::data();
        return new Attribute(
            get: $attribute->get,
            set: fn (PublicKeyCredentialSource $value) => [
                'credential_id' => static::encodeCredentialId($value->publicKeyCredentialId),
                'data' => Serializer::make()->toJson($value),
            ],
        );
    }

    public static function encodeCredentialId(string $raw): string
    {
        // Stable binary-safe lookup on every supported database driver.
        return hash('sha256', $raw);
    }
}
