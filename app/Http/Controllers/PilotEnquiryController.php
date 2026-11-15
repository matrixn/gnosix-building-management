<?php

namespace App\Http\Controllers;

use App\Http\Requests\PilotEnquiryRequest;
use App\Mail\PilotEnquiry;
use App\Support\PilotAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PilotEnquiryController extends Controller
{
    public function __invoke(PilotEnquiryRequest $request): RedirectResponse
    {
        if (! PilotAvailability::ready()) {
            return redirect(route('home').'#pilot')
                ->withErrors(['delivery' => 'Formularul nu este disponibil momentan. Te rugăm să revii.']);
        }

        $data = $request->safe()->only(['name', 'email', 'property', 'message']);

        try {
            Mail::to(config('gnosix.pilot_recipient'))->send(new PilotEnquiry($data));
        } catch (Throwable $exception) {
            // Do not put visitor data or SMTP credentials in application logs.
            Log::error('Pilot enquiry delivery failed.', ['type' => $exception::class]);

            return redirect(route('home').'#pilot')
                ->withErrors(['delivery' => 'Mesajul nu a putut fi trimis. Datele sunt păstrate în formular pentru o nouă încercare.'])
                ->withInput($data + ['consent' => $request->boolean('consent')]);
        }

        return redirect(route('home').'#pilot')
            ->with('pilot_sent', true);
    }
}
