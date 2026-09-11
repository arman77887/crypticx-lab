<?php

namespace Tests\Unit;

use Tests\TestCase;

class PaddleCheckoutContractTest extends TestCase
{
    public function test_paddle_gateway_uses_server_side_transaction_creation(): void
    {
        $gateway = file_get_contents(
            base_path(
                'app/Services/Billing/PaddleBillingGateway.php'
            )
        );

        $this->assertStringContainsString(
            "'/transactions'",
            $gateway,
        );

        $this->assertStringContainsString(
            "'user_id'",
            $gateway,
        );

        $this->assertStringContainsString(
            "'plan_code'",
            $gateway,
        );

        $this->assertStringContainsString(
            "'quantity' => 1",
            $gateway,
        );
    }

    public function test_gateway_only_uses_configured_plan_price(): void
    {
        $gateway = file_get_contents(
            base_path(
                'app/Services/Billing/PaddleBillingGateway.php'
            )
        );

        $this->assertStringContainsString(
            'billing.paddle.prices.',
            $gateway,
        );

        $this->assertStringContainsString(
            "str_starts_with(\n            \$priceId,\n            'pri_'",
            $gateway,
        );
    }

    public function test_browser_does_not_activate_subscription(): void
    {
        $controller = file_get_contents(
            base_path(
                'app/Http/Controllers/Api/V1/BillingController.php'
            )
        );

        $this->assertStringNotContainsString(
            'Subscription::create',
            $controller,
        );

        $this->assertStringNotContainsString(
            'STATUS_ACTIVE',
            $controller,
        );
    }

    public function test_provider_event_ordering_is_persisted(): void
    {
        $migration = file_get_contents(
            base_path(
                'database/migrations/2026_09_11_161000_add_provider_synced_at_to_subscriptions_table.php'
            )
        );

        $service = file_get_contents(
            base_path(
                'app/Services/Billing/PaddleSubscriptionSyncService.php'
            )
        );

        $this->assertStringContainsString(
            'provider_synced_at',
            $migration,
        );

        $this->assertStringContainsString(
            'lessThanOrEqualTo',
            $service,
        );

        $this->assertStringContainsString(
            "'occurred_at'",
            $service,
        );
    }
}
