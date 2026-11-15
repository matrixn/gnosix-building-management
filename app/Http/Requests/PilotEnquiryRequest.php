<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PilotEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
            'property' => ['required', Rule::in(['hotel', 'residential', 'office', 'portfolio'])],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Completează numele tău.',
            'name.max' => 'Numele poate avea cel mult 100 de caractere.',
            'name.not_regex' => 'Introdu numele pe un singur rând.',
            'email.required' => 'Completează adresa de email.',
            'email.email' => 'Introdu o adresă de email validă.',
            'email.max' => 'Adresa de email poate avea cel mult 160 de caractere.',
            'property.required' => 'Alege tipul proprietății.',
            'property.in' => 'Alege un tip de proprietate din listă.',
            'message.required' => 'Spune-ne pe scurt ce ai vrea să rezolvăm.',
            'message.min' => 'Descrierea trebuie să aibă cel puțin 10 caractere.',
            'message.max' => 'Descrierea poate avea cel mult 3000 de caractere.',
            'consent.accepted' => 'Confirmă că ai citit informarea privind datele personale.',
            'website.prohibited' => 'Cererea nu a putut fi validată. Reîncarcă pagina și încearcă din nou.',
        ];
    }
}
