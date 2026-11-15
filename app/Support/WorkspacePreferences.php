<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class WorkspacePreferences
{
    public static function countryRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', Rule::in(\libphonenumber\PhoneNumberUtil::getInstance()->getSupportedRegions())];
    }
    public static function timezoneRules(): array { return ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())]; }
}
