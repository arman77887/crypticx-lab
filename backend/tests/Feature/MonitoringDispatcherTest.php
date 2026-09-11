<?php

namespace Tests\Feature;

use App\Jobs\RunAssessment;
use App\Models\Assessment;
use App\Models\MonitoringPolicy;
use App\Models\Target;
use App\Models\User;
use App\Services\MonitoringDispatcherService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringDispatcherTest extends TestCase
{
    private array $userIds = [];
    private array $targetIds = [];
    private array $assessmentIds = [];
    private array $policyIds = [];

    protected function tearDown(): void
    {
        if ($this->policyIds !== []) {
            MonitoringPolicy::query()
                ->whereIn('id', $this->policyIds)
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

    private function makeUser(): User
    {
        $user = new User();

        $user->id = (string) Str::uuid();
        $user->name = 'Monitoring Dispatcher Test';
        $user->email =
            'monitoring-dispatch-'.Str::uuid().'@example.invalid';
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
        $target->name = 'Monitoring Dispatcher Target';
        $target->url = 'https://monitoring-dispatch.invalid/';
        $target->hostname = 'monitoring-dispatch.invalid';
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

    private function makePolicy(
        User $user,
        Target $target,
        bool $due = true,
    ): MonitoringPolicy {
        $policy = MonitoringPolicy::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'enabled' => true,
            'profile' => 'standard',
            'interval_minutes' => 60,
            'configuration' => [],
            'next_run_at' => $due
                ? now()->subMinute()
                : now()->addHour(),
        ]);

        $this->policyIds[] = $policy->id;

        return $policy;
    }

    private function makeAssessment(
        User $user,
        Target $target,
        string $status,
    ): Assessment {
        $assessment = Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => $status,
            'queued_at' => now(),
            'progress' => $status === 'running' ? 20 : 0,
            'configuration' => [],
            'execution_metadata' => [
                'test_fixture' => true,
            ],
        ]);

        $this->assessmentIds[] = $assessment->id;

        return $assessment;
    }

    public function test_due_policy_creates_one_queued_monitoring_assessment(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $policy = $this->makePolicy($user, $target);

        $result = app(
            MonitoringDispatcherService::class
        )->dispatchDue([$policy->id]);

        $this->assertSame(1, $result['evaluated']);
        $this->assertSame(1, $result['dispatched']);
        $this->assertSame(0, $result['overlap_skipped']);
        $this->assertSame(0, $result['failed']);

        $assessment = Assessment::query()
            ->where('target_id', $target->id)
            ->where('status', 'queued')
            ->firstOrFail();

        $this->assessmentIds[] = $assessment->id;

        $this->assertSame(
            'monitoring',
            $assessment->execution_metadata['dispatch_source']
                ?? null
        );

        Queue::assertPushed(
            RunAssessment::class,
            fn (RunAssessment $job) =>
                $job->assessmentId === $assessment->id
        );

        Queue::assertPushed(RunAssessment::class, 1);

        $policy->refresh();

        $this->assertSame(
            $assessment->id,
            $policy->last_assessment_id
        );

        $this->assertNotNull($policy->last_scheduled_at);
        $this->assertNotNull($policy->next_run_at);
        $this->assertTrue($policy->next_run_at->isFuture());
    }

    public function test_future_policy_is_not_dispatched(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $policy = $this->makePolicy(
            $user,
            $target,
            due: false,
        );

        $result = app(
            MonitoringDispatcherService::class
        )->dispatchDue([$policy->id]);

        $this->assertSame(0, $result['evaluated']);
        $this->assertSame(0, $result['dispatched']);

        $this->assertDatabaseMissing('assessments', [
            'target_id' => $target->id,
        ]);

        Queue::assertNothingPushed();
    }

    public function test_queued_assessment_causes_overlap_skip(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $policy = $this->makePolicy($user, $target);

        $existing = $this->makeAssessment(
            $user,
            $target,
            'queued',
        );

        $result = app(
            MonitoringDispatcherService::class
        )->dispatchDue([$policy->id]);

        $this->assertSame(1, $result['evaluated']);
        $this->assertSame(0, $result['dispatched']);
        $this->assertSame(1, $result['overlap_skipped']);

        $this->assertSame(
            1,
            Assessment::query()
                ->where('target_id', $target->id)
                ->count()
        );

        Queue::assertNothingPushed();

        $policy->refresh();

        $this->assertNull($policy->last_assessment_id);
        $this->assertNotNull($policy->last_scheduled_at);
        $this->assertTrue($policy->next_run_at->isFuture());

        $this->assertSame(
            $existing->id,
            Assessment::query()
                ->where('target_id', $target->id)
                ->firstOrFail()
                ->id
        );
    }

    public function test_running_assessment_causes_overlap_skip(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $policy = $this->makePolicy($user, $target);

        $this->makeAssessment(
            $user,
            $target,
            'running',
        );

        $result = app(
            MonitoringDispatcherService::class
        )->dispatchDue([$policy->id]);

        $this->assertSame(1, $result['evaluated']);
        $this->assertSame(0, $result['dispatched']);
        $this->assertSame(1, $result['overlap_skipped']);

        $this->assertSame(
            1,
            Assessment::query()
                ->where('target_id', $target->id)
                ->count()
        );

        Queue::assertNothingPushed();
    }

    public function test_unauthorized_target_disables_due_policy(): void
    {
        Queue::fake();

        $user = $this->makeUser();

        $target = $this->makeTarget(
            $user,
            authorized: false,
        );

        $policy = $this->makePolicy($user, $target);

        $result = app(
            MonitoringDispatcherService::class
        )->dispatchDue([$policy->id]);

        $this->assertSame(1, $result['evaluated']);
        $this->assertSame(1, $result['target_blocked']);
        $this->assertSame(0, $result['dispatched']);

        $policy->refresh();

        $this->assertFalse($policy->enabled);
        $this->assertNull($policy->next_run_at);

        $this->assertDatabaseMissing('assessments', [
            'target_id' => $target->id,
        ]);

        Queue::assertNothingPushed();
    }

    public function test_inactive_target_disables_due_policy(): void
    {
        Queue::fake();

        $user = $this->makeUser();

        $target = $this->makeTarget(
            $user,
            authorized: true,
            status: 'inactive',
        );

        $policy = $this->makePolicy($user, $target);

        $result = app(
            MonitoringDispatcherService::class
        )->dispatchDue([$policy->id]);

        $this->assertSame(1, $result['evaluated']);
        $this->assertSame(1, $result['target_blocked']);
        $this->assertSame(0, $result['dispatched']);

        $policy->refresh();

        $this->assertFalse($policy->enabled);
        $this->assertNull($policy->next_run_at);

        Queue::assertNothingPushed();
    }
}
