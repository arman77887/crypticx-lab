<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaddleSubscriptionSyncService
{
    public function sync(
        array $payload,
    ): ?Subscription {
        $eventType = (string) (
            $payload['event_type'] ?? ''
        );

        if (! str_starts_with(
            $eventType,
            'subscription.'
        )) {
            return null;
        }

        $eventOccurredAt = $this->parseDate(
            $payload['occurred_at'] ?? null
        );

        if ($eventOccurredAt === null) {
            throw new InvalidArgumentException(
                'Paddle subscription event has no occurred_at timestamp.'
            );
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidArgumentException(
                'Paddle subscription payload has no data object.'
            );
        }

        $providerSubscriptionId = trim(
            (string) ($data['id'] ?? '')
        );

        if (
            $providerSubscriptionId === ''
            || ! str_starts_with(
                $providerSubscriptionId,
                'sub_'
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid Paddle subscription id.'
            );
        }

        $customData = is_array(
            $data['custom_data'] ?? null
        )
            ? $data['custom_data']
            : [];

        $userId = trim(
            (string) ($customData['user_id'] ?? '')
        );

        $planCode = strtolower(
            trim(
                (string) (
                    $customData['plan_code']
                    ?? ''
                )
            )
        );

        if (
            $userId === ''
            || ! in_array(
                $planCode,
                ['professional', 'team'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Paddle subscription is missing trusted CrypticX metadata.'
            );
        }

        $user = User::query()
            ->whereKey($userId)
            ->first();

        if (! $user) {
            throw new InvalidArgumentException(
                'Paddle subscription references an unknown user.'
            );
        }

        $priceId = $this->extractRecurringPriceId(
            $data
        );

        $expectedPriceId = trim(
            (string) config(
                "billing.paddle.prices.{$planCode}",
                ''
            )
        );

        if (
            $expectedPriceId === ''
            || $priceId === ''
            || ! hash_equals(
                $expectedPriceId,
                $priceId
            )
        ) {
            throw new InvalidArgumentException(
                'Paddle subscription price does not match the configured plan.'
            );
        }

        $status = $this->mapStatus(
            (string) ($data['status'] ?? '')
        );

        $period = is_array(
            $data['current_billing_period'] ?? null
        )
            ? $data['current_billing_period']
            : [];

        $startsAt = $this->parseDate(
            $period['starts_at'] ?? null
        );

        $endsAt = $this->parseDate(
            $period['ends_at'] ?? null
        );

        $scheduledChange = is_array(
            $data['scheduled_change'] ?? null
        )
            ? $data['scheduled_change']
            : null;

        $cancelAtPeriodEnd = (
            is_array($scheduledChange)
            && ($scheduledChange['action'] ?? null)
                === 'cancel'
        );

        $canceledAt = (
            $status === Subscription::STATUS_CANCELED
        )
            ? $this->parseDate(
                $data['canceled_at']
                    ?? $payload['occurred_at']
                    ?? null
            )
            : null;

        return DB::transaction(
            function () use (
                $user,
                $planCode,
                $status,
                $data,
                $providerSubscriptionId,
                $startsAt,
                $endsAt,
                $cancelAtPeriodEnd,
                $canceledAt,
                $eventOccurredAt,
            ) {
                $existing = Subscription::query()
                    ->where(
                        'provider',
                        'paddle'
                    )
                    ->where(
                        'provider_subscription_id',
                        $providerSubscriptionId
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    $existing
                    && $existing->user_id
                        !== $user->id
                ) {
                    throw new InvalidArgumentException(
                        'Paddle subscription ownership mismatch.'
                    );
                }

                if (
                    $existing
                    && $existing->provider_synced_at !== null
                    && $eventOccurredAt->lessThanOrEqualTo(
                        $existing->provider_synced_at
                    )
                ) {
                    /*
                     * Duplicate event IDs are handled before this layer.
                     * This protects against a different but older Paddle
                     * event arriving after a newer lifecycle event.
                     */
                    return $existing;
                }

                return Subscription::query()
                    ->updateOrCreate(
                        [
                            'provider' => 'paddle',
                            'provider_subscription_id' =>
                                $providerSubscriptionId,
                        ],
                        [
                            'user_id' => $user->id,
                            'plan_code' => $planCode,
                            'status' => $status,
                            'provider_customer_id' =>
                                $data['customer_id']
                                    ?? null,
                            'current_period_start' =>
                                $startsAt,
                            'current_period_end' =>
                                $endsAt,
                            'cancel_at_period_end' =>
                                $cancelAtPeriodEnd,
                            'canceled_at' =>
                                $canceledAt,
                            'provider_synced_at' =>
                                $eventOccurredAt,
                        ],
                    );
            }
        );
    }

    private function extractRecurringPriceId(
        array $data,
    ): string {
        $items = $data['items'] ?? [];

        if (! is_array($items)) {
            return '';
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $price = $item['price'] ?? null;

            if (! is_array($price)) {
                continue;
            }

            $id = trim(
                (string) ($price['id'] ?? '')
            );

            if (str_starts_with($id, 'pri_')) {
                return $id;
            }
        }

        return '';
    }

    private function mapStatus(
        string $status,
    ): string {
        return match (
            strtolower(trim($status))
        ) {
            'trialing' =>
                Subscription::STATUS_TRIALING,

            'active' =>
                Subscription::STATUS_ACTIVE,

            'past_due' =>
                Subscription::STATUS_PAST_DUE,

            'canceled' =>
                Subscription::STATUS_CANCELED,

            'paused' =>
                Subscription::STATUS_EXPIRED,

            default =>
                Subscription::STATUS_EXPIRED,
        };
    }

    private function parseDate(
        mixed $value,
    ): ?CarbonImmutable {
        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            return null;
        }

        return CarbonImmutable::parse(
            $value
        );
    }
}
