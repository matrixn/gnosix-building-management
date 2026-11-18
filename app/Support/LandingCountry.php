<?php

namespace App\Support;

use Illuminate\Http\Request;

class LandingCountry
{
    public const UNKNOWN = 'ZZ';

    public function forRequest(Request $request): string
    {
        $key = self::class;
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, $this->countryCode($request->ip()) ?? self::UNKNOWN);
        }

        return $request->attributes->get($key);
    }

    public function countryCode(?string $ip): ?string
    {
        if ($ip === null || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $address = inet_pton($ip);
        if (strlen($address) === 16 && substr($address, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $address = substr($address, 12);
        }
        if (! filter_var(inet_ntop($address), FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
            return null;
        }

        $size = strlen($address);
        $path = resource_path('geo/ipv'.($size === 4 ? '4' : '6').'.bin');
        $file = @fopen($path, 'rb');
        if (! $file) {
            return null;
        }

        try {
            return $this->find($file, $address, $size);
        } finally {
            fclose($file);
        }
    }

    private function find($file, string $address, int $size): ?string
    {
        $width = $size + 2;
        $bytes = fstat($file)['size'] ?? 0;
        if ($bytes % $width !== 0) {
            return null;
        }

        $low = 0;
        $high = intdiv($bytes, $width) - 1;
        $country = null;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            if (fseek($file, $middle * $width) !== 0) {
                return null;
            }
            $record = fread($file, $width);
            if ($record === false || strlen($record) !== $width) {
                return null;
            }
            // Binary addresses are big endian. strcmp avoids numeric string coercion.
            if (strcmp($address, substr($record, 0, $size)) < 0) {
                $high = $middle - 1;
            } else {
                $country = substr($record, -2);
                $low = $middle + 1;
            }
        }

        return $country !== null && $country !== self::UNKNOWN && preg_match('/^[A-Z]{2}$/D', $country) ? $country : null;
    }
}
