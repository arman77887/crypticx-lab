<?php

namespace App\Services;

use App\Jobs\SendMonitoringNotification;
use App\Models\MonitoringChangeEvent;
use App\Models\MonitoringNotificationDelivery;
use App\Models\MonitoringNotificationPreference;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MonitoringNotificationPlannerService
{
    public function plan(
        MonitoringChangeEvent $event
    ): ?MonitoringNotificationDelivery {
        return DB::transaction(function () use ($event) {
            $lockedEvent = MonitoringChangeEvent::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->with([
                    'user:id,name,email',
                    'target:id,user_id,name,url,hostname',
                    'assessment:id,target_id,profile,status,completed_at',
                ])
                ->firstOrFail();

            if (! $lockedEvent->user) {
                throw new RuntimeException(
                    'Monitoring change event owner is unavailable.'
                );
            }

            if (
                ! $lockedEvent->target ||
                $lockedEvent->target->user_id
                    !== $lockedEvent->user_id
            ) {
                throw new RuntimeException(
                    'Monitoring change event target ownership is invalid.'
                );
            }

            $preference =
                MonitoringNotificationPreference::query()
                    ->where(
                        'user_id',
                        $lockedEvent->user_id
                    )
                    ->first();

            $emailEnabled =
                $preference?->email_enabled ?? true;

            $eventTypes =
                $preference?->event_types
                ?? MonitoringNotificationPreference::defaultEventTypes();

            $minimumRiskDelta = (int) (
                $preference?->minimum_risk_delta ?? 1
            );

            if (! $emailEnabled) {
                return null;
            }

            if (
                ! in_array(
                    $lockedEvent->event_type,
                    $eventTypes,
                    true
                )
            ) {
                return null;
            }

            if (
                $lockedEvent->event_type === 'risk_changed' &&
                ! $this->passesRiskThreshold(
                    $lockedEvent,
                    $minimumRiskDelta
                )
            ) {
                return null;
            }

            $recipient = trim(
                (string) $lockedEvent->user->email
            );

            if (
                $recipient === '' ||
                filter_var(
                    $recipient,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new RuntimeException(
                    'Monitoring notification recipient is invalid.'
                );
            }

            /*
             * Payload is a delivery-time immutable snapshot derived
             * exclusively from the persisted monitoring change event
             * and its related persisted records.
             */
            $payload = [
                'version' => 1,
                'change_event' => [
                    'id' => $lockedEvent->id,
                    'event_type' =>
                        $lockedEvent->event_type,
                    'fingerprint' =>
                        $lockedEvent->fingerprint,
                    'detected_at' =>
                        $lockedEvent->detected_at
                            ?->toIso8601String(),
                    'payload' =>
                        $lockedEvent->payload ?? [],
                ],
                'target' => [
                    'id' => $lockedEvent->target->id,
                    'name' => $lockedEvent->target->name,
                    'url' => $lockedEvent->target->url,
                    'hostname' =>
                        $lockedEvent->target->hostname,
                ],
                'assessment' => $lockedEvent->assessment
                    ? [
                        'id' =>
                            $lockedEvent->assessment->id,
                        'profile' =>
                            $lockedEvent->assessment->profile,
                        'status' =>
                            $lockedEvent->assessment->status,
                        'completed_at' =>
                            $lockedEvent->assessment
                                ->completed_at
                                ?->toIso8601String(),
                    ]
                    : null,
            ];

            $delivery =
                MonitoringNotificationDelivery::query()
                    ->firstOrCreate(
                        [
                            'change_event_id' =>
                                $lockedEvent->id,
                            'channel' => 'email',
                        ],
                        [
                            'user_id' =>
                                $lockedEvent->user_id,
                            'status' => 'pending',
                            'recipient' => $recipient,
                            'payload' => $payload,
                            'attempt_count' => 0,
                        ],
                    );

            /*
             * Queue only a newly-created delivery.
             *
             * Dispatch after commit so a worker can never observe
             * a delivery row that later rolls back.
             */
            if ($delivery->wasRecentlyCreated) {
                $deliveryId = $delivery->id;

                DB::afterCommit(
                    static function () use (
                        $deliveryId
                    ): void {
                        SendMonitoringNotification::dispatch(
                            $deliveryId
                        );
                    }
                );
            }

            return $delivery;
        });
    }

    private function passesRiskThreshold(
        MonitoringChangeEvent $event,
        int $minimumRiskDelta
    ): bool {
        $payload = $event->payload ?? [];

        $delta = $payload['risk']['delta_points']
            ?? $payload['delta_points']
            ?? null;

        if (! is_numeric($delta)) {
            /*
             * A malformed risk event must not produce an email
             * claiming a risk change we cannot quantify.
             */
            return false;
        }

        return abs((float) $delta)
            >= $minimumRiskDelta;
    }
}
