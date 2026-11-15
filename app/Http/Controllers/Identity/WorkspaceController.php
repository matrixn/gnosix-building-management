<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function onboarding(Request $request)
    {
        return $request->user('web')->onboarding_completed_at ? redirect()->route('app.dashboard') : view('identity.onboarding');
    }

    public function complete(Request $request)
    {
        if ($request->user('web')->onboarding_completed_at) {
            return redirect()->route('app.dashboard');
        }
        $data = $request->validate(['profile_type' => ['required', 'string', 'in:owner,manager,technical']]);
        // This is context for the welcome experience, never an authorization role.
        $request->user('web')->forceFill(['profile_type' => $data['profile_type'], 'onboarding_completed_at' => now()])->save();
        return redirect()->route('app.dashboard')->with('status', __('identity.onboarding_done'));
    }

    public function dashboard()
    {
        return view('identity.dashboard');
    }
}
