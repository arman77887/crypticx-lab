<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremiumLaunchControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_premium_is_disabled_by_default(): void
    {
        $response = $this->getJson(
            '/api/v1/billing/status'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.checkout_enabled',
                false
            )
            ->assertJsonPath(
                'data.configured',
                false
            );
    }

    public function test_checkout_is_blocked_when_premium_is_disabled(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/account/billing/checkout',
                [
                    'plan_code' => 'professional',
                ]
            );

        $response
            ->assertStatus(503)
            ->assertJsonPath(
                'code',
                'PREMIUM_DISABLED'
            );
    }

    public function test_disabled_premium_returns_unlimited_numeric_quota(): void
    {
        $user = User::factory()->create();

        $limit = app(
            EntitlementService::class
        )->limit(
            $user,
            'targets_total'
        );

        $this->assertNull($limit);
    }

    public function test_premium_off_does_not_unlock_monitoring(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(
            app(EntitlementService::class)
                ->allows(
                    $user,
                    'monitoring'
                )
        );
    }

    public function test_admin_limit_is_used_when_premium_is_enabled(): void
    {
        $user = User::factory()->create();

        PlatformSetting::query()->create([
            'key' => 'premium_enabled',
            'value' => true,
            'updated_by' => $user->id,
        ]);

        PlatformSetting::query()->create([
            'key' => 'free_targets_total',
            'value' => 42,
            'updated_by' => $user->id,
        ]);

        $limit = app(
            EntitlementService::class
        )->limit(
            $user,
            'targets_total'
        );

        $this->assertSame(42, $limit);
    }
}
