<?php

namespace Tests\Feature;

use App\Mail\NewRegistrationAdminMail;
use App\Mail\RegistrationApprovedMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_user_and_admin_notifications(): void
    {
        Mail::fake();

        config([
            'contact.recipient' => 'admin@crypticx.test',
        ]);

        Role::query()->firstOrCreate(
            ['slug' => 'security-researcher'],
            ['name' => 'Security Researcher']
        );

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Registration Test User',
            'email' => 'registration-test@example.com',
            'password' => 'StrongTest!12345',
            'password_confirmation' => 'StrongTest!12345',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.approval_required', false);

        $this->assertDatabaseHas('users', [
            'email' => 'registration-test@example.com',
        ]);

        Mail::assertSent(
            RegistrationApprovedMail::class,
            fn (RegistrationApprovedMail $mail) =>
                $mail->hasTo('registration-test@example.com')
        );

        Mail::assertSent(
            NewRegistrationAdminMail::class,
            fn (NewRegistrationAdminMail $mail) =>
                $mail->hasTo('admin@crypticx.test')
        );

        Mail::assertSentCount(2);
    }

    public function test_registration_still_succeeds_without_admin_recipient(): void
    {
        Mail::fake();

        config([
            'contact.recipient' => null,
        ]);

        Role::query()->firstOrCreate(
            ['slug' => 'security-researcher'],
            ['name' => 'Security Researcher']
        );

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'No Admin Recipient',
            'email' => 'no-admin@example.com',
            'password' => 'StrongTest!12345',
            'password_confirmation' => 'StrongTest!12345',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.approval_required', false);

        Mail::assertSent(
            RegistrationApprovedMail::class,
            fn (RegistrationApprovedMail $mail) =>
                $mail->hasTo('no-admin@example.com')
        );

        Mail::assertNotSent(NewRegistrationAdminMail::class);
    }
}
