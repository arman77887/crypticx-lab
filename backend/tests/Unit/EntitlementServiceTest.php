<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\PlatformSettingsService;
use App\Services\SubscriptionService;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    private function serviceWithSubscription(
        ?Subscription $subscription,
        bool $premiumEnabled = true,
        array $integerSettings = [],
    ): EntitlementService {
        $resolver = $this->createMock(
            SubscriptionService::class
        );

        $resolver
            ->method('entitlementSubscription')
            ->willReturn($subscription);

        $settings = $this->createMock(
            PlatformSettingsService::class
        );

        $settings
            ->method('boolean')
            ->willReturnCallback(
                static function (
                    string $key,
                    bool $default = false,
                ) use ($premiumEnabled): bool {
                    return $key === 'premium_enabled'
                        ? $premiumEnabled
                        : $default;
                }
            );

        $settings
            ->method('integer')
            ->willReturnCallback(
                static function (
                    string $key,
                    ?int $default = null,
                ) use ($integerSettings): ?int {
                    return array_key_exists(
                        $key,
                        $integerSettings
                    )
                        ? $integerSettings[$key]
                        : $default;
                }
            );

        return new EntitlementService(
            $resolver,
            $settings,
        );
    }

    public function test_catalog_contains_canonical_plans(): void
    {
        $catalog = $this
            ->serviceWithSubscription(null)
            ->publicCatalog();

        $this->assertArrayHasKey('free', $catalog);
        $this->assertArrayHasKey(
            'professional',
            $catalog
        );
        $this->assertArrayHasKey('team', $catalog);
    }

    public function test_user_without_subscription_resolves_to_free(): void
    {
        $service =
            $this->serviceWithSubscription(null);

        $this->assertSame(
            'free',
            $service->effectivePlanCode(new User())
        );
    }

    public function test_unknown_subscription_plan_falls_back_to_free(): void
    {
        $subscription = new Subscription([
            'plan_code' => 'unknown-plan',
        ]);

        $service =
            $this->serviceWithSubscription(
                $subscription
            );

        $this->assertSame(
            'free',
            $service->effectivePlanCode(
                new User()
            )
        );
    }

    public function test_known_professional_subscription_is_resolved(): void
    {
        $subscription = new Subscription([
            'plan_code' => 'professional',
        ]);

        $service =
            $this->serviceWithSubscription(
                $subscription
            );

        $this->assertSame(
            'professional',
            $service->effectivePlanCode(
                new User()
            )
        );
    }

    public function test_free_core_security_tools_remain_available(): void
    {
        $service =
            $this->serviceWithSubscription(null);

        $user = new User();

        $this->assertTrue(
            $service->allows(
                $user,
                'core_tools'
            )
        );

        $this->assertTrue(
            $service->allows(
                $user,
                'security_labs'
            )
        );

        $this->assertTrue(
            $service->allows(
                $user,
                'assessments'
            )
        );
    }

    public function test_team_only_capability_is_not_available_to_free_user(): void
    {
        $service =
            $this->serviceWithSubscription(
                null,
                false,
            );

        $this->assertFalse(
            $service->allows(
                new User(),
                'team_workspace'
            )
        );
    }

    public function test_premium_disabled_makes_numeric_quotas_unlimited(): void
    {
        $service =
            $this->serviceWithSubscription(
                null,
                false,
            );

        $user = new User();

        $this->assertNull(
            $service->limit(
                $user,
                'targets_total'
            )
        );

        $this->assertNull(
            $service->limit(
                $user,
                'assessments_monthly'
            )
        );

        $this->assertNull(
            $service->limit(
                $user,
                'concurrent_assessments'
            )
        );
    }

    public function test_premium_disabled_does_not_unlock_paid_capabilities(): void
    {
        $service =
            $this->serviceWithSubscription(
                null,
                false,
            );

        $user = new User();

        $this->assertFalse(
            $service->allows(
                $user,
                'monitoring'
            )
        );

        $this->assertFalse(
            $service->allows(
                $user,
                'team_workspace'
            )
        );
    }

    public function test_premium_enabled_uses_default_free_limits(): void
    {
        $service =
            $this->serviceWithSubscription(
                null,
                true,
            );

        $user = new User();

        $this->assertSame(
            3,
            $service->limit(
                $user,
                'targets_total'
            )
        );

        $this->assertSame(
            25,
            $service->limit(
                $user,
                'assessments_monthly'
            )
        );

        $this->assertSame(
            1,
            $service->limit(
                $user,
                'concurrent_assessments'
            )
        );
    }

    public function test_premium_enabled_uses_admin_configured_limit(): void
    {
        $service =
            $this->serviceWithSubscription(
                null,
                true,
                [
                    'free_targets_total' => 77,
                    'free_assessments_monthly' => 888,
                ],
            );

        $user = new User();

        $this->assertSame(
            77,
            $service->limit(
                $user,
                'targets_total'
            )
        );

        $this->assertSame(
            888,
            $service->limit(
                $user,
                'assessments_monthly'
            )
        );
    }
}
