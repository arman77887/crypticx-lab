<?php

namespace Tests\Feature;

use App\Jobs\RunAssessment;
use App\Models\Assessment;
use App\Models\Target;
use App\Models\User;
use App\Services\AssessmentConcurrencyService;
use App\Services\ChangeDetectionService;
use App\Services\FindingLifecycleService;
use App\Services\HttpAssessmentService;
use App\Services\MonitoringNotificationPlannerService;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunAssessmentExecutionGuardTest extends TestCase
{
    private array $userIds = [];
    private array $targetIds = [];
    private array $assessmentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $concurrency = $this->mock(
            AssessmentConcurrencyService::class
        );

        $concurrency->shouldReceive('hasCapacity')
            ->zeroOrMoreTimes()
            ->andReturnTrue();
    }

    protected function tearDown(): void
    {
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

    private function makeUser(): User
    {
        $user = new User();

        $user->id = (string) Str::uuid();
        $user->name = 'Execution Guard Test';
        $user->email =
            'execution-guard-'.Str::uuid().'@example.invalid';
        $user->password = bcrypt(Str::random(64));
        $user->save();

        $this->userIds[] = $user->id;

        return $user;
    }

    private function makeTarget(
        User $user,
        bool $authorized = true,
        string $status = 'active',
    ): Target {
        $target = new Target();

        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Execution Guard Target';
        $target->url = 'https://execution-guard.invalid/';
        $target->hostname = 'execution-guard.invalid';
        $target->scheme = 'https';
        $target->port = 443;
        $target->authorization_confirmed = $authorized;
        $target->authorization_confirmed_at =
            $authorized ? now() : null;
        $target->authorization_method =
            $authorized ? 'test_fixture' : null;
        $target->status = $status;
        $target->metadata = [
            'test_fixture' => true,
        ];

        $target->save();

        $this->targetIds[] = $target->id;

        return $target;
    }

    private function makeAssessment(
        User $user,
        Target $target,
        string $status = 'queued',
    ): Assessment {
        $assessment = Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => $status,
            'queued_at' => now(),
            'started_at' =>
                $status === 'running' ? now() : null,
            'completed_at' =>
                in_array(
                    $status,
                    ['completed', 'failed', 'blocked'],
                    true
                )
                    ? now()
                    : null,
            'progress' => match ($status) {
                'running' => 20,
                'completed' => 100,
                default => 0,
            },
            'configuration' => [],
            'execution_metadata' => [
                'dispatch_source' => 'manual',
                'test_fixture' => true,
            ],
        ]);

        $this->assessmentIds[] = $assessment->id;

        return $assessment;
    }

    private function scannerResult(): array
    {
        return [
            'engine_version' => 'http-assessment-v3',
            'successful' => true,
            'finding_count' => 0,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function lifecycleResult(): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'reopened' => 0,
            'resolved' => 0,
            'no_longer_detected' => 0,
        ];
    }

    private function expectNoExecution(): void
    {
        $scanner = $this->mock(
            HttpAssessmentService::class
        );
        $scanner->shouldNotReceive('run');

        $lifecycle = $this->mock(
            FindingLifecycleService::class
        );
        $lifecycle->shouldNotReceive('syncAssessment');

        $changes = $this->mock(
            ChangeDetectionService::class
        );
        $changes->shouldNotReceive('detect');

        $notifications = $this->mock(
            MonitoringNotificationPlannerService::class
        );
        $notifications->shouldNotReceive('plan');
    }

    private function runJob(Assessment $assessment): void
    {
        app()->call(
            [new RunAssessment($assessment->id), 'handle']
        );
    }

    public function test_valid_queued_assessment_executes_once(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $assessment = $this->makeAssessment(
            $user,
            $target
        );

        $scanner = $this->mock(
            HttpAssessmentService::class
        );

        $scanner->shouldReceive('run')
            ->once()
            ->withArgs(
                fn (Assessment $given): bool =>
                    $given->id === $assessment->id &&
                    $given->status === 'running'
            )
            ->andReturn($this->scannerResult());

        $lifecycle = $this->mock(
            FindingLifecycleService::class
        );

        $lifecycle->shouldReceive('syncAssessment')
            ->once()
            ->andReturn($this->lifecycleResult());

        $changes = $this->mock(
            ChangeDetectionService::class
        );
        $changes->shouldNotReceive('detect');

        $this->runJob($assessment);

        $assessment->refresh();

        $this->assertSame(
            'completed',
            $assessment->status
        );
        $this->assertSame(100, $assessment->progress);
        $this->assertNotNull($assessment->started_at);
        $this->assertNotNull($assessment->completed_at);
        $this->assertNotNull($assessment->worker_id);
        $this->assertNull($assessment->error_message);
    }

    public function test_running_assessment_is_not_executed_again(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $assessment = $this->makeAssessment(
            $user,
            $target,
            'running'
        );

        $originalStartedAt =
            $assessment->started_at?->toISOString();

        $this->expectNoExecution();
        $this->runJob($assessment);

        $assessment->refresh();

        $this->assertSame('running', $assessment->status);
        $this->assertSame(20, $assessment->progress);
        $this->assertSame(
            $originalStartedAt,
            $assessment->started_at?->toISOString()
        );
    }

    public function test_completed_assessment_is_not_executed_again(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $assessment = $this->makeAssessment(
            $user,
            $target,
            'completed'
        );

        $completedAt =
            $assessment->completed_at?->toISOString();

        $this->expectNoExecution();
        $this->runJob($assessment);

        $assessment->refresh();

        $this->assertSame(
            'completed',
            $assessment->status
        );
        $this->assertSame(100, $assessment->progress);
        $this->assertSame(
            $completedAt,
            $assessment->completed_at?->toISOString()
        );
    }

    public function test_duplicate_delivery_becomes_no_op(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $assessment = $this->makeAssessment(
            $user,
            $target
        );

        $scanner = $this->mock(
            HttpAssessmentService::class
        );

        $scanner->shouldReceive('run')
            ->once()
            ->andReturn($this->scannerResult());

        $lifecycle = $this->mock(
            FindingLifecycleService::class
        );

        $lifecycle->shouldReceive('syncAssessment')
            ->once()
            ->andReturn($this->lifecycleResult());

        $changes = $this->mock(
            ChangeDetectionService::class
        );
        $changes->shouldNotReceive('detect');

        $job = new RunAssessment($assessment->id);

        app()->call([$job, 'handle']);

        $assessment->refresh();

        $firstWorkerId = $assessment->worker_id;
        $firstCompletedAt =
            $assessment->completed_at?->toISOString();

        /*
         * Same queue payload delivered a second time.
         * The scanner expectation remains exactly once.
         */
        app()->call([$job, 'handle']);

        $assessment->refresh();

        $this->assertSame(
            'completed',
            $assessment->status
        );
        $this->assertSame(
            $firstWorkerId,
            $assessment->worker_id
        );
        $this->assertSame(
            $firstCompletedAt,
            $assessment->completed_at?->toISOString()
        );
    }

    public function test_revoked_authorization_blocks_before_execution(): void
    {
        $user = $this->makeUser();

        $target = $this->makeTarget(
            $user,
            authorized: false
        );

        $assessment = $this->makeAssessment(
            $user,
            $target
        );

        $this->expectNoExecution();
        $this->runJob($assessment);

        $assessment->refresh();

        $this->assertSame('blocked', $assessment->status);
        $this->assertSame(0, $assessment->progress);
        $this->assertNull($assessment->worker_id);
        $this->assertNotNull($assessment->completed_at);
        $this->assertSame(
            'Target is not authorized or active.',
            $assessment->error_message
        );
    }

    public function test_inactive_target_blocks_before_execution(): void
    {
        $user = $this->makeUser();

        $target = $this->makeTarget(
            $user,
            authorized: true,
            status: 'inactive'
        );

        $assessment = $this->makeAssessment(
            $user,
            $target
        );

        $this->expectNoExecution();
        $this->runJob($assessment);

        $assessment->refresh();

        $this->assertSame('blocked', $assessment->status);
        $this->assertSame(0, $assessment->progress);
        $this->assertNull($assessment->worker_id);
        $this->assertNotNull($assessment->completed_at);
    }

    public function test_capacity_full_keeps_assessment_queued_without_execution(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $assessment = $this->makeAssessment(
            $user,
            $target
        );

        /*
         * Override the default admission mock for this test only.
         */
        $this->forgetMock(
            AssessmentConcurrencyService::class
        );

        $concurrency = $this->mock(
            AssessmentConcurrencyService::class
        );

        $concurrency->shouldReceive('hasCapacity')
            ->once()
            ->andReturnFalse();

        $this->expectNoExecution();

        $job = new RunAssessment($assessment->id);

        /*
         * Unit-style invocation has no real queue job attached.
         * Queueable/InteractsWithQueue therefore uses its internal
         * fake job object for release().
         */
        app()->call([$job, 'handle']);

        $assessment->refresh();

        $this->assertSame('queued', $assessment->status);
        $this->assertSame(0, $assessment->progress);
        $this->assertNull($assessment->started_at);
        $this->assertNull($assessment->completed_at);
        $this->assertNull($assessment->worker_id);
        $this->assertNull($assessment->error_message);
    }

}
