<?php

namespace Tests\Feature;

use App\Jobs\SendMonitoringNotification;
use App\Models\Assessment;
use App\Models\MonitoringChangeEvent;
use App\Models\MonitoringNotificationDelivery;
use App\Models\MonitoringNotificationPreference;
use App\Models\Target;
use App\Models\User;
use App\Services\MonitoringNotificationPlannerService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringNotificationPlannerTest extends TestCase
{
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }
    private array $targetIds = [];
    private array $assessmentIds = [];
    private array $eventIds = [];

    protected function tearDown(): void
    {
        if ($this->eventIds !== []) {
            MonitoringNotificationDelivery::query()
                ->whereIn('change_event_id', $this->eventIds)
                ->delete();

            MonitoringChangeEvent::query()
                ->whereIn('id', $this->eventIds)
                ->delete();
        }

        if ($this->userIds !== []) {
            MonitoringNotificationPreference::query()
                ->whereIn('user_id', $this->userIds)
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

    private function fixture(): array
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->name = 'Notification Planner Test';
        $user->email =
            'notification-'.Str::uuid().'@example.invalid';
        $user->password = bcrypt(Str::random(64));
        $user->save();

        $this->userIds[] = $user->id;

        $target = new Target();
        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Notification Test Target';
        $target->url = 'https://notification-test.invalid/';
        $target->hostname = 'notification-test.invalid';
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

        return [$user, $target, $assessment];
    }

    private function event(
        User $user,
        Target $target,
        Assessment $assessment,
        string $type,
        array $payload = []
    ): MonitoringChangeEvent {
        $event = MonitoringChangeEvent::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'assessment_id' => $assessment->id,
            'previous_assessment_id' => null,
            'event_type' => $type,
            'fingerprint' => hash(
                'sha256',
                $type.':'.Str::uuid()
            ),
            'payload' => $payload,
            'detected_at' => now(),
        ]);

        $this->eventIds[] = $event->id;

        return $event;
    }

    private function preference(
        User $user,
        bool $emailEnabled,
        array $eventTypes,
        int $minimumRiskDelta = 1
    ): MonitoringNotificationPreference {
        return MonitoringNotificationPreference::query()
            ->create([
                'user_id' => $user->id,
                'email_enabled' => $emailEnabled,
                'event_types' => $eventTypes,
                'minimum_risk_delta' => $minimumRiskDelta,
            ]);
    }

    public function test_default_preferences_create_pending_email_delivery(): void
    {
        [$user, $target, $assessment] = $this->fixture();

        $event = $this->event(
            $user,
            $target,
            $assessment,
            'finding_new',
            [
                'finding' => [
                    'title' => 'Synthetic regression finding',
                    'severity' => 'medium',
                ],
            ]
        );

        $delivery = app(
            MonitoringNotificationPlannerService::class
        )->plan($event);

        $this->assertNotNull($delivery);
        $this->assertSame('email', $delivery->channel);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame($user->id, $delivery->user_id);
        $this->assertSame($event->id, $delivery->change_event_id);
        $this->assertSame($user->email, $delivery->recipient);
        $this->assertSame(0, $delivery->attempt_count);

        $payload = $delivery->payload;

        $this->assertSame(
            $event->id,
            $payload['change_event']['id']
        );

        $this->assertSame(
            'finding_new',
            $payload['change_event']['event_type']
        );

        $this->assertSame(
            $target->id,
            $payload['target']['id']
        );

        $this->assertSame(
            $assessment->id,
            $payload['assessment']['id']
        );

        Queue::assertPushed(
            SendMonitoringNotification::class,
            function (
                SendMonitoringNotification $job
            ) use ($delivery): bool {
                return $job->deliveryId === $delivery->id;
            }
        );

        Queue::assertPushed(
            SendMonitoringNotification::class,
            1
        );
    }

    public function test_planning_same_event_twice_is_idempotent(): void
    {
        [$user, $target, $assessment] = $this->fixture();

        $event = $this->event(
            $user,
            $target,
            $assessment,
            'finding_new'
        );

        $planner = app(
            MonitoringNotificationPlannerService::class
        );

        $first = $planner->plan($event);
        $second = $planner->plan($event);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);

        $this->assertSame(
            1,
            MonitoringNotificationDelivery::query()
                ->where('change_event_id', $event->id)
                ->where('channel', 'email')
                ->count()
        );

        Queue::assertPushed(
            SendMonitoringNotification::class,
            1
        );

        Queue::assertPushed(
            SendMonitoringNotification::class,
            function (
                SendMonitoringNotification $job
            ) use ($first): bool {
                return $job->deliveryId === $first->id;
            }
        );
    }

    public function test_disabled_email_creates_no_delivery(): void
    {
        [$user, $target, $assessment] = $this->fixture();

        $this->preference(
            $user,
            false,
            MonitoringNotificationPreference::defaultEventTypes()
        );

        $event = $this->event(
            $user,
            $target,
            $assessment,
            'finding_new'
        );

        $delivery = app(
            MonitoringNotificationPlannerService::class
        )->plan($event);

        $this->assertNull($delivery);

        $this->assertSame(
            0,
            MonitoringNotificationDelivery::query()
                ->where('change_event_id', $event->id)
                ->count()
        );

        Queue::assertNothingPushed();
    }

    public function test_unsubscribed_event_creates_no_delivery(): void
    {
        [$user, $target, $assessment] = $this->fixture();

        $this->preference(
            $user,
            true,
            ['risk_changed']
        );

        $event = $this->event(
            $user,
            $target,
            $assessment,
            'finding_new'
        );

        $delivery = app(
            MonitoringNotificationPlannerService::class
        )->plan($event);

        $this->assertNull($delivery);

        Queue::assertNothingPushed();
    }

    public function test_risk_change_below_threshold_creates_no_delivery(): void
    {
        [$user, $target, $assessment] = $this->fixture();

        $this->preference(
            $user,
            true,
            ['risk_changed'],
            10
        );

        $event = $this->event(
            $user,
            $target,
            $assessment,
            'risk_changed',
            [
                'risk' => [
                    'delta_points' => 9,
                    'trend' => 'increased',
                ],
            ]
        );

        $delivery = app(
            MonitoringNotificationPlannerService::class
        )->plan($event);

        $this->assertNull($delivery);

        Queue::assertNothingPushed();
    }

    public function test_risk_change_meeting_threshold_creates_delivery(): void
    {
        [$user, $target, $assessment] = $this->fixture();

        $this->preference(
            $user,
            true,
            ['risk_changed'],
            10
        );

        $event = $this->event(
            $user,
            $target,
            $assessment,
            'risk_changed',
            [
                'risk' => [
                    'delta_points' => -10,
                    'trend' => 'decreased',
                ],
            ]
        );

        $delivery = app(
            MonitoringNotificationPlannerService::class
        )->plan($event);

        $this->assertNotNull($delivery);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame($event->id, $delivery->change_event_id);

        Queue::assertPushed(
            SendMonitoringNotification::class,
            function (
                SendMonitoringNotification $job
            ) use ($delivery): bool {
                return $job->deliveryId === $delivery->id;
            }
        );

        Queue::assertPushed(
            SendMonitoringNotification::class,
            1
        );
    }
}
