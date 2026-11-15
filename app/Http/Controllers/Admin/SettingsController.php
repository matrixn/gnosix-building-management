<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\SmtpSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SettingsController extends Controller
{
    public function show(Request $request, SmtpSettings $settings)
    {
        $tab = in_array($request->query('tab'), ['general', 'smtp', 'security']) ? $request->query('tab') : 'general';
        $smtp = $settings->resolved();
        $hasPassword = filled($smtp['password']);
        unset($smtp['password']);
        return view('admin.settings', [
            'tab' => $tab, 'smtp' => $smtp, 'hasPassword' => $hasPassword,
            'smtpSaved' => $settings->saved() !== null,
            'passkeys' => Auth::guard('platform_admin')->user()->passkeys()->latest()->get(),
        ]);
    }

    public function updateSmtp(Request $request, SmtpSettings $settings)
    {
        $validator = Validator::make($request->all(), [
            'host' => ['required', 'string', 'max:253', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9.\-]*\z/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'scheme' => ['required', 'in:smtp,smtps'],
            'username' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'smtp_password' => ['nullable', 'string', 'max:1024'],
            'clear_password' => ['nullable', 'boolean'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
        ], [
            'required' => 'Completează câmpul :attribute.',
            'email' => 'Introdu o adresă de email validă pentru :attribute.',
            'integer' => 'Portul trebuie să fie un număr întreg.',
            'between.numeric' => 'Portul trebuie să fie între 1 și 65535.',
            'max.string' => 'Câmpul :attribute poate avea cel mult :max caractere.',
            'in' => 'Alege o opțiune validă pentru :attribute.',
            'not_regex' => 'Câmpul :attribute trebuie să fie pe un singur rând.',
            'host.regex' => 'Introdu doar numele serverului SMTP, fără protocol sau cale.',
        ], ['host' => 'server SMTP', 'port' => 'port', 'scheme' => 'criptare', 'username' => 'utilizator SMTP', 'smtp_password' => 'parolă SMTP', 'from_address' => 'email expeditor', 'from_name' => 'nume expeditor']);
        if ($validator->fails()) {
            return redirect()->route('admin.settings', ['tab' => 'smtp'])->withErrors($validator)->withInput($request->except('smtp_password'));
        }
        $data = $validator->validated();
        $password = $request->boolean('clear_password') ? null : ($settings->resolved()['password'] ?? null);
        if (filled($data['smtp_password'] ?? null)) {
            $password = $data['smtp_password'];
        }
        if (filled($data['username'] ?? null) !== filled($password)) {
            return redirect()->route('admin.settings', ['tab' => 'smtp'])->withErrors(['smtp' => 'Completează atât utilizatorul, cât și parola SMTP, sau lasă ambele necompletate pentru un relay fără autentificare.'])->withInput($request->except('smtp_password'));
        }
        PlatformSetting::updateOrCreate(['key' => 'smtp'], ['value' => [
            'host' => $data['host'], 'port' => (int) $data['port'], 'scheme' => $data['scheme'],
            'username' => $data['username'] ?? null, 'password' => $password,
            'from_address' => $data['from_address'], 'from_name' => $data['from_name'],
        ], 'updated_by' => Auth::guard('platform_admin')->id()]);
        return redirect()->route('admin.settings', ['tab' => 'smtp'])->with('status', 'Setările SMTP au fost salvate. Toate emailurile sistemului folosesc această configurație.');
    }

    public function testSmtp()
    {
        try {
            Mail::raw('Acesta este un mesaj de test pentru configurația SMTP GNOSIX. Primirea lui confirmă livrarea către această căsuță.', function ($message) {
                $message->to(Auth::guard('platform_admin')->user()->email)->subject('GNOSIX — Test SMTP');
            });
        } catch (Throwable $exception) {
            Log::warning('Admin SMTP test failed.', ['type' => $exception::class]);
            return redirect()->route('admin.settings', ['tab' => 'smtp'])->withErrors(['smtp' => 'Trimiterea a eșuat. Verifică serverul, portul, criptarea și credențialele.']);
        }
        return redirect()->route('admin.settings', ['tab' => 'smtp'])->with('status', 'Serverul SMTP a acceptat mesajul de test. Verifică inboxul și Spam la adresa administratorului.');
    }
}
