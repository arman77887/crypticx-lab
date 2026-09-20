<?php

namespace App\Services\Billing;

use App\Models\BillingWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Throwable;

class PolarWebhookService
{
    public function __construct(
        private PolarSubscriptionSyncService $subscriptions,
    ) {
    }

    public function process(array $payload): array
    {
        $eventType = strtolower(
            trim((string) ($payload['type'] ?? ''))
        );

        $timestamp = trim(
            (string) ($payload['timestamp'] ?? '')
        );

        $data = $payload['data'] ?? null;

        if (
            $eventType === ''
            || $timestamp === ''
            || ! is_array($data)
        ) {
            throw new InvalidArgumentException(
                'Invalid Polar event envelope.'
            );
        }

        $subscriptionId = trim(
            (string) ($data['id'] ?? '')
        );

        /*
         * Polar's webhook envelope does not expose a separate
         * event identifier in the schema we consume.
         *
         * Build a deterministic delivery identity from immutable
         * envelope fields. This prevents duplicate delivery from
         * applying the same lifecycle transition twice.
         */
        $eventId = hash(
            'sha256',
            implode('|', [
                $eventType,
                $timestamp,
                $subscriptionId,
            ])
        );

        $event = $this->createEventRecord(
            $eventId,
            $eventType,
            $timestamp,
        );

        if ($event === null) {
            return [
                'duplicate' => true,
                'event_id' => $eventId,
            ];
        }

        try {
            if (str_starts_with(
                $eventType,
                'subscription.'
            )) {
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
        string $eventId,
        string $eventType,
        string $timestamp,
    ): ?BillingWebhookEvent {
        try {
            return BillingWebhookEvent::query()
                ->create([
                    'provider' => 'polar',
                    'event_id' => $eventId,
                    'notification_id' => null,
                    'event_type' => $eventType,
                    'occurred_at' =>
                        CarbonImmutable::parse(
                            $timestamp
                        ),
                    'processing_status' =>
                        BillingWebhookEvent::STATUS_RECEIVED,
                ]);
        } catch (QueryException $exception) {
            if (
                BillingWebhookEvent::query()
                    ->where('provider', 'polar')
                    ->where('event_id', $eventId)
                    ->exists()
            ) {
                return null;
            }

            throw $exception;
        }
    }
}
