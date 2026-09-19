<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitoringChangeEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        public array $snapshot
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->snapshot['change_event'] ?? [];
        $target = $this->snapshot['target'] ?? [];

        $eventType = (string) (
            $event['event_type'] ?? 'monitoring_change'
        );

        $targetName = (string) (
            $target['name']
            ?? $target['hostname']
            ?? 'Monitored target'
        );

        $finding = $event['payload']['finding'] ?? null;

        $severity = is_array($finding)
            ? strtolower(trim(
                (string) ($finding['severity'] ?? '')
            ))
            : '';

        $urgentFindingEvent = in_array(
            $eventType,
            [
                'finding_new',
                'finding_reappeared',
                'finding_reopened',
            ],
            true
        );

        $subject = match (true) {
            $urgentFindingEvent
                && $severity === 'critical' =>
                'URGENT: Critical security finding detected',

            $urgentFindingEvent
                && $severity === 'high' =>
                'URGENT: High-severity security finding detected',

            $eventType === 'finding_new' =>
                'New security finding detected',

            $eventType === 'finding_reappeared' =>
                'Security finding detected again',

            $eventType === 'finding_no_longer_detected' =>
                'Security finding no longer detected',

            $eventType === 'finding_reopened' =>
                'Security finding reopened',

            $eventType === 'risk_changed' =>
                'Security risk score changed',

            default =>
                'Monitoring change detected',
        };

        $message = (new MailMessage())
            ->subject($subject.' — '.$targetName)
            ->greeting('CrypticX Lab Monitoring Alert')
            ->line(
                'A monitoring change was detected for '
                .$targetName.'.'
            )
            ->line(
                'Event: '
                .$this->humanizeEventType($eventType)
            );

        if (! empty($target['hostname'])) {
            $message->line(
                'Target: '.$target['hostname']
            );
        }

        if ($eventType === 'risk_changed') {
            $risk = $event['payload']['risk'] ?? [];

            if (
                isset($risk['delta_points'])
                && is_numeric($risk['delta_points'])
            ) {
                $message->line(
                    'Risk change: '
                    .$this->formatSignedNumber(
                        (float) $risk['delta_points']
                    )
                    .' points'
                );
            }

            if (! empty($risk['trend'])) {
                $message->line(
                    'Trend: '.ucfirst(
                        (string) $risk['trend']
                    )
                );
            }
        }

        if (
            $urgentFindingEvent
            && in_array(
                $severity,
                ['high', 'critical'],
                true
            )
        ) {
            $message->line(
                'Priority: Immediate review recommended because '
                .'this persisted monitoring event is classified '
                .strtoupper($severity).'.'
            );
        }

        if (is_array($finding)) {
            if (! empty($finding['title'])) {
                $message->line(
                    'Finding: '.$finding['title']
                );
            }

            if (! empty($finding['severity'])) {
                $message->line(
                    'Severity: '.ucfirst(
                        (string) $finding['severity']
                    )
                );
            }
        }

        if (! empty($event['detected_at'])) {
            $message->line(
                'Detected at: '.$event['detected_at']
            );
        }

        /*
         * Do not include raw evidence, credentials, cookies,
         * headers, stack traces or provider diagnostics in email.
         */
        return $message->line(
            'Sign in to CrypticX Lab to review the complete monitoring details.'
        );
    }

    private function humanizeEventType(
        string $eventType
    ): string {
        return ucwords(
            str_replace('_', ' ', $eventType)
        );
    }

    private function formatSignedNumber(
        float $value
    ): string {
        $formatted = rtrim(
            rtrim(
                number_format($value, 2, '.', ''),
                '0'
            ),
            '.'
        );

        return $value > 0
            ? '+'.$formatted
            : $formatted;
    }
}
