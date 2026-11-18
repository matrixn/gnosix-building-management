<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessage;
use App\Rules\ContactPhone;
use App\Support\CampaignAttribution;
use App\Support\ContactAvailability;
use App\Support\PublicSite;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactController extends Controller
{
    public function __invoke(ContactRequest $request)
    {
        $redirect = PublicSite::home().'#contact';
        $data = $request->safe()->only(['name', 'email', 'phone', 'subject', 'message']);
        if (! ContactAvailability::ready()) {
            return redirect($redirect)->withErrors(['delivery' => __('Formularul nu este disponibil momentan. Te rugăm să revii.')])->withInput($data);
        }
        $data['phone'] = ContactPhone::normalize($data['phone']);
        $data['locale'] = PublicSite::locale();
        try {
            Mail::to(\App\Support\SiteSettings::value('contact_recipient'))->send(new ContactMessage($data));
        } catch (Throwable $exception) {
            Log::error('Contact message delivery failed.', ['type' => $exception::class]);
            return redirect($redirect)->withErrors(['delivery' => __('Mesajul nu a putut fi trimis. Datele sunt păstrate în formular pentru o nouă încercare.')])
                ->withInput($request->safe()->only(['name', 'email', 'phone', 'subject', 'message', 'consent']));
        }
        app(CampaignAttribution::class)->contact($request);

        return redirect($redirect)->with('contact_sent', true);
    }
}
