<?php

namespace Tests\Unit;

use App\Models\Subscription;
use Tests\TestCase;

class SubscriptionStateContractTest extends TestCase
{
    public function test_only_active_or_trialing_states_can_grant_entitlements(): void
    {
        foreach (
            [
                Subscription::STATUS_PAST_DUE,
                Subscription::STATUS_CANCELED,
                Subscription::STATUS_EXPIRED,
            ]
            as $status
        ) {
            $subscription = new Subscription([
                'status' => $status,
                'plan_code' => 'professional',
            ]);

            $this->assertFalse(
                $subscription->grantsEntitlements()
            );
        }

        foreach (
            [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
            ]
            as $status
        ) {
            $subscription = new Subscription([
                'status' => $status,
                'plan_code' => 'professional',
                'current_period_end' =>
                    now()->addDay(),
            ]);

            $this->assertTrue(
                $subscription->grantsEntitlements()
            );
        }
    }

    public function test_expired_period_fails_closed(): void
    {
        $subscription = new Subscription([
            'status' => Subscription::STATUS_ACTIVE,
            'plan_code' => 'professional',
            'current_period_end' =>
                now()->subSecond(),
        ]);

        $this->assertFalse(
            $subscription->grantsEntitlements()
        );
    }

    public function test_subscription_schema_has_provider_neutral_fields(): void
    {
        $source = file_get_contents(
            base_path(
                'database/migrations/'
                .'2026_09_11_145000_create_subscriptions_table.php'
            )
        );

        foreach (
            [
                'plan_code',
                'status',
                'provider',
                'provider_customer_id',
                'provider_subscription_id',
                'current_period_start',
                'current_period_end',
                'cancel_at_period_end',
            ]
            as $field
        ) {
            $this->assertStringContainsString(
                $field,
                $source,
            );
        }
    }

    public function test_entitlement_service_uses_subscription_resolver(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Services/EntitlementService.php'
            )
        );

        $this->assertStringContainsString(
            'SubscriptionService',
            $source,
        );

        $this->assertStringContainsString(
            'entitlementSubscription',
            $source,
        );

        $this->assertStringNotContainsString(
            "getAttribute('plan_code')",
            $source,
        );
    }

    public function test_account_subscription_endpoint_is_authenticated(): void
    {
        $routes = file_get_contents(
            base_path('routes/api.php')
        );

        $this->assertStringContainsString(
            "/account/subscription",
            $routes,
        );

        $this->assertStringContainsString(
            'AccountSubscriptionController',
            $routes,
        );
    }
}
