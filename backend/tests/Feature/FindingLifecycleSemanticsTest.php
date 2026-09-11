<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingLifecycle;
use App\Models\Target;
use App\Models\User;
use App\Services\FindingLifecycleService;
use Illuminate\Support\Str;
use Tests\TestCase;

class FindingLifecycleSemanticsTest extends TestCase
{
    private array $userIds = [];
    private array $targetIds = [];
    private array $assessmentIds = [];
    private array $findingIds = [];

    protected function tearDown(): void
    {
        if ($this->targetIds !== []) {
            FindingLifecycle::query()
                ->whereIn('target_id', $this->targetIds)
                ->delete();
        }

        if ($this->findingIds !== []) {
            Finding::query()
                ->whereIn('id', $this->findingIds)
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
        $user->name = 'Lifecycle Semantics Test';
        $user->email =
            'lifecycle-'.Str::uuid().'@example.invalid';
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
        $target->name = 'Lifecycle Test Target';
        $target->url = 'https://lifecycle-test.invalid/';
        $target->hostname = 'lifecycle-test.invalid';
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

    private function makeCompletedAssessment(
        User $user,
        Target $target
    ): Assessment {
        $assessment = Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => 'completed',
            'queued_at' => now()->subMinutes(2),
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'progress' => 100,
            'configuration' => [],
            'execution_metadata' => [
                'test_fixture' => true,
            ],
        ]);

        $this->assessmentIds[] = $assessment->id;

        return $assessment;
    }

    private function makeFinding(
        Assessment $assessment,
        Target $target,
        string $fingerprint
    ): Finding {
        $finding = Finding::query()->create([
            'assessment_id' => $assessment->id,
            'target_id' => $target->id,
            'type' => 'test_header',
            'fingerprint' => $fingerprint,
            'title' => 'Lifecycle regression fixture',
            'description' => 'Synthetic test finding.',
            'severity' => 'medium',
            'confidence' => 'high',
            'evidence' => 'Synthetic fixture only.',
            'evidence_data' => [
                'test_fixture' => true,
            ],
            'remediation' => 'Synthetic fixture.',
            'status' => 'open',
        ]);

        $this->findingIds[] = $finding->id;

        return $finding;
    }

    public function test_absence_does_not_automatically_resolve_finding(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $first = $this->makeCompletedAssessment(
            $user,
            $target
        );

        $fingerprint = hash(
            'sha256',
            'lifecycle-absence-'.Str::uuid()
        );

        $this->makeFinding(
            $first,
            $target,
            $fingerprint
        );

        $firstResult = app(
            FindingLifecycleService::class
        )->syncAssessment($first);

        $this->assertSame(1, $firstResult['created']);
        $this->assertSame(0, $firstResult['resolved']);

        $lifecycle = FindingLifecycle::query()
            ->where('target_id', $target->id)
            ->where('fingerprint', $fingerprint)
            ->firstOrFail();

        $this->assertSame('open', $lifecycle->status);
        $this->assertNull($lifecycle->resolved_at);
        $this->assertSame(1, $lifecycle->occurrence_count);

        $second = $this->makeCompletedAssessment(
            $user,
            $target
        );

        // Deliberately no finding with this fingerprint in scan #2.
        $secondResult = app(
            FindingLifecycleService::class
        )->syncAssessment($second);

        $this->assertSame(
            1,
            $secondResult['no_longer_detected']
        );

        $this->assertSame(0, $secondResult['resolved']);

        $this->assertSame(
            'no_longer_detected',
            $secondResult['semantics']['absence_means']
        );

        $this->assertTrue(
            $secondResult['semantics']
                ['resolved_requires_explicit_workflow']
        );

        $lifecycle->refresh();

        $this->assertSame('open', $lifecycle->status);
        $this->assertNull($lifecycle->resolved_at);
        $this->assertSame(1, $lifecycle->occurrence_count);
        $this->assertSame(
            $first->id,
            $lifecycle->last_assessment_id
        );
    }

    public function test_explicitly_resolved_finding_reopens_when_detected_again(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $first = $this->makeCompletedAssessment(
            $user,
            $target
        );

        $fingerprint = hash(
            'sha256',
            'lifecycle-reopen-'.Str::uuid()
        );

        $firstFinding = $this->makeFinding(
            $first,
            $target,
            $fingerprint
        );

        app(FindingLifecycleService::class)
            ->syncAssessment($first);

        $lifecycle = FindingLifecycle::query()
            ->where('target_id', $target->id)
            ->where('fingerprint', $fingerprint)
            ->firstOrFail();

        // Simulate the existing explicit Finding API workflow.
        $resolvedAt = now();

        $lifecycle->update([
            'status' => 'resolved',
            'resolved_at' => $resolvedAt,
        ]);

        $firstFinding->update([
            'status' => 'resolved',
        ]);

        $second = $this->makeCompletedAssessment(
            $user,
            $target
        );

        $secondFinding = $this->makeFinding(
            $second,
            $target,
            $fingerprint
        );

        $result = app(
            FindingLifecycleService::class
        )->syncAssessment($second);

        $this->assertSame(1, $result['reopened']);
        $this->assertSame(0, $result['resolved']);

        $lifecycle->refresh();
        $secondFinding->refresh();

        $this->assertSame('reopened', $lifecycle->status);
        $this->assertSame('reopened', $secondFinding->status);

        $this->assertNotNull($lifecycle->reopened_at);
        $this->assertNull($lifecycle->resolved_at);

        $this->assertSame(2, $lifecycle->occurrence_count);
        $this->assertSame(
            $second->id,
            $lifecycle->last_assessment_id
        );

        $this->assertSame(
            $secondFinding->id,
            $lifecycle->last_finding_id
        );
    }
}
