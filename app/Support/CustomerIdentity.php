<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class CustomerIdentity
{
    public static function destination(User $user): string
    {
        return ! $user->hasVerifiedEmail() ? 'verification.notice' : (($user->onboarding_completed_at || $user->memberships()->exists()) ? 'app.dashboard' : 'app.onboarding');
    }

    public static function establish(Request $request, User $user): void
    {
        if (Auth::guard('web')->id() !== $user->id) {
            Auth::guard('web')->login($user);
        }
        $request->session()->regenerate();
        $request->session()->put('customer_auth_version', $user->auth_version);
        $request->session()->put('customer_locale', $user->preferredLocale());
        // Do not trust an arbitrary intended URL or mix platform-admin navigation.
        $request->session()->forget('url.intended');
        $user->forceFill(['first_signed_in_at' => $user->first_signed_in_at ?? now(), 'last_signed_in_at' => now()])->save();
    }

    public static function intendedVerification(mixed $intended, User $user): ?string
    {
        if (! is_string($intended)) return null;
        $parts = parse_url($intended);
        $expected = '/app/verify-email/'.$user->id.'/'.sha1($user->email);
        if (($parts['path'] ?? '') !== $expected || ! isset($parts['query'])) return null;
        // Only a valid, unexpired verification path for this exact user may
        // resume after login. Return a relative path, never an external URL.
        $path = $expected.'?'.$parts['query'];
        return URL::hasValidRelativeSignature(Request::create($path)) ? $path : null;
    }

    public static function verifyEmail(User $user): bool
    {
        try {
            $user->sendEmailVerificationNotification();
            return true;
        } catch (\Throwable $exception) {
            // Do not log recipients, SMTP credentials, signed URLs or tokens.
            Log::warning('customer_verification_delivery_failed', ['exception_type' => get_class($exception)]);
            return false;
        }
    }

    public static function passwordRules(bool $confirmed = true): array
    {
        return array_merge(['required', 'string', 'min:12', 'max:72', function ($attribute, $value, $fail) {
            if (is_string($value) && strlen($value) > 72) {
                $fail(__('identity.password_bytes'));
            }
        }], $confirmed ? ['confirmed'] : []);
    }

    public static function rotate(Request $request, User $user): void
    {
        // Version checks work with file/database/Redis sessions, without deleting
        // rows belonging to the separately authenticated platform administrators.
        $user->increment('auth_version');
        $user->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->save();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put('customer_auth_version', $user->auth_version);
    }
}
