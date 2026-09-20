<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PolarSubscriptionSyncService
{
    public function sync(array $payload): ?Subscription
    {
        $eventType = strtolower(
            trim((string) ($payload['type'] ?? ''))
        );

        if (! str_starts_with(
            $eventType,
            'subscription.'
        )) {
            return null;
        }

        $eventOccurredAt = $this->parseDate(
            $payload['timestamp'] ?? null
        );

        if ($eventOccurredAt === null) {
            throw new InvalidArgumentException(
                'Polar subscription event has no timestamp.'
            );
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidArgumentException(
                'Polar subscription payload has no data object.'
            );
        }

        $providerSubscriptionId = trim(
            (string) ($data['id'] ?? '')
        );

        if (! $this->validUuid($providerSubscriptionId)) {
            throw new InvalidArgumentException(
                'Invalid Polar subscription id.'
            );
        }

        $metadata = is_array(
            $data['metadata'] ?? null
        )
            ? $data['metadata']
            : [];

        $userId = trim(
            (string) ($metadata['user_id'] ?? '')
        );

        $planCode = strtolower(
            trim(
                (string) ($metadata['plan_code'] ?? '')
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
                'Polar subscription is missing trusted CrypticX metadata.'
            );
        }

        $user = User::query()
            ->whereKey($userId)
            ->first();

        if (! $user) {
            throw new InvalidArgumentException(
                'Polar subscription references an unknown user.'
            );
        }

        $productId = trim(
            (string) ($data['product_id'] ?? '')
        );

        $expectedProductId = trim(
            (string) config(
                "billing.polar.products.{$planCode}",
                ''
            )
        );

        if (
            ! $this->validUuid($productId)
            || ! $this->validUuid($expectedProductId)
            || ! hash_equals(
                $expectedProductId,
                $productId
            )
        ) {
            throw new InvalidArgumentException(
                'Polar subscription product does not match the configured plan.'
            );
        }

        $status = $this->mapStatus(
            (string) ($data['status'] ?? ''),
            $eventType,
        );

        $startsAt = $this->parseDate(
            $data['current_period_start'] ?? null
        );

        $endsAt = $this->parseDate(
            $data['current_period_end']
                ?? $data['ends_at']
                ?? $data['ended_at']
                ?? null
        );

        $cancelAtPeriodEnd = (bool) (
            $data['cancel_at_period_end']
                ?? false
        );

        $canceledAt = $this->parseDate(
            $data['canceled_at']
                ?? (
                    in_array(
                        $eventType,
                        [
                            'subscription.canceled',
                            'subscription.revoked',
                        ],
                        true
                    )
                        ? ($payload['timestamp'] ?? null)
                        : null
                )
        );

        $customerId = trim(
            (string) ($data['customer_id'] ?? '')
        );

        return DB::transaction(
            function () use (
                $user,
                $planCode,
                $status,
                $providerSubscriptionId,
                $customerId,
                $startsAt,
                $endsAt,
                $cancelAtPeriodEnd,
                $canceledAt,
                $eventOccurredAt,
            ) {
                $existing = Subscription::query()
                    ->where('provider', 'polar')
                    ->where(
                        'provider_subscription_id',
                        $providerSubscriptionId
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    $existing
                    && $existing->user_id !== $user->id
                ) {
                    throw new InvalidArgumentException(
                        'Polar subscription ownership mismatch.'
                    );
                }

                if (
                    $existing
                    && $existing->provider_synced_at !== null
                    && $eventOccurredAt->lessThanOrEqualTo(
                        $existing->provider_synced_at
                    )
                ) {
                    return $existing;
                }

                return Subscription::query()
                    ->updateOrCreate(
                        [
                            'provider' => 'polar',
                            'provider_subscription_id' =>
                                $providerSubscriptionId,
                        ],
                        [
                            'user_id' => $user->id,
                            'plan_code' => $planCode,
                            'status' => $status,
                            'provider_customer_id' =>
                                $customerId !== ''
                                    ? $customerId
                                    : null,
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

    private function mapStatus(
        string $status,
        string $eventType,
    ): string {
        if ($eventType === 'subscription.revoked') {
            return Subscription::STATUS_EXPIRED;
        }

        return match (strtolower(trim($status))) {
            'active' =>
                Subscription::STATUS_ACTIVE,

            'trialing' =>
                Subscription::STATUS_TRIALING,

            'past_due' =>
                Subscription::STATUS_PAST_DUE,

            'canceled' =>
                Subscription::STATUS_CANCELED,

            'revoked',
            'paused',
            'inactive',
            'expired' =>
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

        return CarbonImmutable::parse($value);
    }

    private function validUuid(
        string $value,
    ): bool {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }
}
