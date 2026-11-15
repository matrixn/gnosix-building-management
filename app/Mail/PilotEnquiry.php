<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PilotEnquiry extends Mailable
{
    public function __construct(public array $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->enquiry['email'], $this->enquiry['name'])],
            subject: 'GNOSIX — Cerere parteneriat pilot',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.pilot-enquiry');
    }
}
