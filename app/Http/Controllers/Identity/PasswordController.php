<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CustomerIdentity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function forgot()
    {
        return view('identity.forgot');
    }

    public function send(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);
        try {
            Password::broker('users')->sendResetLink(['email' => strtolower($data['email'])]);
        } catch (\Throwable $exception) {
            Log::warning('customer_reset_delivery_failed', ['exception_type' => get_class($exception)]);
        }
        // Same response for missing addresses, throttled tokens and delivery errors.
        return redirect()->route('password.request')->with('status', __('identity.reset_sent'));
    }

    public function reset(Request $request, string $token)
    {
        return view('identity.reset', ['token' => $token, 'email' => is_string($request->query('email')) ? $request->query('email') : '']);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => CustomerIdentity::passwordRules(),
        ]);
        $data['email'] = strtolower($data['email']);
        $status = Password::broker('users')->reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->increment('auth_version');
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('identity.reset_invalid')]);
        }
        return redirect()->route('login', ['lang' => app()->getLocale()])->with('status', __('identity.password_reset'));
    }
}
