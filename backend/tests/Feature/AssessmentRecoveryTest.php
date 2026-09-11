<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Target;
use App\Models\User;
use App\Services\AssessmentRecoveryService;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AssessmentRecoveryTest extends TestCase
{
    private array $userIds = [];
    private array $targetIds = [];
    private array $assessmentIds = [];

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
        $user->name = 'Assessment Recovery Test';
        $user->email =
            'assessment-recovery-'
            .Str::uuid()
            .'@example.invalid';
        $user->password = bcrypt(Str::random(64));
        $user->save();

        $this->userIds[] = $user->id;

        return $user;
    }

    private function makeTarget(User $user): Target
    {
        $target = new Target();
        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Assessment Recovery Target';
        $target->url =
            'https://assessment-recovery.invalid/';
        $target->hostname =
            'assessment-recovery.invalid';
        $target->scheme = 'https';
        $target->port = 443;
        $target->authorization_confirmed = true;
        $target->authorization_confirmed_at = now();
        $target->authorization_method =
            'test_fixture';
        $target->status = 'active';
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
        string $status,
        ?int $startedSecondsAgo = null
    ): Assessment {
        $terminal = in_array(
            $status,
            ['completed', 'failed', 'blocked'],
            true
        );

        $assessment = Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => $status,
            'queued_at' => now()->subMinutes(10),
            'started_at' =>
                $startedSecondsAgo !== null
                    ? now()->subSeconds(
                        $startedSecondsAgo
                    )
                    : null,
            'completed_at' =>
                $terminal ? now() : null,
            'worker_id' =>
                $status === 'running'
                    ? 'recovery-test-'.Str::uuid()
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

        $this->assessmentIds[] =
            $assessment->id;

        return $assessment;
    }

    public function test_old_running_assessment_is_reconciled_as_failed(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $assessment = $this->makeAssessment(
            $user,
            $target,
            'running',
            300
        );

        $result = app(
            AssessmentRecoveryService::class
        )->reconcileAbandonedRunning();

        $assessment->refresh();

        $this->assertSame(
            'failed',
            $assessment->status
        );

        $this->assertNotNull(
            $assessment->completed_at
        );

        $this->assertSame(
            'Assessment execution was abandoned before completion.',
            $assessment->error_message
        );

        $this->assertSame(
            'abandoned_running_execution',
            $assessment->execution_metadata[
                'recovery'
            ]['reason']
        );

        $this->assertFalse(
            $assessment->execution_metadata[
                'recovery'
            ]['automatic_retry']
        );

        $this->assertGreaterThanOrEqual(
            1,
            $result['recovered']
        );
    }

    public function test_fresh_running_assessment_is_not_recovered(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $assessment = $this->makeAssessment(
            $user,
            $target,
            'running',
            30
        );

        $originalWorkerId =
            $assessment->worker_id;

        app(
            AssessmentRecoveryService::class
        )->reconcileAbandonedRunning();

        $assessment->refresh();

        $this->assertSame(
            'running',
            $assessment->status
        );

        $this->assertSame(
            $originalWorkerId,
            $assessment->worker_id
        );

        $this->assertNull(
            $assessment->completed_at
        );
    }

    public function test_terminal_queue_failure_reconciles_queued_assessment(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $assessment = $this->makeAssessment(
            $user,
            $target,
            'queued'
        );

        $changed = app(
            AssessmentRecoveryService::class
        )->reconcileTerminalQueueFailure(
            $assessment->id,
            new RuntimeException(
                'test queue exhaustion'
            )
        );

        $assessment->refresh();

        $this->assertTrue($changed);

        $this->assertSame(
            'failed',
            $assessment->status
        );

        $this->assertNotNull(
            $assessment->completed_at
        );

        $this->assertNull(
            $assessment->worker_id
        );

        $this->assertSame(
            'queue_attempts_exhausted',
            $assessment->execution_metadata[
                'recovery'
            ]['reason']
        );

        $this->assertSame(
            RuntimeException::class,
            $assessment->execution_metadata[
                'recovery'
            ]['exception']
        );

        $this->assertFalse(
            $assessment->execution_metadata[
                'recovery'
            ]['automatic_retry']
        );
    }

    public function test_terminal_queue_failure_does_not_rewrite_running_assessment(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $assessment = $this->makeAssessment(
            $user,
            $target,
            'running',
            30
        );

        $originalWorkerId =
            $assessment->worker_id;

        $changed = app(
            AssessmentRecoveryService::class
        )->reconcileTerminalQueueFailure(
            $assessment->id,
            new RuntimeException('test')
        );

        $assessment->refresh();

        $this->assertFalse($changed);

        $this->assertSame(
            'running',
            $assessment->status
        );

        $this->assertSame(
            $originalWorkerId,
            $assessment->worker_id
        );
    }

    public function test_terminal_states_are_never_rewritten_by_queue_failure_recovery(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        foreach (
            ['completed', 'failed', 'blocked']
            as $status
        ) {
            $assessment =
                $this->makeAssessment(
                    $user,
                    $target,
                    $status
                );

            $originalCompletedAt =
                $assessment
                    ->completed_at
                    ?->toISOString();

            $changed = app(
                AssessmentRecoveryService::class
            )->reconcileTerminalQueueFailure(
                $assessment->id,
                new RuntimeException('test')
            );

            $assessment->refresh();

            $this->assertFalse($changed);

            $this->assertSame(
                $status,
                $assessment->status
            );

            $this->assertSame(
                $originalCompletedAt,
                $assessment
                    ->completed_at
                    ?->toISOString()
            );
        }
    }
}
