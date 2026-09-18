<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'contact.recipient' => 'contact@example.test',
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Security Researcher',
            'email' => 'researcher@example.test',
            'category' => 'security',
            'subject' => 'Responsible disclosure',
            'message' => 'I would like to report a security issue responsibly.',
            'website' => '',
        ], $overrides);
    }

    public function test_valid_contact_submission_sends_mail(): void
    {
        Mail::fake();

        $response = $this->postJson(
            '/api/v1/contact',
            $this->validPayload()
        );

        $response
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Your message has been sent successfully.',
            ]);

        Mail::assertSent(
            ContactMessageMail::class,
            function (ContactMessageMail $mail): bool {
                $mail->build();

                return $mail->hasTo('contact@example.test')
                    && $mail->hasReplyTo(
                        'researcher@example.test',
                        'Security Researcher'
                    )
                    && $mail->contactData['category'] === 'security'
                    && ! array_key_exists('website', $mail->contactData);
            }
        );
    }

    public function test_invalid_contact_payload_is_rejected(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/contact', [
            'name' => 'A',
            'email' => 'not-an-email',
            'category' => 'invalid',
            'subject' => 'x',
            'message' => 'short',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'email',
                'category',
                'subject',
                'message',
            ]);

        Mail::assertNothingSent();
    }

    public function test_honeypot_field_rejects_bot_submission(): void
    {
        Mail::fake();

        $response = $this->postJson(
            '/api/v1/contact',
            $this->validPayload([
                'website' => 'https://spam.example',
            ])
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['website']);

        Mail::assertNothingSent();
    }

    public function test_missing_contact_recipient_returns_service_unavailable(): void
    {
        Mail::fake();

        config(['contact.recipient' => null]);

        $response = $this->postJson(
            '/api/v1/contact',
            $this->validPayload()
        );

        $response
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Contact service is temporarily unavailable.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_contact_endpoint_is_rate_limited_by_ip(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(
                '/api/v1/contact',
                $this->validPayload([
                    'subject' => 'Rate limit test '.$i,
                ]),
                ['REMOTE_ADDR' => '203.0.113.25']
            )->assertCreated();
        }

        $this->postJson(
            '/api/v1/contact',
            $this->validPayload([
                'subject' => 'Rate limit blocked',
            ]),
            ['REMOTE_ADDR' => '203.0.113.25']
        )->assertStatus(429);

        Mail::assertSent(ContactMessageMail::class, 5);
    }
}
