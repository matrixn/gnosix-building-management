<?php

namespace Tests\Support;

/** Synthetic ES256 authenticator. No real user credentials or device enrollment. */
class VirtualPasskey
{
    private \OpenSSLAsymmetricKey $key;
    public string $id;

    public function __construct()
    {
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->id = random_bytes(32);
    }

    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function head(int $major, int $length): string
    {
        return $length < 24 ? chr(($major << 5) | $length) : ($length < 256 ? chr(($major << 5) | 24).chr($length) : chr(($major << 5) | 25).pack('n', $length));
    }

    private static function integer(int $value): string
    {
        return $value >= 0 ? self::head(0, $value) : self::head(1, -1 - $value);
    }

    private static function bytes(string $value): string { return self::head(2, strlen($value)).$value; }
    private static function text(string $value): string { return self::head(3, strlen($value)).$value; }
    private static function map(array $pairs): string { return self::head(5, count($pairs)).implode('', array_map(fn ($pair) => $pair[0].$pair[1], $pairs)); }

    public function registration(array $options, string $origin): array
    {
        $details = openssl_pkey_get_details($this->key)['ec'];
        $cose = self::map([
            [self::integer(1), self::integer(2)], [self::integer(3), self::integer(-7)],
            [self::integer(-1), self::integer(1)], [self::integer(-2), self::bytes($details['x'])], [self::integer(-3), self::bytes($details['y'])],
        ]);
        $authData = hash('sha256', $options['rp']['id'], true).chr(0x45).pack('N', 0).str_repeat("\0", 16).pack('n', strlen($this->id)).$this->id.$cose;
        $attestation = self::map([[self::text('fmt'), self::text('none')], [self::text('attStmt'), self::map([])], [self::text('authData'), self::bytes($authData)]]);
        return ['id' => self::encode($this->id), 'rawId' => self::encode($this->id), 'type' => 'public-key', 'clientExtensionResults' => (object) [],
            'response' => ['clientDataJSON' => self::encode(json_encode(['type' => 'webauthn.create', 'challenge' => $options['challenge'], 'origin' => $origin, 'crossOrigin' => false])), 'attestationObject' => self::encode($attestation), 'transports' => ['internal']]];
    }

    public function assertion(array $options, string $origin, string $userHandle, int $counter = 1, bool $uv = true, ?string $rpId = null): array
    {
        $client = json_encode(['type' => 'webauthn.get', 'challenge' => $options['challenge'], 'origin' => $origin, 'crossOrigin' => false]);
        $authData = hash('sha256', $rpId ?? $options['rpId'], true).chr($uv ? 0x05 : 0x01).pack('N', $counter);
        openssl_sign($authData.hash('sha256', $client, true), $signature, $this->key, OPENSSL_ALGO_SHA256);
        return ['id' => self::encode($this->id), 'rawId' => self::encode($this->id), 'type' => 'public-key', 'clientExtensionResults' => (object) [],
            'response' => ['clientDataJSON' => self::encode($client), 'authenticatorData' => self::encode($authData), 'signature' => self::encode($signature), 'userHandle' => self::encode($userHandle)]];
    }
}
