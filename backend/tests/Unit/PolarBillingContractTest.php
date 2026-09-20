<?php

namespace Tests\Unit;

use Tests\TestCase;

class PolarBillingContractTest extends TestCase
{
    public function test_polar_webhook_route_is_present(): void
    {
        $routes = file_get_contents(
            base_path('routes/api.php')
        );

        $this->assertStringContainsString(
            "Route::post('/webhooks/polar'",
            $routes
        );

        $this->assertStringContainsString(
            'PolarWebhookController',
            $routes
        );
    }

    public function test_polar_verifier_uses_raw_body_and_required_headers(): void
    {
        $service = file_get_contents(
            base_path(
                'app/Services/Billing/PolarWebhookVerifier.php'
            )
        );

        foreach (
            [
                'webhook-id',
                'webhook-timestamp',
                'webhook-signature',
                'getContent()',
                'hash_hmac',
                'hash_equals',
                'TOLERANCE_SECONDS',
            ]
            as $requirement
        ) {
            $this->assertStringContainsString(
                $requirement,
                $service
            );
        }
    }

    public function test_polar_checkout_is_created_server_side(): void
    {
        $gateway = file_get_contents(
            base_path(
                'app/Services/Billing/PolarBillingGateway.php'
            )
        );

        $this->assertStringContainsString(
            "'/v1/checkouts/'",
            $gateway
        );

        $this->assertStringContainsString(
            "'user_id'",
            $gateway
        );

        $this->assertStringContainsString(
            "'plan_code'",
            $gateway
        );

        $this->assertStringContainsString(
            'billing.polar.products.',
            $gateway
        );
    }

    public function test_subscription_sync_requires_user_plan_and_product_mapping(): void
    {
        $service = file_get_contents(
            base_path(
                'app/Services/Billing/PolarSubscriptionSyncService.php'
            )
        );

        $this->assertStringContainsString(
            "\$metadata['user_id']",
            $service
        );

        $this->assertStringContainsString(
            "\$metadata['plan_code']",
            $service
        );

        $this->assertStringContainsString(
            'billing.polar.products.',
            $service
        );

        $this->assertStringContainsString(
            'hash_equals',
            $service
        );

        $this->assertStringContainsString(
            'ownership mismatch',
            $service
        );
    }

    public function test_polar_events_are_idempotent_and_ordered(): void
    {
        $webhookService = file_get_contents(
            base_path(
                'app/Services/Billing/PolarWebhookService.php'
            )
        );

        $subscriptionService = file_get_contents(
            base_path(
                'app/Services/Billing/PolarSubscriptionSyncService.php'
            )
        );

        $migration = file_get_contents(
            base_path(
                'database/migrations/'
                .'2026_09_11_160000_create_billing_webhook_events_table.php'
            )
        );

        $this->assertStringContainsString(
            "hash(\n            'sha256'",
            $webhookService
        );

        $this->assertStringContainsString(
            "['provider', 'event_id']",
            $migration
        );

        $this->assertStringContainsString(
            'provider_synced_at',
            $subscriptionService
        );

        $this->assertStringContainsString(
            'lessThanOrEqualTo',
            $subscriptionService
        );
    }

    public function test_only_subscription_events_reach_subscription_sync(): void
    {
        $service = file_get_contents(
            base_path(
                'app/Services/Billing/PolarWebhookService.php'
            )
        );

        $this->assertStringContainsString(
            "'subscription.'",
            $service
        );

        $this->assertStringNotContainsString(
            'EntitlementService',
            $service
        );

        $this->assertStringNotContainsString(
            'Subscription::STATUS_ACTIVE',
            $service
        );
    }

    public function test_browser_checkout_does_not_activate_subscription(): void
    {
        $controller = file_get_contents(
            base_path(
                'app/Http/Controllers/Api/V1/BillingController.php'
            )
        );

        $gateway = file_get_contents(
            base_path(
                'app/Services/Billing/PolarBillingGateway.php'
            )
        );

        $this->assertStringNotContainsString(
            'Subscription::create',
            $controller
        );

        $this->assertStringNotContainsString(
            'STATUS_ACTIVE',
            $controller
        );

        $this->assertStringNotContainsString(
            'Subscription::create',
            $gateway
        );

        $this->assertStringNotContainsString(
            'STATUS_ACTIVE',
            $gateway
        );
    }

    public function test_polar_secret_and_products_are_environment_backed(): void
    {
        $config = file_get_contents(
            base_path('config/billing.php')
        );

        foreach (
            [
                'POLAR_ACCESS_TOKEN',
                'POLAR_WEBHOOK_SECRET',
                'POLAR_PRODUCT_PROFESSIONAL',
                'POLAR_PRODUCT_TEAM',
            ]
            as $variable
        ) {
            $this->assertStringContainsString(
                $variable,
                $config
            );
        }
    }
}
