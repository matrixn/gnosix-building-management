<?php

namespace Tests\Feature;

use App\Mail\PilotEnquiry;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class PilotEnquiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'gnosix.pilot_enabled' => true,
            'gnosix.pilot_recipient' => 'raduta.alex@gmail.com',
            'mail.default' => 'smtp',
            'mail.from.address' => 'pilot@example.test',
        ]);
    }

    private function enquiry(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Administrator Demo',
            'email' => 'admin@example.test',
            'property' => 'hotel',
            'message' => 'Dorim să organizăm istoricul echipamentelor.',
            'consent' => '1',
            'website' => '',
        ], $overrides);
    }

    public function test_valid_enquiry_is_sent_only_to_the_configured_recipient(): void
    {
        Mail::fake();
        $this->post('/program-pilot', $this->enquiry())->assertRedirect(url('/').'#pilot')
            ->assertSessionHas('pilot_sent', true)->assertSessionHasNoErrors();
        Mail::assertSent(PilotEnquiry::class, fn ($mail) => $mail->hasTo('raduta.alex@gmail.com')
            && $mail->envelope()->replyTo[0]->address === 'admin@example.test'
            && ! array_key_exists('consent', $mail->enquiry));
        Mail::assertSentCount(1);
    }

    public function test_invalid_input_and_missing_acknowledgement_do_not_send_mail(): void
    {
        Mail::fake();
        $this->postJson('/program-pilot', $this->enquiry(['email' => 'invalid', 'property' => 'unknown', 'message' => 'x', 'consent' => '0']))
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'property', 'message', 'consent']);
        Mail::assertNothingSent();
    }

    public function test_honeypot_and_header_injection_are_rejected(): void
    {
        Mail::fake();
        $this->postJson('/program-pilot', $this->enquiry(['website' => 'https://spam.test', 'name' => "Demo\r\nBcc: visitor@example.test"]))
            ->assertUnprocessable()->assertJsonValidationErrors(['website', 'name']);
        Mail::assertNothingSent();
    }

    public function test_log_mailer_cannot_report_a_successful_delivery(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $this->post('/program-pilot', $this->enquiry())->assertSessionHasErrors('delivery')->assertSessionMissing('pilot_sent');
        Mail::assertNothingSent();
    }

    public function test_transport_failure_keeps_input_without_claiming_success(): void
    {
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Transport unavailable'));
        $this->post('/program-pilot', $this->enquiry())->assertSessionHasErrors('delivery')
            ->assertSessionHasInput('email', 'admin@example.test')->assertSessionMissing('pilot_sent');
    }

    public function test_repeated_submissions_are_throttled(): void
    {
        Mail::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/program-pilot', [])->assertUnprocessable();
        }
        $this->postJson('/program-pilot', $this->enquiry())->assertTooManyRequests();
        Mail::assertNothingSent();
    }

    public function test_mailable_renders_the_requested_context(): void
    {
        $mail = new PilotEnquiry($this->enquiry());
        $mail->assertSeeInText('Hotel / pensiune');
        $mail->assertSeeInText('istoricul echipamentelor');
    }
}
