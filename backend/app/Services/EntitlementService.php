<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;

class EntitlementService
{
    public function __construct(
        private SubscriptionService $subscriptions,
    ) {
    }

    public function effectivePlanCode(
        User $user,
    ): string {
        $subscription =
            $this->subscriptions
                ->entitlementSubscription($user);

        if ($subscription === null) {
            return $this->defaultPlanCode();
        }

        $candidate = strtolower(
            trim(
                (string) $subscription->plan_code
            )
        );

        /*
         * Subscription data never creates arbitrary entitlements.
         * Only canonical configured plans are accepted.
         */
        if (
            $candidate !== ''
            && $this->planExists($candidate)
        ) {
            return $candidate;
        }

        return $this->defaultPlanCode();
    }

    public function defaultPlanCode(): string
    {
        $plan = strtolower(
            trim(
                (string) config(
                    'premium.default_plan',
                    'free',
                )
            )
        );

        if (! $this->planExists($plan)) {
            throw new InvalidArgumentException(
                'Configured default premium plan does not exist.'
            );
        }

        return $plan;
    }

    public function planExists(
        string $planCode,
    ): bool {
        return array_key_exists(
            $planCode,
            $this->plans(),
        );
    }

    public function plan(
        string $planCode,
    ): array {
        $planCode = strtolower(
            trim($planCode)
        );

        $plans = $this->plans();

        if (! array_key_exists($planCode, $plans)) {
            throw new InvalidArgumentException(
                'Unknown entitlement plan.'
            );
        }

        return $plans[$planCode];
    }

    public function forUser(
        User $user,
    ): array {
        $planCode =
            $this->effectivePlanCode($user);

        $plan = $this->plan($planCode);

        return [
            'plan' => [
                'code' => $planCode,
                'name' => (string) (
                    $plan['name']
                    ?? $planCode
                ),
            ],

            'capabilities' =>
                $plan['capabilities']
                ?? [],

            'limits' =>
                $plan['limits']
                ?? [],
        ];
    }

    public function allows(
        User $user,
        string $capability,
    ): bool {
        $entitlements =
            $this->forUser($user);

        return (
            $entitlements['capabilities'][$capability]
            ?? false
        ) === true;
    }

    public function limit(
        User $user,
        string $limit,
    ): ?int {
        $entitlements =
            $this->forUser($user);

        $value =
            $entitlements['limits'][$limit]
            ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException(
                'Entitlement limit must be numeric or null.'
            );
        }

        return max(
            0,
            (int) $value,
        );
    }

    public function publicCatalog(): array
    {
        $result = [];

        foreach ($this->plans() as $code => $plan) {
            $result[$code] = [
                'code' => $code,
                'name' => (string) (
                    $plan['name']
                    ?? $code
                ),

                'price_monthly_usd' =>
                    (int) (
                        $plan['price_monthly_usd']
                        ?? 0
                    ),

                'capabilities' =>
                    $plan['capabilities']
                    ?? [],

                'limits' =>
                    $plan['limits']
                    ?? [],
            ];
        }

        return $result;
    }

    private function plans(): array
    {
        $plans = config(
            'premium.plans',
            [],
        );

        if (! is_array($plans)) {
            throw new InvalidArgumentException(
                'Premium plan configuration must be an array.'
            );
        }

        return $plans;
    }
}
