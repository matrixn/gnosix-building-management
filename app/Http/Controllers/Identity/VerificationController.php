<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Support\CustomerIdentity;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function show(Request $request)
    {
        return $request->user('web')->hasVerifiedEmail() ? redirect()->route(CustomerIdentity::destination($request->user('web'))) : view('identity.verify');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();
        return redirect()->route(CustomerIdentity::destination($request->user('web')))->with('status', __('identity.email_verified'));
    }

    public function send(Request $request)
    {
        $user = $request->user('web');
        if ($user->hasVerifiedEmail()) {
            return redirect()->route(CustomerIdentity::destination($user));
        }
        $sent = CustomerIdentity::verifyEmail($user);
        return redirect()->route('verification.notice')->with('delivery_warning', ! $sent)->with('status', __($sent ? 'identity.verification_sent' : 'identity.mail_unavailable'));
    }
}
