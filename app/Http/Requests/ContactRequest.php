<?php

namespace App\Http\Requests;

use App\Rules\ContactPhone;
use App\Support\ContactSubjects;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function getRedirectUrl(): string
    {
        return \App\Support\PublicSite::home().'#contact';
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:120', 'not_regex:/[\r\n\x00-\x1F\x7F]/'],
            'email' => ['bail', 'required', 'string', 'email:rfc,strict', 'max:160'],
            'phone' => ['bail', 'required', 'string', 'max:40', new ContactPhone],
            'subject' => ['bail', 'required', 'string', Rule::in(array_keys(ContactSubjects::SUBJECTS))],
            'message' => ['bail', 'required', 'string', 'min:10', 'max:3000'],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('Completează câmpul :attribute.'),
            'string' => __('Câmpul :attribute trebuie să conțină text.'),
            'min.string' => __('Câmpul :attribute trebuie să aibă cel puțin :min caractere.'),
            'max.string' => __('Câmpul :attribute poate avea cel mult :max caractere.'),
            'email.email' => __('Introdu o adresă de email validă.'),
            'name.not_regex' => __('Introdu numele complet pe un singur rând.'),
            'subject.in' => __('Alege un subiect din listă.'),
            'consent.accepted' => __('Confirmă că ai citit informarea privind datele personale.'),
            'website.prohibited' => __('Cererea nu a putut fi validată. Reîncarcă pagina și încearcă din nou.'),
        ];
    }

    public function attributes(): array
    {
        return ['name' => __('nume complet'), 'email' => __('adresă email'), 'phone' => __('număr de telefon'), 'subject' => __('subiect'), 'message' => __('mesaj')];
    }
}
