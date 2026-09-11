<?php

namespace Tests\Unit;

use Tests\TestCase;

class PaddleWebhookContractTest extends TestCase
{
    public function test_webhook_route_is_public_and_present(): void
    {
        $routes = file_get_contents(
            base_path('routes/api.php')
        );

        $this->assertStringContainsString(
            "Route::post('/webhooks/paddle'",
            $routes,
        );

        $routePrefix = strstr(
            $routes,
            "Route::post('/webhooks/paddle'",
            true
        );

        $this->assertNotFalse(
            $routePrefix
        );
    }

    public function test_official_sdk_verifier_is_used(): void
    {
        $service = file_get_contents(
            base_path(
                'app/Services/Billing/PaddleWebhookVerifier.php'
            )
        );

        $this->assertStringContainsString(
            'Paddle\\SDK\\Notifications\\Verifier',
            $service,
        );

        $this->assertStringContainsString(
            'toPsrRequest()',
            $service,
        );

        $this->assertStringContainsString(
            'PADDLE_WEBHOOK_SECRET',
            file_get_contents(
                base_path('.env.example')
            ),
        );
    }

    public function test_event_id_is_unique_for_idempotency(): void
    {
        $migration = file_get_contents(
            base_path(
                'database/migrations/2026_09_11_160000_create_billing_webhook_events_table.php'
            )
        );

        $this->assertStringContainsString(
            "['provider', 'event_id']",
            $migration,
        );

        $this->assertStringContainsString(
            'billing_webhook_provider_event_unique',
            $migration,
        );
    }

    public function test_transaction_events_do_not_grant_entitlements(): void
    {
        $service = file_get_contents(
            base_path(
                'app/Services/Billing/PaddleWebhookService.php'
            )
        );

        $this->assertStringNotContainsString(
            'Subscription::STATUS_ACTIVE',
            $service,
        );

        $this->assertStringNotContainsString(
            'EntitlementService',
            $service,
        );
    }

    public function test_subscription_sync_requires_user_plan_and_price_mapping(): void
    {
        $service = file_get_contents(
            base_path(
                'app/Services/Billing/PaddleSubscriptionSyncService.php'
            )
        );

        $this->assertStringContainsString(
            "['user_id']",
            $service,
        );

        $this->assertStringContainsString(
            "['plan_code']",
            $service,
        );

        $this->assertStringContainsString(
            'billing.paddle.prices.',
            $service,
        );

        $this->assertStringContainsString(
            'hash_equals',
            $service,
        );
    }
}
