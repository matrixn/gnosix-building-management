<?php

namespace App\Http\Controllers\Admin;

use App\Evidence\Providers;
use App\Evidence\Settings;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\SiteSettings;
use App\Support\SmtpSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class SettingsController extends Controller
{
    public function show(Request $request, SmtpSettings $settings)
    {
        $tab = in_array($request->query('tab'), ['general', 'gnosix', 'smtp', 'security', 'evidence', 'maintenance', 'field', 'dna']) ? $request->query('tab') : 'general';
        $smtp = $settings->resolved();
        $hasPassword = filled($smtp['password']);
        unset($smtp['password']);

        $aiSettings = app(Settings::class);
        $aiProvider = $request->query('ai_provider', $aiSettings->all()['provider']);
        if (! is_string($aiProvider) || ! array_key_exists($aiProvider, Providers::all())) {
            $aiProvider = $aiSettings->all()['provider'];
        }
        $aiSelected = $aiSettings->forProvider($aiProvider);

        return view('admin.settings', [
            'dnaSettings' => app(\App\Dna\Settings::class)->all(),
            'fieldSettings' => app(\App\Field\Settings::class)->all(),
            'maintenance' => app(\App\Maintenance\Settings::class)->all(),
            'tab' => $tab, 'smtp' => $smtp, 'hasPassword' => $hasPassword,
            'site' => app(SiteSettings::class)->all(),
            'evidence' => array_diff_key($aiSelected, ['api_key' => true]),
            'aiProviders' => Providers::all(), 'activeAiProvider' => $aiSettings->all()['provider'],
            'hasEvidenceKey' => filled($aiSelected['api_key']),
            'smtpSaved' => $settings->saved() !== null,
            'passkeys' => Auth::guard('platform_admin')->user()->passkeys()->latest()->get(),
        ]);
    }

    public function updateGnosix(Request $request, SiteSettings $settings)
    {
        $validator = Validator::make($request->all(), [
            'canonical_url' => ['bail', 'required', 'string', 'max:255', function ($attribute, $value, $fail) {
                $url = SiteSettings::normalizeCanonicalUrl($value);
                if ($url === null || (app()->environment('production') && ! str_starts_with($url, 'https://'))) {
                    $fail('Introdu adresa de bază a site-ului, fără cale, parametri sau credențiale. În producție este obligatoriu HTTPS.');
                }
            }],
            'contact_recipient' => ['required', 'string', 'email:rfc,strict', 'max:160'],
            'contact_enabled' => ['required', 'boolean'],
            'indexable' => ['required', 'boolean'],
        ], [
            'required' => 'Completează câmpul :attribute.',
            'email' => 'Introdu o adresă de email validă pentru primirea mesajelor.',
            'boolean' => 'Alege o valoare validă pentru :attribute.',
            'max.string' => 'Câmpul :attribute poate avea cel mult :max caractere.',
        ], ['canonical_url' => 'adresă canonică', 'contact_recipient' => 'destinatar contact', 'contact_enabled' => 'formular activ', 'indexable' => 'indexare SEO']);
        if ($validator->fails()) {
            return redirect()->route('admin.settings', ['tab' => 'gnosix'])->withErrors($validator)->withInput($request->only(['canonical_url', 'contact_recipient', 'contact_enabled', 'indexable']));
        }
        $data = $validator->validated();
        $settings->save([
            'canonical_url' => SiteSettings::normalizeCanonicalUrl($data['canonical_url']),
            'contact_recipient' => $data['contact_recipient'],
            'contact_enabled' => $request->boolean('contact_enabled'),
            'indexable' => $request->boolean('indexable'),
        ], Auth::guard('platform_admin')->id());

        return redirect()->route('admin.settings', ['tab' => 'gnosix'])->with('status', 'Setările GNOSIX au fost salvate și sunt active imediat. Nu este necesar un deploy sau config:cache.');
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

    public function updateEvidence(Request $request, Settings $settings)
    {
        $v = Validator::make($request->all(), ['enabled' => 'required|boolean', 'provider' => ['required', Rule::in(array_keys(Providers::all()))], 'model' => ['bail', 'nullable', 'required_if:enabled,1', 'string', 'max:100', function ($attribute, $value, $fail) use ($request) {
            if (! is_string($request->input('provider')) || ! Providers::validModel($request->input('provider'), $value, $request->boolean('free_only', true))) {
                $fail('Model invalid. OpenRouter în modul gratuit acceptă numai openrouter/free sau identificatori terminați în :free. Ollama acceptă numai modele locale, fără cloud.');
            }
        }], 'api_key' => 'nullable|string|max:512', 'clear_key' => 'sometimes|boolean', 'free_only' => 'sometimes|boolean', 'max_file_mb' => 'required|integer|min:1|max:20', 'building_quota_mb' => 'required|integer|min:20|max:2048']);
        if ($v->fails()) {
            return redirect()->route('admin.settings', ['tab' => 'evidence', 'ai_provider' => $request->input('provider')])->withErrors($v)->withInput($request->except('api_key'));
        }
        $settings->save($v->validated(), Auth::guard('platform_admin')->id());

        return redirect()->route('admin.settings', ['tab' => 'evidence'])->with('status', 'Setările Evidence Engine au fost salvate. Salvarea nu trimite documente către furnizor.');
    }

    public function updateMaintenance(Request $request)
    {
        $v = Validator::make($request->all(), ['repeat_days' => 'required|integer|between:7,3650', 'repeat_threshold' => 'required|integer|between:2,20']);
        if ($v->fails()) {
            return redirect()->route('admin.settings', ['tab' => 'maintenance'])->withErrors($v)->withInput($request->only('repeat_days', 'repeat_threshold'));
        }
        PlatformSetting::updateOrCreate(['key' => 'maintenance'], ['value' => $v->validated(), 'updated_by' => Auth::guard('platform_admin')->id()]);

        return redirect()->route('admin.settings', ['tab' => 'maintenance'])->with('status', 'Setările de mentenanță au fost salvate.');
    }

    public function updateField(Request $r)
    {
        $v = Validator::make($r->all(), ['invite_max_days' => 'required|integer|between:1,365']);
        if ($v->fails()) {
            return redirect()->route('admin.settings', ['tab' => 'field'])->withErrors($v)->withInput($r->only('invite_max_days'));
        }PlatformSetting::updateOrCreate(['key' => 'field'], ['value' => $v->validated(), 'updated_by' => Auth::guard('platform_admin')->id()]);

        return redirect()->route('admin.settings', ['tab' => 'field'])->with('status', 'Setările portalului au fost salvate.');
    }

    public function updateDna(Request $r)
    {
        $d = $r->validate(['max_depth' => 'required|integer|between:1,12', 'max_nodes' => 'required|integer|between:40,300', 'timeout_ms' => 'required|integer|between:100,3000']);
        PlatformSetting::updateOrCreate(['key' => 'dna'], ['value' => $d, 'updated_by' => Auth::guard('platform_admin')->id()]);

        return redirect()->route('admin.settings', ['tab' => 'dna'])->with('status', 'Limitele Building DNA au fost salvate.');
    }
}
