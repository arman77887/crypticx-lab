<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingLifecycle;
use App\Models\MonitoringChangeEvent;
use App\Models\Target;
use App\Models\User;
use App\Services\ChangeDetectionService;
use App\Services\FindingLifecycleService;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChangeDetectionServiceTest extends TestCase
{
    private array $userIds = [];
    private array $targetIds = [];
    private array $assessmentIds = [];
    private array $findingIds = [];

    protected function tearDown(): void
    {
        if ($this->targetIds !== []) {
            MonitoringChangeEvent::query()
                ->whereIn('target_id', $this->targetIds)
                ->delete();

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
        $user->name = 'Change Detection Test';
        $user->email =
            'change-detection-'.Str::uuid().'@example.invalid';
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
        $target->name = 'Change Detection Target';
        $target->url = 'https://change-detection.invalid/';
        $target->hostname = 'change-detection.invalid';
        $target->scheme = 'https';
        $target->port = 443;
        $target->authorization_confirmed = true;
        $target->authorization_confirmed_at = now();
        $target->authorization_method = 'test_fixture';
        $target->status = 'active';
        $target->metadata = ['test_fixture' => true];
        $target->save();

        $this->targetIds[] = $target->id;

        return $target;
    }

    private function makeAssessment(
        User $user,
        Target $target,
        int $sequence
    ): Assessment {
        $time = now()->subMinutes(20 - ($sequence * 2));

        $assessment = Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => 'completed',
            'queued_at' => $time->copy()->subMinute(),
            'started_at' => $time->copy()->subSeconds(30),
            'completed_at' => $time,
            'progress' => 100,
            'configuration' => [],
            'execution_metadata' => [
                'dispatch_source' => 'monitoring',
                'test_fixture' => true,
            ],
        ]);

        $this->assessmentIds[] = $assessment->id;

        return $assessment;
    }

    private function makeFinding(
        Assessment $assessment,
        Target $target,
        string $fingerprint,
        string $severity = 'medium',
        string $confidence = 'high',
    ): Finding {
        $finding = Finding::query()->create([
            'assessment_id' => $assessment->id,
            'target_id' => $target->id,
            'type' => 'test_header',
            'fingerprint' => $fingerprint,
            'title' => 'Synthetic monitoring finding',
            'description' => 'Synthetic regression fixture.',
            'severity' => $severity,
            'confidence' => $confidence,
            'evidence' => 'Synthetic fixture.',
            'evidence_data' => ['test_fixture' => true],
            'remediation' => 'Synthetic fixture.',
            'status' => 'open',
        ]);

        $this->findingIds[] = $finding->id;

        return $finding;
    }

    private function sync(Assessment $assessment): array
    {
        return app(FindingLifecycleService::class)
            ->syncAssessment($assessment);
    }

    public function test_new_finding_and_risk_change_are_persisted_idempotently(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $baseline = $this->makeAssessment($user, $target, 1);
        $this->sync($baseline);

        $current = $this->makeAssessment($user, $target, 2);

        $fingerprint = hash(
            'sha256',
            'change-new-'.Str::uuid()
        );

        $this->makeFinding(
            $current,
            $target,
            $fingerprint,
            'high',
            'high'
        );

        $this->sync($current);

        $first = app(ChangeDetectionService::class)
            ->detect($current);

        $this->assertTrue($first['available']);
        $this->assertSame(
            1,
            $first['event_types']['finding_new'] ?? 0
        );

        $this->assertDatabaseHas(
            'monitoring_change_events',
            [
                'assessment_id' => $current->id,
                'event_type' => 'finding_new',
                'fingerprint' => $fingerprint,
            ]
        );

        $riskEvent = MonitoringChangeEvent::query()
            ->where('assessment_id', $current->id)
            ->where('event_type', 'risk_changed')
            ->first();

        $this->assertNotNull($riskEvent);

        $this->assertFalse(
            $riskEvent->payload['semantics']
                ['historical_snapshot']
        );

        $countBefore = MonitoringChangeEvent::query()
            ->where('assessment_id', $current->id)
            ->count();

        $second = app(ChangeDetectionService::class)
            ->detect($current);

        $countAfter = MonitoringChangeEvent::query()
            ->where('assessment_id', $current->id)
            ->count();

        $this->assertSame($countBefore, $countAfter);
        $this->assertSame(0, $second['events_created']);
        $this->assertSame(
            $countBefore,
            $second['events_existing']
        );
    }

    public function test_absence_creates_no_longer_detected_not_resolved(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $previous = $this->makeAssessment($user, $target, 1);

        $fingerprint = hash(
            'sha256',
            'change-absence-'.Str::uuid()
        );

        $this->makeFinding(
            $previous,
            $target,
            $fingerprint
        );

        $this->sync($previous);

        $current = $this->makeAssessment($user, $target, 2);
        $this->sync($current);

        $result = app(ChangeDetectionService::class)
            ->detect($current);

        $this->assertSame(
            1,
            $result['event_types']
                ['finding_no_longer_detected'] ?? 0
        );

        $event = MonitoringChangeEvent::query()
            ->where('assessment_id', $current->id)
            ->where(
                'event_type',
                'finding_no_longer_detected'
            )
            ->where('fingerprint', $fingerprint)
            ->firstOrFail();

        $this->assertSame(
            'resolved',
            $event->payload['semantics']
                ['absence_does_not_prove']
        );

        $lifecycle = FindingLifecycle::query()
            ->where('target_id', $target->id)
            ->where('fingerprint', $fingerprint)
            ->firstOrFail();

        $this->assertNotSame(
            'resolved',
            $lifecycle->status
        );

        $this->assertNull($lifecycle->resolved_at);

        $this->assertDatabaseMissing(
            'monitoring_change_events',
            [
                'assessment_id' => $current->id,
                'event_type' => 'finding_resolved',
                'fingerprint' => $fingerprint,
            ]
        );
    }

    public function test_historical_reappearance_is_not_classified_as_new(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $first = $this->makeAssessment($user, $target, 1);

        $fingerprint = hash(
            'sha256',
            'change-reappearance-'.Str::uuid()
        );

        $this->makeFinding(
            $first,
            $target,
            $fingerprint
        );

        $this->sync($first);

        $absent = $this->makeAssessment($user, $target, 2);
        $this->sync($absent);

        $current = $this->makeAssessment($user, $target, 3);

        $this->makeFinding(
            $current,
            $target,
            $fingerprint
        );

        $this->sync($current);

        $result = app(ChangeDetectionService::class)
            ->detect($current);

        $this->assertSame(
            1,
            $result['event_types']
                ['finding_reappeared'] ?? 0
        );

        $this->assertArrayNotHasKey(
            'finding_new',
            $result['event_types']
        );

        $this->assertDatabaseHas(
            'monitoring_change_events',
            [
                'assessment_id' => $current->id,
                'event_type' => 'finding_reappeared',
                'fingerprint' => $fingerprint,
            ]
        );
    }

    public function test_explicitly_resolved_finding_generates_reopened_event(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $first = $this->makeAssessment($user, $target, 1);

        $fingerprint = hash(
            'sha256',
            'change-reopened-'.Str::uuid()
        );

        $firstFinding = $this->makeFinding(
            $first,
            $target,
            $fingerprint
        );

        $this->sync($first);

        $lifecycle = FindingLifecycle::query()
            ->where('target_id', $target->id)
            ->where('fingerprint', $fingerprint)
            ->firstOrFail();

        $lifecycle->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $firstFinding->update([
            'status' => 'resolved',
        ]);

        $current = $this->makeAssessment($user, $target, 2);

        $this->makeFinding(
            $current,
            $target,
            $fingerprint
        );

        $syncResult = $this->sync($current);

        $this->assertSame(1, $syncResult['reopened']);

        $result = app(ChangeDetectionService::class)
            ->detect($current);

        $this->assertSame(
            1,
            $result['event_types']
                ['finding_reopened'] ?? 0
        );

        $this->assertDatabaseHas(
            'monitoring_change_events',
            [
                'assessment_id' => $current->id,
                'event_type' => 'finding_reopened',
                'fingerprint' => $fingerprint,
            ]
        );

        $lifecycle->refresh();

        $this->assertSame('reopened', $lifecycle->status);
        $this->assertNull($lifecycle->resolved_at);
    }

    public function test_first_completed_assessment_has_no_comparison_events(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $assessment = $this->makeAssessment(
            $user,
            $target,
            1
        );

        $fingerprint = hash(
            'sha256',
            'change-first-assessment-'.Str::uuid()
        );

        $this->makeFinding(
            $assessment,
            $target,
            $fingerprint
        );

        $this->sync($assessment);

        $result = app(ChangeDetectionService::class)
            ->detect($assessment);

        $this->assertFalse($result['available']);
        $this->assertSame(
            'no_previous_completed_assessment',
            $result['reason']
        );

        $this->assertSame(0, $result['events_created']);

        $this->assertDatabaseMissing(
            'monitoring_change_events',
            [
                'assessment_id' => $assessment->id,
            ]
        );
    }
}
