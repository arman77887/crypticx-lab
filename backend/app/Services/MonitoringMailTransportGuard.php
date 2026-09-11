<?php

namespace App\Services;

use RuntimeException;

class MonitoringMailTransportGuard
{
    private const NON_DELIVERY_TRANSPORTS = [
        'log',
        'array',
        'null',
    ];

    /**
     * Assert that the configured default mailer represents
     * an actual delivery-capable transport.
     *
     * This prevents CrypticX Lab from marking a monitoring
     * notification as "sent" when Laravel only logged or
     * captured the message in memory.
     */
    public function assertDeliveryCapable(): void
    {
        $mailer = (string) config('mail.default');

        if ($mailer === '') {
            throw new RuntimeException(
                'Monitoring email transport is not configured.'
            );
        }

        $this->assertMailerDeliveryCapable(
            $mailer,
            []
        );
    }

    private function assertMailerDeliveryCapable(
        string $mailer,
        array $visited
    ): void {
        if (in_array($mailer, $visited, true)) {
            throw new RuntimeException(
                'Monitoring email transport configuration contains a cycle.'
            );
        }

        $configuration = config(
            'mail.mailers.'.$mailer
        );

        if (! is_array($configuration)) {
            throw new RuntimeException(
                'Monitoring email mailer configuration is unavailable.'
            );
        }

        $transport = (string) (
            $configuration['transport'] ?? ''
        );

        if ($transport === '') {
            throw new RuntimeException(
                'Monitoring email transport is undefined.'
            );
        }

        if (
            in_array(
                $transport,
                self::NON_DELIVERY_TRANSPORTS,
                true
            )
        ) {
            throw new RuntimeException(
                'Monitoring email requires a real delivery transport.'
            );
        }

        /*
         * Composite transports are accepted only when every possible
         * child mailer is itself delivery-capable. This deliberately
         * rejects configurations such as smtp -> log failover.
         */
        if (
            $transport === 'failover'
            || $transport === 'roundrobin'
        ) {
            $children = $configuration['mailers'] ?? [];

            if (
                ! is_array($children)
                || $children === []
            ) {
                throw new RuntimeException(
                    'Monitoring composite email transport has no mailers.'
                );
            }

            $visited[] = $mailer;

            foreach ($children as $child) {
                if (! is_string($child) || $child === '') {
                    throw new RuntimeException(
                        'Monitoring composite email transport is invalid.'
                    );
                }

                $this->assertMailerDeliveryCapable(
                    $child,
                    $visited
                );
            }
        }
    }
}
