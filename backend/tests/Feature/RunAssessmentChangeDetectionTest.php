<?php

namespace Tests\Feature;

use App\Jobs\RunAssessment;
use App\Models\Assessment;
use App\Models\Target;
use App\Models\User;
use App\Services\ChangeDetectionService;
use App\Services\FindingLifecycleService;
use App\Services\HttpAssessmentService;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class RunAssessmentChangeDetectionTest extends TestCase
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
        $user->name = 'RunAssessment Integration Test';
        $user->email =
            'run-assessment-'.Str::uuid().'@example.invalid';
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
        $target->name = 'RunAssessment Test Target';
        $target->url = 'https://run-assessment.invalid/';
        $target->hostname = 'run-assessment.invalid';
        $target->scheme = 'https';
        $target->port = 443;
        $target->authorization_confirmed = true;
        $target->authorization_confirmed_at = now();
        $target->authorization_method = 'test_fixture';
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
        string $source
    ): Assessment {
        $assessment = Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => 'queued',
            'queued_at' => now(),
            'progress' => 0,
            'configuration' => [],
            'execution_metadata' => [
                'dispatch_source' => $source,
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
            'scanner_policy' => [
                'authorization_scope' => 'single_target',
                'dns_pinning' => true,
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
            'semantics' => [
                'absence_means' => 'no_longer_detected',
                'absence_does_not_prove' => 'resolved',
                'resolved_requires_explicit_workflow' => true,
            ],
        ];
    }

    public function test_manual_assessment_does_not_run_change_detection(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $assessment = $this->makeAssessment(
            $user,
            $target,
            'manual'
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

        app()->call(
            [new RunAssessment($assessment->id), 'handle']
        );

        $assessment->refresh();

        $this->assertSame('completed', $assessment->status);
        $this->assertSame(100, $assessment->progress);
        $this->assertNotNull($assessment->completed_at);

        $metadata = $assessment->execution_metadata;

        $this->assertSame(
            'manual',
            $metadata['dispatch_source']
        );

        $this->assertSame(
            'http-assessment-v3',
            $metadata['engine']
        );

        $this->assertArrayHasKey('result', $metadata);
        $this->assertArrayHasKey('lifecycle', $metadata);
        $this->assertArrayNotHasKey(
            'change_detection',
            $metadata
        );
    }

    public function test_monitoring_assessment_runs_change_detection_once(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $assessment = $this->makeAssessment(
            $user,
            $target,
            'monitoring'
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

        $changeResult = [
            'available' => true,
            'assessment_id' => $assessment->id,
            'previous_assessment_id' =>
                (string) Str::uuid(),
            'events_created' => 2,
            'events_existing' => 0,
            'event_types' => [
                'finding_new' => 1,
                'risk_changed' => 1,
            ],
            'risk' => [
                'delta_points' => 10,
                'trend' => 'increased',
                'historical_snapshot' => false,
            ],
        ];

        $changes = $this->mock(
            ChangeDetectionService::class
        );

        $changes->shouldReceive('detect')
            ->once()
            ->withArgs(function (Assessment $given) use (
                $assessment
            ): bool {
                return
                    $given->id === $assessment->id
                    && $given->status === 'completed'
                    && $given->completed_at !== null;
            })
            ->andReturn($changeResult);

        app()->call(
            [new RunAssessment($assessment->id), 'handle']
        );

        $assessment->refresh();

        $this->assertSame('completed', $assessment->status);
        $this->assertSame(100, $assessment->progress);

        $metadata = $assessment->execution_metadata;

        $this->assertSame(
            'monitoring',
            $metadata['dispatch_source']
        );

        $storedChanges = $metadata['change_detection'];

        $this->assertTrue($storedChanges['available']);

        $this->assertSame(
            $assessment->id,
            $storedChanges['assessment_id']
        );

        $this->assertSame(
            $changeResult['previous_assessment_id'],
            $storedChanges['previous_assessment_id']
        );

        $this->assertSame(
            2,
            $storedChanges['events_created']
        );

        $this->assertSame(
            0,
            $storedChanges['events_existing']
        );

        $this->assertSame(
            1,
            $storedChanges['event_types']['finding_new']
        );

        $this->assertSame(
            1,
            $storedChanges['event_types']['risk_changed']
        );

        $this->assertSame(
            10,
            $storedChanges['risk']['delta_points']
        );

        $this->assertSame(
            'increased',
            $storedChanges['risk']['trend']
        );

        $this->assertFalse(
            $storedChanges['risk']['historical_snapshot']
        );

        $this->assertArrayHasKey('result', $metadata);
        $this->assertArrayHasKey('lifecycle', $metadata);
    }

    public function test_change_detection_failure_does_not_fail_successful_assessment(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);
        $assessment = $this->makeAssessment(
            $user,
            $target,
            'monitoring'
        );

        $scannerResult = $this->scannerResult();
        $lifecycleResult = $this->lifecycleResult();

        $scanner = $this->mock(
            HttpAssessmentService::class
        );

        $scanner->shouldReceive('run')
            ->once()
            ->andReturn($scannerResult);

        $lifecycle = $this->mock(
            FindingLifecycleService::class
        );

        $lifecycle->shouldReceive('syncAssessment')
            ->once()
            ->andReturn($lifecycleResult);

        $changes = $this->mock(
            ChangeDetectionService::class
        );

        $changes->shouldReceive('detect')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Synthetic change detection failure'
                )
            );

        app()->call(
            [new RunAssessment($assessment->id), 'handle']
        );

        $assessment->refresh();

        $this->assertSame('completed', $assessment->status);
        $this->assertSame(100, $assessment->progress);
        $this->assertNotNull($assessment->completed_at);
        $this->assertNull($assessment->error_message);

        $metadata = $assessment->execution_metadata;

        $this->assertSame(
            'monitoring',
            $metadata['dispatch_source']
        );

        $this->assertSame(
            'http-assessment-v3',
            $metadata['engine']
        );

        $storedResult = $metadata['result'];

        $this->assertSame(
            'http-assessment-v3',
            $storedResult['engine_version']
        );

        $this->assertSame(
            200,
            $storedResult['http_status']
        );

        $this->assertTrue(
            $storedResult['successful']
        );

        $this->assertSame(
            1,
            $storedResult['duration_ms']
        );

        $this->assertSame(
            'text/html',
            $storedResult['content_type']
        );

        $this->assertSame(
            0,
            $storedResult['finding_count']
        );

        $this->assertSame(
            ['192.0.2.1'],
            $storedResult['resolved_ips']
        );

        $this->assertTrue(
            $storedResult['tls']['enabled']
        );

        $this->assertTrue(
            $storedResult['scanner_policy']['dns_pinning']
        );

        $this->assertSame(
            'single_target',
            $storedResult['scanner_policy']
                ['authorization_scope']
        );

        $this->assertSame(
            'sha256',
            $storedResult['finding_identity']['algorithm']
        );

        $storedLifecycle = $metadata['lifecycle'];

        $this->assertSame(
            0,
            $storedLifecycle['created']
        );

        $this->assertSame(
            0,
            $storedLifecycle['reopened']
        );

        $this->assertSame(
            0,
            $storedLifecycle['resolved']
        );

        $this->assertSame(
            0,
            $storedLifecycle['no_longer_detected']
        );

        $this->assertSame(
            'no_longer_detected',
            $storedLifecycle['semantics']['absence_means']
        );

        $this->assertSame(
            'resolved',
            $storedLifecycle['semantics']
                ['absence_does_not_prove']
        );

        $this->assertTrue(
            $storedLifecycle['semantics']
                ['resolved_requires_explicit_workflow']
        );

        $this->assertFalse(
            $metadata['change_detection']['available']
        );

        $this->assertTrue(
            $metadata['change_detection']['failed']
        );

        $this->assertSame(
            RuntimeException::class,
            $metadata['change_detection']['exception']
        );

        $this->assertArrayNotHasKey(
            'message',
            $metadata['change_detection']
        );
    }
}
