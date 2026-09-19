<?php

namespace App\Jobs;

use App\Models\MonitoringDailyDigestDelivery;
use App\Notifications\MonitoringDailyDigestEmailNotification;
use App\Services\MonitoringMailTransportGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendMonitoringDailyDigest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $deliveryId,
    ) {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(
        MonitoringMailTransportGuard $transportGuard,
    ): void {
        $delivery =
            MonitoringDailyDigestDelivery::query()
                ->find($this->deliveryId);

        if (! $delivery || $delivery->status === 'sent') {
            return;
        }

        $recipient = trim(
            (string) $delivery->recipient
        );

        if (
            $recipient === '' ||
            filter_var(
                $recipient,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $delivery->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_class' =>
                    'invalid_recipient',
            ])->save();

            return;
        }

        $transportGuard->assertDeliveryCapable();

        $delivery->forceFill([
            'status' => 'processing',
            'processing_at' => now(),
            'failed_at' => null,
            'failure_class' => null,
            'attempt_count' =>
                ((int) $delivery->attempt_count) + 1,
        ])->save();

        try {
            Notification::route('mail', $recipient)
                ->notify(
                    new MonitoringDailyDigestEmailNotification(
                        is_array($delivery->payload)
                            ? $delivery->payload
                            : []
                    )
                );

            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'failure_class' => null,
            ])->save();
        } catch (Throwable $e) {
            $delivery->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_class' => $e::class,
            ])->save();

            throw $e;
        }
    }
}
