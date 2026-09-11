<?php

namespace Tests\Unit;

use App\Exceptions\BillingUnavailableException;
use App\Models\User;
use App\Services\Billing\BillingManager;
use Tests\TestCase;

class BillingBoundaryContractTest extends TestCase
{
    public function test_billing_is_disabled_by_default(): void
    {
        config([
            'billing.provider' => null,
            'billing.checkout_enabled' => false,
        ]);

        $billing = app(BillingManager::class);

        $status = $billing->status();

        $this->assertFalse(
            $status['configured']
        );

        $this->assertFalse(
            $status['checkout_enabled']
        );

        $this->assertNull(
            $status['provider']
        );
    }

    public function test_checkout_fails_closed_without_provider(): void
    {
        config([
            'billing.provider' => null,
            'billing.checkout_enabled' => false,
        ]);

        $this->expectException(
            BillingUnavailableException::class
        );

        app(BillingManager::class)
            ->createCheckout(
                new User(),
                'professional',
            );
    }

    public function test_no_generic_webhook_route_exists_before_provider_adapter(): void
    {
        $routes = file_get_contents(
            base_path('routes/api.php')
        );

        $this->assertStringNotContainsString(
            "/billing/webhook",
            $routes,
        );

        $this->assertStringNotContainsString(
            "/webhooks/billing",
            $routes,
        );
    }

    public function test_checkout_does_not_write_subscription_state_directly(): void
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
            "STATUS_ACTIVE",
            $controller,
        );
    }

    public function test_billing_secrets_are_loaded_from_environment_not_hardcoded(): void
    {
        $config = file_get_contents(
            base_path('config/billing.php')
        );

        $this->assertStringContainsString(
            "env('PADDLE_API_KEY')",
            $config,
        );

        $this->assertStringContainsString(
            "env('PADDLE_WEBHOOK_SECRET')",
            $config,
        );

        $this->assertStringContainsString(
            "env('PADDLE_CLIENT_TOKEN')",
            $config,
        );

        /*
         * Paddle secret material must never be embedded directly
         * in committed PHP configuration.
         */
        $this->assertDoesNotMatchRegularExpression(
            '/pdl_[a-z0-9_]+_[A-Za-z0-9_-]{20,}/',
            $config,
        );
    }
}
