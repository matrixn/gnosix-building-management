<?php

namespace App\Support;

class LandingCountry
{
    public function isForeign(?string $ip): bool
    {
        if ($ip === null || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $address = inet_pton($ip);
        if (strlen($address) === 16 && substr($address, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $address = substr($address, 12);
        }
        if (! filter_var(inet_ntop($address), FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
            return false;
        }

        $size = strlen($address);
        $path = resource_path('geo/ipv'.($size === 4 ? '4' : '6').'.bin');
        $file = @fopen($path, 'rb');
        if (! $file) {
            return false;
        }

        try {
            return $this->find($file, $address, $size) === 'F';
        } finally {
            fclose($file);
        }
    }

    private function find($file, string $address, int $size): ?string
    {
        $width = 2 * $size + 1;
        $bytes = fstat($file)['size'] ?? 0;
        if ($bytes % $width !== 0) {
            return null;
        }

        $low = 0;
        $high = intdiv($bytes, $width) - 1;
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
            } elseif (strcmp($address, substr($record, $size, $size)) > 0) {
                $low = $middle + 1;
            } else {
                return $record[$width - 1];
            }
        }

        return null;
    }
}
