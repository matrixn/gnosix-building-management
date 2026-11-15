<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        app(SiteSettings::class)->save(['contact_enabled' => true, 'contact_recipient' => 'raduta.alex@gmail.com']);
        config(['mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.scheme' => 'smtps', 'mail.from.address' => 'contact@example.test']);
    }

    private function enquiry(array $overrides = []): array
    {
        return array_replace(['name' => 'Administrator Demo', 'email' => 'admin@example.test', 'phone' => '0722 123 456', 'subject' => 'pilot', 'message' => 'Dorim să organizăm istoricul echipamentelor.', 'consent' => '1', 'website' => ''], $overrides);
    }

    public function test_valid_contact_uses_central_recipient_and_normalizes_phone(): void
    {
        Mail::fake();
        $this->post('/contact', $this->enquiry())->assertRedirect(url('/').'#contact')->assertSessionHas('contact_sent', true)->assertSessionHasNoErrors();
        Mail::assertSent(ContactMessage::class, fn ($mail) => $mail->hasTo('raduta.alex@gmail.com') && $mail->envelope()->replyTo[0]->address === 'admin@example.test' && $mail->enquiry['phone'] === '+40722123456' && $mail->enquiry['subject'] === 'pilot' && ! isset($mail->enquiry['consent']));
        Mail::assertSentCount(1);
    }

    public function test_english_contact_and_international_phone_preserve_language(): void
    {
        Mail::fake();
        $this->post('/en/contact', $this->enquiry(['phone' => '+44 20 7946 0018', 'subject' => 'partnership']))->assertRedirect(url('/en').'#contact')->assertSessionHasNoErrors();
        Mail::assertSent(ContactMessage::class, fn ($mail) => $mail->enquiry['phone'] === '+442079460018' && $mail->enquiry['locale'] === 'en');
        $this->get('/en')->assertSee('Thank you for your message.');
    }

    public static function requiredFields(): array
    {
        return array_map(fn ($field) => [$field], ['name', 'email', 'phone', 'subject', 'message']);
    }

    #[DataProvider('requiredFields')]
    public function test_each_contact_field_is_required(string $field): void
    {
        Mail::fake();
        $this->postJson('/contact', $this->enquiry([$field => '']))->assertUnprocessable()->assertJsonValidationErrors($field);
        Mail::assertNothingSent();
    }

    public static function invalidContacts(): array
    {
        return [
            ['email', 'not-an-email'], ['email', 'admin..bad@example.test'],
            ['phone', '1234567890'], ['phone', '0722ABC456'], ['phone', '+999123456789'], ['phone', '0000000000'], ['phone', '+40722123456 ext 123'],
            ['subject', 'not-approved'], ['name', "Demo\r\nBcc: attacker@example.test"],
        ];
    }

    #[DataProvider('invalidContacts')]
    public function test_invalid_emails_phones_subjects_and_headers_do_not_send_mail(string $field, string $value): void
    {
        Mail::fake();
        $this->postJson('/contact', $this->enquiry([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        Mail::assertNothingSent();
    }

    public function test_validation_feedback_is_translated_and_redirects_to_the_same_language(): void
    {
        Mail::fake();
        $response = $this->postJson('/en/contact', $this->enquiry(['email' => 'wrong', 'phone' => 'wrong']));
        $response->assertUnprocessable()->assertJsonPath('errors.email.0', 'Please enter a valid email address.');
        $this->assertStringContainsString('valid phone number', $response->json('errors.phone.0'));
        $this->post('/en/contact', $this->enquiry(['email' => 'wrong']))->assertRedirect(url('/en').'#contact')->assertSessionHasErrors('email')->assertSessionHasInput('phone', '0722 123 456');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/en');
        $response->assertSee('Please enter a valid email address.')->assertSee('aria-invalid="true"', false);
        Mail::assertNothingSent();
    }

    public function test_honeypot_and_unacknowledged_privacy_are_rejected(): void
    {
        Mail::fake();
        $this->postJson('/contact', $this->enquiry(['website' => 'https://spam.test', 'consent' => '0']))->assertUnprocessable()->assertJsonValidationErrors(['website', 'consent']);
        Mail::assertNothingSent();
    }

    public function test_unavailable_transport_keeps_input_without_claiming_success(): void
    {
        Mail::fake();
        config(['mail.mailers.smtp.host' => '']);
        $this->post('/en/contact', $this->enquiry())->assertRedirect(url('/en').'#contact')->assertSessionHasErrors('delivery')->assertSessionMissing('contact_sent')->assertSessionHasInput('phone', '0722 123 456');
        Mail::assertNothingSent();
    }

    public function test_transport_failure_keeps_all_contact_context(): void
    {
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Transport unavailable'));
        $this->post('/en/contact', $this->enquiry())->assertRedirect(url('/en').'#contact')->assertSessionHasErrors('delivery')->assertSessionMissing('contact_sent')->assertSessionHasInput('phone', '0722 123 456')->assertSessionHasInput('subject', 'pilot');
    }

    public function test_contact_rate_limit_is_shared_between_languages(): void
    {
        Mail::fake();
        for ($i = 0; $i < 5; $i++) $this->postJson('/contact', [])->assertUnprocessable();
        $this->postJson('/en/contact', $this->enquiry())->assertTooManyRequests();
        Mail::assertNothingSent();
    }

    public function test_plain_text_mail_contains_all_fields_without_html_entities(): void
    {
        $mail = new ContactMessage($this->enquiry(['name' => 'Alex & Radu', 'phone' => '+40722123456']) + ['locale' => 'ro']);
        $mail->assertSeeInText('+40722123456');
        $mail->assertSeeInText('Participare la programul pilot');
        $mail->assertSeeInText('Alex & Radu', false);
    }
}
