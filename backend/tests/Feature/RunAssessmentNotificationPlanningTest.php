<?php

namespace Tests\Feature;

use App\Jobs\RunAssessment;
use App\Models\Assessment;
use App\Models\MonitoringChangeEvent;
use App\Models\Target;
use App\Models\User;
use App\Services\ChangeDetectionService;
use App\Services\FindingLifecycleService;
use App\Services\HttpAssessmentService;
use App\Services\MonitoringNotificationPlannerService;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RunAssessmentNotificationPlanningTest extends TestCase
{
    private array $userIds = [];
    private array $targetIds = [];
    private array $assessmentIds = [];
    private array $eventIds = [];

    protected function tearDown(): void
    {
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

    private function fixture(): array
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->name = 'Notification Integration Test';
        $user->email =
            'notification-integration-'
            .Str::uuid()
            .'@example.invalid';
        $user->password = bcrypt(Str::random(64));
        $user->save();

        $this->userIds[] = $user->id;

        $target = new Target();
        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Notification Integration Target';
        $target->url =
            'https://notification-integration.invalid/';
        $target->hostname =
            'notification-integration.invalid';
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
            'status' => 'queued',
            'queued_at' => now(),
            'progress' => 0,
            'configuration' => [],
            'execution_metadata' => [
                'dispatch_source' => 'monitoring',
                'test_fixture' => true,
            ],
        ]);

        $this->assessmentIds[] = $assessment->id;

        return [$user, $target, $assessment];
    }

    private function scannerMock(): void
    {
        $scanner = $this->mock(
            HttpAssessmentService::class
        );

        $scanner->shouldReceive('run')
            ->once()
            ->andReturn([
                'engine_version' =>
                    'http-assessment-v3',
                'scanner_policy' => [
                    'authorization_scope' =>
                        'single_target',
                ],
                'finding_identity' => [
                    'version' => 1,
                    'algorithm' => 'sha256',
                ],
                'http_status' => 200,
                'successful' => true,
                'duration_ms' => 1,
                'content_type' => 'text/html',
                'server' => null,
                'powered_by' => null,
                'redirect' => null,
                'redirect_chain' => [],
                'redirect_hops' => 0,
                'resolved_ips' => ['192.0.2.1'],
                'tls' => [
                    'enabled' => true,
                    'certificate' => null,
                ],
                'headers' => [],
                'finding_count' => 0,
                'checked_at' => now()->toIso8601String(),
            ]);
    }

    private function lifecycleMock(): void
    {
        $lifecycle = $this->mock(
            FindingLifecycleService::class
        );

        $lifecycle->shouldReceive('syncAssessment')
            ->once()
            ->andReturn([
                'created' => 0,
                'updated' => 0,
                'reopened' => 0,
                'resolved' => 0,
                'no_longer_detected' => 0,
                'semantics' => [
                    'absence_means' =>
                        'no_longer_detected',
                    'absence_does_not_prove' =>
                        'resolved',
                    'resolved_requires_explicit_workflow'
                        => true,
                ],
            ]);
    }

    private function createEvent(
        User $user,
        Target $target,
        Assessment $assessment
    ): MonitoringChangeEvent {
        $event = MonitoringChangeEvent::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'assessment_id' => $assessment->id,
            'previous_assessment_id' => null,
            'event_type' => 'finding_new',
            'fingerprint' => hash(
                'sha256',
                'integration:'.Str::uuid()
            ),
            'payload' => [
                'test_fixture' => true,
            ],
            'detected_at' => now(),
        ]);

        $this->eventIds[] = $event->id;

        return $event;
    }

    public function test_persisted_event_ids_are_sent_to_planner(): void
    {
        [$user, $target, $assessment] =
            $this->fixture();

        $this->scannerMock();
        $this->lifecycleMock();

        /*
         * The event must already exist before detect() returns its ID.
         * This models ChangeDetectionService's real persistence
         * contract without performing network activity.
         */
        $event = $this->createEvent(
            $user,
            $target,
            $assessment
        );

        $changes = $this->mock(
            ChangeDetectionService::class
        );

        $changes->shouldReceive('detect')
            ->once()
            ->andReturn([
                'available' => true,
                'assessment_id' => $assessment->id,
                'previous_assessment_id' => null,
                'events_created' => 1,
                'events_existing' => 0,
                'event_ids' => [$event->id],
                'event_types' => [
                    'finding_new' => 1,
                ],
                'risk' => [
                    'delta_points' => 0,
                    'trend' => 'unchanged',
                    'historical_snapshot' => false,
                ],
            ]);

        $planner = Mockery::mock(
            MonitoringNotificationPlannerService::class
        );

        $planner->shouldReceive('plan')
            ->once()
            ->withArgs(
                fn (MonitoringChangeEvent $given): bool =>
                    $given->id === $event->id
                    && $given->assessment_id
                        === $assessment->id
            )
            /*
             * A non-null return means an eligible delivery
             * was planned. We only need an object here because
             * RunAssessment does not inspect its fields.
             */
            ->andReturn(
                Mockery::mock(
                    \App\Models\MonitoringNotificationDelivery::class
                )
            );

        app()->instance(
            MonitoringNotificationPlannerService::class,
            $planner
        );

        app()->call(
            [new RunAssessment($assessment->id), 'handle']
        );

        $assessment->refresh();

        $this->assertSame(
            'completed',
            $assessment->status
        );

        $metadata = $assessment->execution_metadata;

        $this->assertSame(
            [$event->id],
            $metadata['change_detection']['event_ids']
        );

        $this->assertSame(
            1,
            $metadata['notification_planning'][
                'eligible_events'
            ]
        );

        $this->assertSame(
            1,
            $metadata['notification_planning'][
                'deliveries_planned'
            ]
        );

        $this->assertSame(
            0,
            $metadata['notification_planning'][
                'failed_events'
            ]
        );
    }

    public function test_planner_failure_does_not_fail_completed_assessment(): void
    {
        [$user, $target, $assessment] =
            $this->fixture();

        $this->scannerMock();
        $this->lifecycleMock();

        $event = $this->createEvent(
            $user,
            $target,
            $assessment
        );

        $changes = $this->mock(
            ChangeDetectionService::class
        );

        $changes->shouldReceive('detect')
            ->once()
            ->andReturn([
                'available' => true,
                'assessment_id' => $assessment->id,
                'previous_assessment_id' => null,
                'events_created' => 1,
                'events_existing' => 0,
                'event_ids' => [$event->id],
                'event_types' => [
                    'finding_new' => 1,
                ],
                'risk' => [
                    'delta_points' => 0,
                    'trend' => 'unchanged',
                    'historical_snapshot' => false,
                ],
            ]);

        $planner = Mockery::mock(
            MonitoringNotificationPlannerService::class
        );

        $planner->shouldReceive('plan')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Synthetic planner failure'
                )
            );

        app()->instance(
            MonitoringNotificationPlannerService::class,
            $planner
        );

        app()->call(
            [new RunAssessment($assessment->id), 'handle']
        );

        $assessment->refresh();

        $this->assertSame(
            'completed',
            $assessment->status
        );

        $this->assertSame(
            100,
            $assessment->progress
        );

        $this->assertNull(
            $assessment->error_message
        );

        $metadata = $assessment->execution_metadata;

        $this->assertSame(
            [$event->id],
            $metadata['change_detection']['event_ids']
        );

        $this->assertSame(
            1,
            $metadata['notification_planning'][
                'eligible_events'
            ]
        );

        $this->assertSame(
            0,
            $metadata['notification_planning'][
                'deliveries_planned'
            ]
        );

        $this->assertSame(
            1,
            $metadata['notification_planning'][
                'failed_events'
            ]
        );

        $this->assertDatabaseHas(
            'monitoring_change_events',
            [
                'id' => $event->id,
                'assessment_id' => $assessment->id,
            ]
        );
    }
}
