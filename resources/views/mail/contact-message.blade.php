GNOSIX — Mesaj din formularul de contact

Nume complet: {!! $enquiry['name'] !!}
Adresă email: {!! $enquiry['email'] !!}
Număr de telefon: {!! $enquiry['phone'] !!}
Subiect: {!! \App\Support\ContactSubjects::SUBJECTS[$enquiry['subject']] !!}
Limba paginii: {{ $enquiry['locale'] }}

Mesaj:
{!! $enquiry['message'] !!}

Solicitantul a confirmat citirea informării privind datele personale.
Răspunde la acest email pentru a contacta solicitantul.
