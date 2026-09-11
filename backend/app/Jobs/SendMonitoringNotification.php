<?php

namespace App\Jobs;

use App\Models\MonitoringNotificationDelivery;
use App\Notifications\MonitoringChangeEmailNotification;
use App\Services\MonitoringMailTransportGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class SendMonitoringNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [
        60,
        300,
        900,
    ];

    public function __construct(
        public string $deliveryId
    ) {
    }

    public function handle(
        MonitoringMailTransportGuard $transportGuard
    ): void {
        $delivery = DB::transaction(
            function (): ?MonitoringNotificationDelivery {
                $locked =
                    MonitoringNotificationDelivery::query()
                        ->whereKey($this->deliveryId)
                        ->lockForUpdate()
                        ->first();

                if (! $locked) {
                    return null;
                }

                /*
                 * A successful provider handoff is terminal.
                 * Never intentionally send it again.
                 */
                if ($locked->status === 'sent') {
                    return null;
                }

                if ($locked->channel !== 'email') {
                    throw new RuntimeException(
                        'Unsupported monitoring notification channel.'
                    );
                }

                if (
                    ! in_array(
                        $locked->status,
                        ['pending', 'failed'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Monitoring notification delivery is not sendable.'
                    );
                }

                $locked->update([
                    'status' => 'processing',
                    'attempt_count' =>
                        $locked->attempt_count + 1,
                    'processing_at' => now(),
                    'failed_at' => null,
                    'failure_class' => null,
                ]);

                return $locked->fresh();
            }
        );

        if (! $delivery) {
            return;
        }

        try {
            /*
             * Critical invariant:
             * log/array/unsafe composite transports must never
             * result in a "sent" delivery.
             */
            $transportGuard->assertDeliveryCapable();

            $recipient = trim(
                (string) $delivery->recipient
            );

            if (
                $recipient === ''
                || filter_var(
                    $recipient,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new RuntimeException(
                    'Monitoring notification recipient is invalid.'
                );
            }

            $snapshot = is_array($delivery->payload)
                ? $delivery->payload
                : [];

            Notification::route(
                'mail',
                $recipient
            )->notify(
                new MonitoringChangeEmailNotification(
                    $snapshot
                )
            );

            MonitoringNotificationDelivery::query()
                ->whereKey($delivery->id)
                ->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'processing_at' => null,
                    'failed_at' => null,
                    'failure_class' => null,
                ]);
        } catch (Throwable $e) {
            /*
             * Store only bounded exception classification.
             * Never persist SMTP responses, credentials,
             * provider bodies or raw exception messages.
             */
            MonitoringNotificationDelivery::query()
                ->whereKey($delivery->id)
                ->update([
                    'status' => 'failed',
                    'processing_at' => null,
                    'failed_at' => now(),
                    'failure_class' => mb_substr(
                        get_class($e),
                        0,
                        255
                    ),
                ]);

            throw $e;
        }
    }
}
