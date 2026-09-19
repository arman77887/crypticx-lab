<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\MonitoringChangeEvent;
use App\Models\MonitoringPolicy;
use App\Models\Target;
use App\Models\User;
use App\Services\MonitoringDailyDigestBuilderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringDailyDigestBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
        ]);
    }

    private function target(User $user, string $host): Target
    {
        return Target::query()->create([
            'user_id' => $user->id,
            'name' => $host,
            'url' => 'https://'.$host,
            'hostname' => $host,
            'scheme' => 'https',
            'port' => 443,
            'authorization_confirmed' => true,
            'authorization_confirmed_at' => now(),
            'authorization_method' => 'user_confirmation',
            'status' => 'active',
        ]);
    }

    private function policy(User $user, Target $target): void
    {
        MonitoringPolicy::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'enabled' => true,
            'profile' => 'standard',
            'interval_minutes' => 1440,
            'configuration' => [],
            'next_run_at' => now()->addDay(),
        ]);
    }

    private function assessment(
        User $user,
        Target $target,
        string $status,
        CarbonImmutable $at,
    ): Assessment {
        $assessment = Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => $status,
            'queued_at' => $at,
            'started_at' => $at,
            'completed_at' =>
                $status === 'completed' ? $at : null,
            'progress' =>
                $status === 'completed' ? 100 : 25,
            'configuration' => [],
            'execution_metadata' => [],
            'error_message' =>
                $status === 'failed'
                    ? 'bounded test failure'
                    : null,
        ]);

        $assessment->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->saveQuietly();

        return $assessment;
    }

    public function test_digest_uses_only_owners_targets(): void
    {
        $day = CarbonImmutable::parse(
            '2026-09-18 10:00:00',
            'UTC'
        );

        $owner = $this->user('owner@example.test');
        $other = $this->user('other@example.test');

        $ownedTarget = $this->target(
            $owner,
            'owned.example.test'
        );

        $otherTarget = $this->target(
            $other,
            'other.example.test'
        );

        $this->policy($owner, $ownedTarget);
        $this->policy($other, $otherTarget);

        $this->assessment(
            $owner,
            $ownedTarget,
            'completed',
            $day
        );

        $this->assessment(
            $other,
            $otherTarget,
            'completed',
            $day
        );

        $snapshot = app(
            MonitoringDailyDigestBuilderService::class
        )->build(
            $owner,
            '2026-09-18',
            'UTC'
        );

        $this->assertSame(
            1,
            $snapshot['totals']['monitored_targets']
        );

        $this->assertCount(1, $snapshot['targets']);

        $this->assertSame(
            'owned.example.test',
            $snapshot['targets'][0]['hostname']
        );
    }

    public function test_digest_discloses_failed_and_incomplete_scans(): void
    {
        $day = CarbonImmutable::parse(
            '2026-09-18 10:00:00',
            'UTC'
        );

        $user = $this->user('status@example.test');
        $target = $this->target(
            $user,
            'status.example.test'
        );

        $this->policy($user, $target);

        $this->assessment(
            $user,
            $target,
            'completed',
            $day
        );

        $this->assessment(
            $user,
            $target,
            'failed',
            $day->addHour()
        );

        $this->assessment(
            $user,
            $target,
            'running',
            $day->addHours(2)
        );

        $snapshot = app(
            MonitoringDailyDigestBuilderService::class
        )->build(
            $user,
            '2026-09-18',
            'UTC'
        );

        $this->assertSame(
            1,
            $snapshot['totals']['completed_scans']
        );

        $this->assertSame(
            1,
            $snapshot['totals']['failed_scans']
        );

        $this->assertSame(
            1,
            $snapshot['totals']['incomplete_scans']
        );

        $this->assertFalse(
            $snapshot['targets'][0]
                ['monitoring_complete_for_day']
        );
    }

    public function test_digest_counts_persisted_high_and_critical_events(): void
    {
        $day = CarbonImmutable::parse(
            '2026-09-18 10:00:00',
            'UTC'
        );

        $user = $this->user('risk@example.test');
        $target = $this->target(
            $user,
            'risk.example.test'
        );

        $this->policy($user, $target);

        $previous = $this->assessment(
            $user,
            $target,
            'completed',
            $day->subHour()
        );

        $current = $this->assessment(
            $user,
            $target,
            'completed',
            $day
        );

        foreach (['high', 'critical'] as $severity) {
            MonitoringChangeEvent::query()->create([
                'user_id' => $user->id,
                'target_id' => $target->id,
                'assessment_id' => $current->id,
                'previous_assessment_id' =>
                    $previous->id,
                'event_type' => 'finding_new',
                'fingerprint' =>
                    hash(
                        'sha256',
                        $severity.Str::uuid()
                    ),
                'payload' => [
                    'finding' => [
                        'title' =>
                            ucfirst($severity).' test finding',
                        'severity' => $severity,
                    ],
                ],
                'detected_at' => $day,
            ]);
        }

        $snapshot = app(
            MonitoringDailyDigestBuilderService::class
        )->build(
            $user,
            '2026-09-18',
            'UTC'
        );

        $this->assertSame(
            1,
            $snapshot['totals']['high_findings_detected']
        );

        $this->assertSame(
            1,
            $snapshot['totals']['critical_findings_detected']
        );

        $this->assertSame(
            2,
            $snapshot['totals']['new_findings']
        );
    }

    public function test_empty_changes_never_claim_site_is_safe(): void
    {
        $day = CarbonImmutable::parse(
            '2026-09-18 10:00:00',
            'UTC'
        );

        $user = $this->user('truth@example.test');
        $target = $this->target(
            $user,
            'truth.example.test'
        );

        $this->policy($user, $target);

        $this->assessment(
            $user,
            $target,
            'completed',
            $day
        );

        $snapshot = app(
            MonitoringDailyDigestBuilderService::class
        )->build(
            $user,
            '2026-09-18',
            'UTC'
        );

        $json = strtolower(
            json_encode(
                $snapshot,
                JSON_THROW_ON_ERROR
            )
        );

        $this->assertStringNotContainsString(
            'site is safe',
            $json
        );

        $this->assertStringNotContainsString(
            'site is secure',
            $json
        );

        $this->assertTrue(
            $snapshot['truthfulness']
                ['no_findings_does_not_mean_safe']
        );
    }

    public function test_timezone_defines_digest_day_boundary(): void
    {
        $user = $this->user('timezone@example.test');
        $target = $this->target(
            $user,
            'timezone.example.test'
        );

        $this->policy($user, $target);

        /*
         * 2026-09-17 21:30 UTC is 2026-09-18 00:30
         * in Asia/Riyadh.
         */
        $boundaryAssessment = $this->assessment(
            $user,
            $target,
            'completed',
            CarbonImmutable::parse(
                '2026-09-17 21:30:00',
                'UTC'
            )
        );

        /*
         * Force the persisted UTC boundary value so this test
         * verifies timezone-window behavior independently from
         * Eloquent timestamp serialization.
         */
        \DB::table('assessments')
            ->where('id', $boundaryAssessment->id)
            ->update([
                'created_at' => '2026-09-17 21:30:00',
                'updated_at' => '2026-09-17 21:30:00',
                'completed_at' => '2026-09-17 21:30:00',
            ]);

        $snapshot = app(
            MonitoringDailyDigestBuilderService::class
        )->build(
            $user,
            '2026-09-18',
            'Asia/Riyadh'
        );

        $this->assertSame(
            1,
            $snapshot['totals']['completed_scans']
        );
    }
}
