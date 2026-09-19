<?php

namespace Tests\Feature;

use App\Jobs\SendMonitoringDailyDigest;
use App\Models\MonitoringDailyDigestDelivery;
use App\Models\MonitoringNotificationPreference;
use App\Models\MonitoringPolicy;
use App\Models\Target;
use App\Models\User;
use App\Services\MonitoringDailyDigestPlannerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MonitoringDailyDigestPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }


    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function monitoredUser(
        string $email = 'monitor@example.test'
    ): User {
        $user = User::factory()->create([
            'email' => $email,
        ]);

        $target = Target::query()->create([
            'user_id' => $user->id,
            'name' => 'example.test',
            'url' => 'https://example.test',
            'hostname' => 'example.test',
            'scheme' => 'https',
            'port' => 443,
            'authorization_confirmed' => true,
            'authorization_confirmed_at' => now(),
            'authorization_method' => 'user_confirmation',
            'status' => 'active',
        ]);

        MonitoringPolicy::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'enabled' => true,
            'profile' => 'standard',
            'interval_minutes' => 1440,
            'configuration' => [],
            'next_run_at' => now()->addDay(),
        ]);

        return $user;
    }

    private function preference(
        User $user,
        bool $enabled = true,
        string $timezone = 'UTC',
        int $hour = 8,
    ): MonitoringNotificationPreference {
        return MonitoringNotificationPreference::query()
            ->create([
                'user_id' => $user->id,
                'email_enabled' => true,
                'event_types' =>
                    MonitoringNotificationPreference::defaultEventTypes(),
                'minimum_risk_delta' => 1,
                'daily_digest_enabled' => $enabled,
                'daily_digest_timezone' => $timezone,
                'daily_digest_hour' => $hour,
            ]);
    }

    public function test_digest_is_not_created_before_local_due_hour(): void
    {
        Carbon::setTestNow('2026-09-18 07:59:00 UTC');

        $user = $this->monitoredUser();
        $this->preference($user);

        $result = app(
            MonitoringDailyDigestPlannerService::class
        )->planUser($user->id);

        $this->assertSame('not_due', $result);

        $this->assertDatabaseCount(
            'monitoring_daily_digest_deliveries',
            0
        );
    }

    public function test_digest_is_created_at_local_due_hour(): void
    {
        Queue::fake();

        Carbon::setTestNow('2026-09-18 08:00:00 UTC');

        $user = $this->monitoredUser();
        $this->preference($user);

        $result = app(
            MonitoringDailyDigestPlannerService::class
        )->planUser($user->id);

        $this->assertSame('created', $result);

        $delivery =
            MonitoringDailyDigestDelivery::query()
                ->where('user_id', $user->id)
                ->firstOrFail();

        $this->assertSame(
            '2026-09-17',
            $delivery->digest_date->toDateString()
        );

        $this->assertSame(
            'pending',
            $delivery->status
        );

        Queue::assertPushed(
            SendMonitoringDailyDigest::class,
            fn (SendMonitoringDailyDigest $job): bool =>
                $job->deliveryId === $delivery->id
        );
    }

    public function test_same_user_gets_only_one_digest_per_local_day(): void
    {
        Carbon::setTestNow('2026-09-18 08:00:00 UTC');

        $user = $this->monitoredUser();
        $this->preference($user);

        $planner = app(
            MonitoringDailyDigestPlannerService::class
        );

        $this->assertSame(
            'created',
            $planner->planUser($user->id)
        );

        Carbon::setTestNow('2026-09-18 18:00:00 UTC');

        $this->assertSame(
            'existing',
            $planner->planUser($user->id)
        );

        $this->assertSame(
            1,
            MonitoringDailyDigestDelivery::query()
                ->where('user_id', $user->id)
                ->count()
        );
    }

    public function test_disabled_digest_creates_nothing(): void
    {
        Carbon::setTestNow('2026-09-18 12:00:00 UTC');

        $user = $this->monitoredUser();
        $this->preference(
            $user,
            enabled: false
        );

        $result = app(
            MonitoringDailyDigestPlannerService::class
        )->planUser($user->id);

        $this->assertSame('disabled', $result);

        $this->assertDatabaseCount(
            'monitoring_daily_digest_deliveries',
            0
        );
    }

    public function test_user_timezone_controls_due_time_and_date(): void
    {
        /*
         * 05:00 UTC = 08:00 Asia/Riyadh.
         */
        Carbon::setTestNow('2026-09-18 05:00:00 UTC');

        $user = $this->monitoredUser();
        $this->preference(
            $user,
            timezone: 'Asia/Riyadh',
            hour: 8
        );

        $result = app(
            MonitoringDailyDigestPlannerService::class
        )->planUser($user->id);

        $this->assertSame('created', $result);

        $delivery =
            MonitoringDailyDigestDelivery::query()
                ->where('user_id', $user->id)
                ->firstOrFail();

        $this->assertSame(
            '2026-09-17',
            $delivery->digest_date->toDateString()
        );

        $this->assertSame(
            'Asia/Riyadh',
            $delivery->timezone
        );
    }

    public function test_plan_due_only_evaluates_users_with_enabled_monitoring(): void
    {
        Carbon::setTestNow('2026-09-18 12:00:00 UTC');

        $enabledUser = $this->monitoredUser(
            'enabled@example.test'
        );

        $this->preference($enabledUser);

        $disabledUser = $this->monitoredUser(
            'disabled@example.test'
        );

        $this->preference($disabledUser);

        MonitoringPolicy::query()
            ->where('user_id', $disabledUser->id)
            ->update([
                'enabled' => false,
                'next_run_at' => null,
            ]);

        $result = app(
            MonitoringDailyDigestPlannerService::class
        )->planDue();

        $this->assertSame(1, $result['evaluated']);
        $this->assertSame(1, $result['created']);

        $this->assertDatabaseHas(
            'monitoring_daily_digest_deliveries',
            [
                'user_id' => $enabledUser->id,
            ]
        );

        $this->assertDatabaseMissing(
            'monitoring_daily_digest_deliveries',
            [
                'user_id' => $disabledUser->id,
            ]
        );
    }
}
