<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EntitlementService;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserQuotaOverrideTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create();
    }

    private function setting(
        string $key,
        bool|int $value,
    ): void {
        app(PlatformSettingsService::class)->update(
            $key,
            $value,
            $this->actor,
        );
    }

    public function test_premium_off_ignores_user_override_and_returns_unlimited(): void
    {
        $user = User::factory()->create();

        $user->quotaOverride()->create([
            'targets_total' => 99,
        ]);

        $this->setting('premium_enabled', false);

        $this->assertNull(
            app(EntitlementService::class)
                ->limit($user, 'targets_total'),
        );
    }

    public function test_premium_on_user_override_wins_over_plan_limit(): void
    {
        $user = User::factory()->create();

        $this->setting('premium_enabled', true);
        $this->setting('free_targets_total', 3);

        $user->quotaOverride()->create([
            'targets_total' => 25,
        ]);

        $this->assertSame(
            25,
            app(EntitlementService::class)
                ->limit($user, 'targets_total'),
        );
    }

    public function test_null_override_inherits_admin_configured_plan_limit(): void
    {
        $user = User::factory()->create();

        $this->setting('premium_enabled', true);
        $this->setting('free_targets_total', 17);

        $user->quotaOverride()->create([
            'targets_total' => null,
        ]);

        $this->assertSame(
            17,
            app(EntitlementService::class)
                ->limit($user, 'targets_total'),
        );
    }

    public function test_quota_override_does_not_unlock_paid_capability(): void
    {
        $user = User::factory()->create();

        $this->setting('premium_enabled', true);

        $user->quotaOverride()->create([
            'monitoring_policies' => 100,
        ]);

        $entitlements = app(EntitlementService::class);

        $this->assertFalse(
            $entitlements->allows($user, 'monitoring'),
        );

        $this->assertSame(
            100,
            $entitlements->limit(
                $user,
                'monitoring_policies',
            ),
        );
    }
}
