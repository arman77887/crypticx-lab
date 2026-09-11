<?php

namespace App\Services\Billing;

use App\Models\BillingWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Throwable;

class PaddleWebhookService
{
    public function __construct(
        private PaddleSubscriptionSyncService $subscriptions,
    ) {
    }

    public function process(
        array $payload,
    ): array {
        $eventId = trim(
            (string) ($payload['event_id'] ?? '')
        );

        $eventType = trim(
            (string) ($payload['event_type'] ?? '')
        );

        if (
            $eventId === ''
            || $eventType === ''
        ) {
            throw new InvalidArgumentException(
                'Invalid Paddle event envelope.'
            );
        }

        $event = $this->createEventRecord(
            $payload,
            $eventId,
            $eventType,
        );

        if ($event === null) {
            return [
                'duplicate' => true,
                'event_id' => $eventId,
            ];
        }

        try {
            if (
                str_starts_with(
                    $eventType,
                    'subscription.'
                )
            ) {
                $this->subscriptions->sync(
                    $payload
                );

                $event->forceFill([
                    'processing_status' =>
                        BillingWebhookEvent::STATUS_PROCESSED,
                    'processed_at' => now(),
                ])->save();

                return [
                    'duplicate' => false,
                    'event_id' => $eventId,
                    'processed' => true,
                ];
            }

            /*
             * Transaction events may be retained for audit, but
             * they do not independently grant entitlements.
             *
             * Subscription state remains the access authority.
             */
            $event->forceFill([
                'processing_status' =>
                    BillingWebhookEvent::STATUS_IGNORED,
                'processed_at' => now(),
            ])->save();

            return [
                'duplicate' => false,
                'event_id' => $eventId,
                'processed' => false,
            ];
        } catch (Throwable $exception) {
            $event->forceFill([
                'processing_status' =>
                    BillingWebhookEvent::STATUS_FAILED,
                'failure_reason' =>
                    mb_substr(
                        $exception->getMessage(),
                        0,
                        2000
                    ),
            ])->save();

            throw $exception;
        }
    }

    private function createEventRecord(
        array $payload,
        string $eventId,
        string $eventType,
    ): ?BillingWebhookEvent {
        try {
            return BillingWebhookEvent::query()
                ->create([
                    'provider' => 'paddle',
                    'event_id' => $eventId,
                    'notification_id' =>
                        $payload['notification_id']
                            ?? null,
                    'event_type' => $eventType,
                    'occurred_at' =>
                        $this->parseDate(
                            $payload['occurred_at']
                                ?? null
                        ),
                    'processing_status' =>
                        BillingWebhookEvent::STATUS_RECEIVED,
                ]);
        } catch (QueryException $exception) {
            if (
                BillingWebhookEvent::query()
                    ->where(
                        'provider',
                        'paddle'
                    )
                    ->where(
                        'event_id',
                        $eventId
                    )
                    ->exists()
            ) {
                return null;
            }

            throw $exception;
        }
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
