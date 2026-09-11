<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionService
{
    public function entitlementSubscription(
        User $user,
    ): ?Subscription {
        /*
         * Provider-specific billing logic does not belong here.
         *
         * This service only resolves persisted subscription state.
         */
        $subscriptions = $user->subscriptions()
            ->whereIn(
                'status',
                [
                    Subscription::STATUS_TRIALING,
                    Subscription::STATUS_ACTIVE,
                ]
            )
            ->orderByDesc('current_period_end')
            ->orderByDesc('created_at')
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->grantsEntitlements()) {
                return $subscription;
            }
        }

        return null;
    }

    public function accountState(
        User $user,
    ): array {
        $subscription =
            $this->entitlementSubscription($user);

        if ($subscription === null) {
            return [
                'subscribed' => false,
                'plan_code' => null,
                'status' => null,
                'provider' => null,
                'current_period_start' => null,
                'current_period_end' => null,
                'cancel_at_period_end' => false,
            ];
        }

        return [
            'subscribed' => true,
            'plan_code' => $subscription->plan_code,
            'status' => $subscription->status,
            'provider' => $subscription->provider,
            'current_period_start' =>
                $subscription->current_period_start
                    ?->toIso8601String(),
            'current_period_end' =>
                $subscription->current_period_end
                    ?->toIso8601String(),
            'cancel_at_period_end' =>
                (bool) $subscription->cancel_at_period_end,
        ];
    }
}
