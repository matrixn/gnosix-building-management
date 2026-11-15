<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Throwable;

class ContactPhone implements ValidationRule
{
    public static function normalize(string $value): ?string
    {
        if (! preg_match('/\A\+?[0-9() .-]{6,39}\z/', $value)) {
            return null;
        }
        try {
            $util = PhoneNumberUtil::getInstance();
            $number = $util->parse($value, 'RO');
            return ! $number->hasExtension() && $util->isValidNumber($number)
                ? $util->format($number, PhoneNumberFormat::E164) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail(__('Introdu un număr de telefon valid. Pentru numere din afara României, include prefixul internațional (de exemplu +44).'));
        }
    }
}
