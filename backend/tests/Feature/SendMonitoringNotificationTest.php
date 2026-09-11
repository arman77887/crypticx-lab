<?php

namespace Tests\Feature;

use App\Jobs\SendMonitoringNotification;
use App\Models\Assessment;
use App\Models\MonitoringChangeEvent;
use App\Models\MonitoringNotificationDelivery;
use App\Models\Target;
use App\Models\User;
use App\Notifications\MonitoringChangeEmailNotification;
use App\Services\MonitoringMailTransportGuard;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SendMonitoringNotificationTest extends TestCase
{
    private array $userIds = [];
    private array $targetIds = [];
    private array $assessmentIds = [];
    private array $eventIds = [];
    private array $deliveryIds = [];

    protected function tearDown(): void
    {
        if ($this->deliveryIds !== []) {
            MonitoringNotificationDelivery::query()
                ->whereIn('id', $this->deliveryIds)
                ->delete();
        }

        if ($this->eventIds !== []) {
            MonitoringChangeEvent::query()
                ->whereIn('id', $this->eventIds)
                ->delete();
        }

        if ($this->assessmentIds !== []) {
            Assessment::query()
                ->whereIn('id', $this->assessmentIds)
                ->delete();
        }

        if ($this->targetIds !== []) {
            Target::query()
                ->whereIn('id', $this->targetIds)
                ->delete();
        }

        if ($this->userIds !== []) {
            User::query()
                ->whereIn('id', $this->userIds)
                ->delete();
        }

        parent::tearDown();
    }

    private function delivery(
        string $status = 'pending',
        int $attemptCount = 0
    ): MonitoringNotificationDelivery {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->name = 'Sender Regression Test';
        $user->email =
            'sender-'.Str::uuid().'@example.invalid';
        $user->password = bcrypt(Str::random(64));
        $user->save();

        $this->userIds[] = $user->id;

        $target = new Target();
        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Sender Test Target';
        $target->url = 'https://sender-test.invalid/';
        $target->hostname = 'sender-test.invalid';
        $target->scheme = 'https';
        $target->port = 443;
        $target->authorization_confirmed = true;
        $target->authorization_confirmed_at = now();
        $target->authorization_method = 'test_fixture';
        $target->status = 'active';
        $target->metadata = ['test_fixture' => true];
        $target->save();

        $this->targetIds[] = $target->id;

        $assessment = Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => 'completed',
            'queued_at' => now()->subMinute(),
            'started_at' => now()->subSeconds(30),
            'completed_at' => now(),
            'progress' => 100,
            'configuration' => [],
            'execution_metadata' => [
                'dispatch_source' => 'monitoring',
                'test_fixture' => true,
            ],
        ]);

        $this->assessmentIds[] = $assessment->id;

        $event = MonitoringChangeEvent::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'assessment_id' => $assessment->id,
            'previous_assessment_id' => null,
            'event_type' => 'finding_new',
            'fingerprint' => hash(
                'sha256',
                'sender:'.Str::uuid()
            ),
            'payload' => [
                'finding' => [
                    'title' => 'Synthetic sender test finding',
                    'severity' => 'medium',
                ],
            ],
            'detected_at' => now(),
        ]);

        $this->eventIds[] = $event->id;

        $delivery =
            MonitoringNotificationDelivery::query()
                ->create([
                    'user_id' => $user->id,
                    'change_event_id' => $event->id,
                    'channel' => 'email',
                    'status' => $status,
                    'recipient' => $user->email,
                    'payload' => [
                        'version' => 1,
                        'change_event' => [
                            'id' => $event->id,
                            'event_type' => 'finding_new',
                            'fingerprint' =>
                                $event->fingerprint,
                            'detected_at' =>
                                $event->detected_at
                                    ->toIso8601String(),
                            'payload' => $event->payload,
                        ],
                        'target' => [
                            'id' => $target->id,
                            'name' => $target->name,
                            'url' => $target->url,
                            'hostname' =>
                                $target->hostname,
                        ],
                        'assessment' => [
                            'id' => $assessment->id,
                            'profile' =>
                                $assessment->profile,
                            'status' =>
                                $assessment->status,
                            'completed_at' =>
                                $assessment->completed_at
                                    ->toIso8601String(),
                        ],
                    ],
                    'attempt_count' => $attemptCount,
                ]);

        $this->deliveryIds[] = $delivery->id;

        return $delivery;
    }

    public function test_successful_handoff_marks_delivery_sent(): void
    {
        Notification::fake();

        $delivery = $this->delivery();

        /*
         * The sender state machine is under test here, not
         * external SMTP connectivity. A delivery-capable guard
         * is therefore mocked explicitly.
         */
        $guard = $this->mock(
            MonitoringMailTransportGuard::class
        );

        $guard->shouldReceive('assertDeliveryCapable')
            ->once();

        app()->call([
            new SendMonitoringNotification($delivery->id),
            'handle',
        ]);

        $delivery->refresh();

        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->processing_at);
        $this->assertNull($delivery->failed_at);
        $this->assertNull($delivery->failure_class);

        Notification::assertSentOnDemand(
            MonitoringChangeEmailNotification::class,
            function (
                MonitoringChangeEmailNotification $notification,
                array $channels,
                object $notifiable
            ) use ($delivery): bool {
                return
                    in_array('mail', $channels, true)
                    && $notifiable->routeNotificationFor(
                        'mail'
                    ) === $delivery->recipient;
            }
        );
    }

    public function test_log_transport_is_failed_not_sent(): void
    {
        config([
            'mail.default' => 'log',
            'mail.mailers.log' => [
                'transport' => 'log',
            ],
        ]);

        $delivery = $this->delivery();

        try {
            app()->call([
                new SendMonitoringNotification($delivery->id),
                'handle',
            ]);

            $this->fail(
                'Expected non-delivery transport exception.'
            );
        } catch (RuntimeException) {
            // Expected: the job must rethrow for queue retry.
        }

        $delivery->refresh();

        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertNull($delivery->sent_at);
        $this->assertNull($delivery->processing_at);
        $this->assertNotNull($delivery->failed_at);

        $this->assertSame(
            RuntimeException::class,
            $delivery->failure_class
        );
    }

    public function test_failed_delivery_can_retry_and_succeed(): void
    {
        Notification::fake();

        $delivery = $this->delivery(
            status: 'failed',
            attemptCount: 1
        );

        $guard = $this->mock(
            MonitoringMailTransportGuard::class
        );

        $guard->shouldReceive('assertDeliveryCapable')
            ->once();

        app()->call([
            new SendMonitoringNotification($delivery->id),
            'handle',
        ]);

        $delivery->refresh();

        $this->assertSame('sent', $delivery->status);
        $this->assertSame(2, $delivery->attempt_count);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->failed_at);
        $this->assertNull($delivery->failure_class);

        Notification::assertSentOnDemand(
            MonitoringChangeEmailNotification::class
        );
    }

    public function test_already_sent_delivery_is_not_sent_again(): void
    {
        Notification::fake();

        $delivery = $this->delivery(
            status: 'sent',
            attemptCount: 1
        );

        $delivery->update([
            'sent_at' => now(),
        ]);

        $guard = $this->mock(
            MonitoringMailTransportGuard::class
        );

        $guard->shouldNotReceive(
            'assertDeliveryCapable'
        );

        app()->call([
            new SendMonitoringNotification($delivery->id),
            'handle',
        ]);

        $delivery->refresh();

        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);

        Notification::assertNothingSent();
    }
}
