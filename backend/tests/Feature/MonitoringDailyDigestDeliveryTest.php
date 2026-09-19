<?php

namespace Tests\Feature;

use App\Jobs\SendMonitoringDailyDigest;
use App\Models\MonitoringDailyDigestDelivery;
use App\Models\User;
use App\Notifications\MonitoringDailyDigestEmailNotification;
use App\Services\MonitoringMailTransportGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MonitoringDailyDigestDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function delivery(
        User $user,
        array $overrides = [],
    ): MonitoringDailyDigestDelivery {
        return MonitoringDailyDigestDelivery::query()->create(
            array_merge([
                'user_id' => $user->id,
                'digest_date' => '2026-09-17',
                'timezone' => 'UTC',
                'recipient' => $user->email,
                'status' => 'pending',
                'attempt_count' => 0,
                'payload' => [
                    'version' => 1,
                    'digest_date' => '2026-09-17',
                    'timezone' => 'UTC',
                    'totals' => [
                        'monitored_targets' => 1,
                        'completed_scans' => 1,
                        'failed_scans' => 0,
                        'incomplete_scans' => 0,
                        'change_events' => 1,
                        'high_findings_detected' => 1,
                        'critical_findings_detected' => 0,
                    ],
                    'targets' => [
                        [
                            'name' => 'example.test',
                            'hostname' => 'example.test',
                            'scan_summary' => [
                                'completed' => 1,
                                'failed' => 0,
                                'incomplete' => 0,
                            ],
                            'severity_events' => [
                                'high' => 1,
                                'critical' => 0,
                            ],
                        ],
                    ],
                    'truthfulness' => [
                        'no_findings_does_not_mean_safe' =>
                            true,
                    ],
                ],
            ], $overrides)
        );
    }

    public function test_pending_digest_is_sent_once_and_marked_sent(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'digest@example.test',
        ]);

        $delivery = $this->delivery($user);

        $guard = $this->mock(
            MonitoringMailTransportGuard::class
        );

        $guard->shouldReceive('assertDeliveryCapable')
            ->once();

        (new SendMonitoringDailyDigest(
            $delivery->id
        ))->handle($guard);

        $delivery->refresh();

        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->failure_class);

        Notification::assertSentOnDemand(
            MonitoringDailyDigestEmailNotification::class,
            function (
                MonitoringDailyDigestEmailNotification $notification,
                array $channels,
                object $notifiable
            ): bool {
                return in_array('mail', $channels, true)
                    && $notifiable->routes['mail']
                        === 'digest@example.test';
            }
        );
    }

    public function test_sent_digest_is_not_sent_again(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'already@example.test',
        ]);

        $delivery = $this->delivery($user, [
            'status' => 'sent',
            'attempt_count' => 1,
            'sent_at' => now(),
        ]);

        $guard = $this->mock(
            MonitoringMailTransportGuard::class
        );

        $guard->shouldNotReceive(
            'assertDeliveryCapable'
        );

        (new SendMonitoringDailyDigest(
            $delivery->id
        ))->handle($guard);

        Notification::assertNothingSent();

        $this->assertSame(
            1,
            $delivery->fresh()->attempt_count
        );
    }

    public function test_invalid_recipient_is_failed_without_mail(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $delivery = $this->delivery($user, [
            'recipient' => 'not-an-email',
        ]);

        $guard = $this->mock(
            MonitoringMailTransportGuard::class
        );

        $guard->shouldNotReceive(
            'assertDeliveryCapable'
        );

        (new SendMonitoringDailyDigest(
            $delivery->id
        ))->handle($guard);

        $delivery->refresh();

        $this->assertSame('failed', $delivery->status);
        $this->assertSame(
            'invalid_recipient',
            $delivery->failure_class
        );

        Notification::assertNothingSent();
    }

    public function test_email_content_preserves_truthful_no_scan_language(): void
    {
        $notification =
            new MonitoringDailyDigestEmailNotification([
                'digest_date' => '2026-09-17',
                'timezone' => 'UTC',
                'totals' => [
                    'completed_scans' => 0,
                    'failed_scans' => 1,
                    'incomplete_scans' => 0,
                    'change_events' => 0,
                    'high_findings_detected' => 0,
                    'critical_findings_detected' => 0,
                ],
                'targets' => [],
            ]);

        $mail = $notification->toMail(
            new \stdClass()
        );

        $text = strtolower(
            implode(
                ' ',
                array_map(
                    fn ($line) => is_string($line)
                        ? $line
                        : '',
                    $mail->introLines
                )
            )
        );

        $this->assertStringContainsString(
            'no security conclusion can be drawn',
            $text
        );

        $this->assertStringNotContainsString(
            'site is safe',
            $text
        );

        $this->assertStringNotContainsString(
            'site is secure',
            $text
        );
    }

    public function test_no_change_email_does_not_claim_security(): void
    {
        $notification =
            new MonitoringDailyDigestEmailNotification([
                'digest_date' => '2026-09-17',
                'timezone' => 'UTC',
                'totals' => [
                    'completed_scans' => 2,
                    'failed_scans' => 0,
                    'incomplete_scans' => 0,
                    'change_events' => 0,
                    'high_findings_detected' => 0,
                    'critical_findings_detected' => 0,
                ],
                'targets' => [],
            ]);

        $mail = $notification->toMail(
            new \stdClass()
        );

        $text = strtolower(
            implode(
                ' ',
                array_map(
                    fn ($line) => is_string($line)
                        ? $line
                        : '',
                    $mail->introLines
                )
            )
        );

        $this->assertStringContainsString(
            'does not prove',
            $text
        );

        $this->assertStringContainsString(
            'completed monitoring scans',
            $text
        );
    }
}
