<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\SubscriptionService;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    private function serviceWithSubscription(
        ?Subscription $subscription,
    ): EntitlementService {
        $resolver = $this->createMock(
            SubscriptionService::class
        );

        $resolver
            ->method('entitlementSubscription')
            ->willReturn($subscription);

        return new EntitlementService(
            $resolver
        );
    }

    public function test_catalog_contains_canonical_plans(): void
    {
        $service =
            $this->serviceWithSubscription(null);

        $catalog = $service->publicCatalog();

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

        $user = new User();

        $this->assertSame(
            'free',
            $service->effectivePlanCode($user)
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
            $this->serviceWithSubscription(null);

        $this->assertFalse(
            $service->allows(
                new User(),
                'team_workspace'
            )
        );
    }

    public function test_free_limits_are_typed_as_integers(): void
    {
        $service =
            $this->serviceWithSubscription(null);

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
}
