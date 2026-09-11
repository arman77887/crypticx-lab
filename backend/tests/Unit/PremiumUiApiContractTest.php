<?php

namespace Tests\Unit;

use Tests\TestCase;

class PremiumUiApiContractTest extends TestCase
{
    public function test_public_plan_catalog_route_exists(): void
    {
        $routes = file_get_contents(
            base_path('routes/api.php')
        );

        $this->assertStringContainsString(
            "Route::get('/plans'",
            $routes,
        );

        $this->assertStringContainsString(
            'PlanCatalogController::class',
            $routes,
        );
    }

    public function test_plan_catalog_uses_canonical_entitlement_catalog(): void
    {
        $controller = file_get_contents(
            base_path(
                'app/Http/Controllers/Api/V1/PlanCatalogController.php'
            )
        );

        $this->assertStringContainsString(
            'EntitlementService',
            $controller,
        );

        $this->assertStringContainsString(
            'publicCatalog()',
            $controller,
        );
    }

    public function test_catalog_endpoint_does_not_contain_checkout_logic(): void
    {
        $controller = strtolower(
            file_get_contents(
                base_path(
                    'app/Http/Controllers/Api/V1/PlanCatalogController.php'
                )
            )
        );

        foreach (
            [
                'checkout',
                'stripe',
                'paypal',
                'payment_success',
            ]
            as $term
        ) {
            $this->assertStringNotContainsString(
                $term,
                $controller,
            );
        }
    }
}
