<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitoringDailyDigestEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $snapshot,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = (string) (
            $this->snapshot['digest_date'] ?? 'Unknown date'
        );

        $timezone = (string) (
            $this->snapshot['timezone'] ?? 'UTC'
        );

        $totals = is_array(
            $this->snapshot['totals'] ?? null
        )
            ? $this->snapshot['totals']
            : [];

        $targets = is_array(
            $this->snapshot['targets'] ?? null
        )
            ? $this->snapshot['targets']
            : [];

        $completed = (int) (
            $totals['completed_scans'] ?? 0
        );

        $failed = (int) (
            $totals['failed_scans'] ?? 0
        );

        $incomplete = (int) (
            $totals['incomplete_scans'] ?? 0
        );

        $changes = (int) (
            $totals['change_events'] ?? 0
        );

        $high = (int) (
            $totals['high_findings_detected'] ?? 0
        );

        $critical = (int) (
            $totals['critical_findings_detected'] ?? 0
        );

        $mail = (new MailMessage)
            ->subject(
                "CrypticX Lab — Daily Security Monitoring — {$date}"
            )
            ->greeting('Daily Security Monitoring')
            ->line(
                "Reporting period: {$date} ({$timezone})."
            )
            ->line(
                "Completed monitoring scans: {$completed}"
            )
            ->line(
                "Detected monitoring changes: {$changes}"
            )
            ->line(
                "High-severity finding events: {$high}"
            )
            ->line(
                "Critical-severity finding events: {$critical}"
            );

        if ($failed > 0 || $incomplete > 0) {
            $mail->line(
                "Monitoring coverage warning: {$failed} failed "
                ."scan(s) and {$incomplete} incomplete scan(s) "
                .'were recorded during this reporting period.'
            );
        }

        if ($completed === 0) {
            $mail->line(
                'No completed monitoring scans were recorded '
                .'during this reporting period. No security '
                .'conclusion can be drawn from this digest.'
            );
        } elseif ($changes === 0) {
            $mail->line(
                'No material security changes were detected in '
                .'the completed monitoring scans during this '
                .'reporting period. This does not prove that '
                .'the monitored targets are safe or secure.'
            );
        }

        foreach ($targets as $target) {
            if (! is_array($target)) {
                continue;
            }

            $name = trim(
                (string) (
                    $target['name']
                    ?? $target['hostname']
                    ?? 'Monitored target'
                )
            );

            $scan = is_array(
                $target['scan_summary'] ?? null
            )
                ? $target['scan_summary']
                : [];

            $severity = is_array(
                $target['severity_events'] ?? null
            )
                ? $target['severity_events']
                : [];

            $targetCompleted = (int) (
                $scan['completed'] ?? 0
            );

            $targetFailed = (int) (
                $scan['failed'] ?? 0
            );

            $targetIncomplete = (int) (
                $scan['incomplete'] ?? 0
            );

            $targetHigh = (int) (
                $severity['high'] ?? 0
            );

            $targetCritical = (int) (
                $severity['critical'] ?? 0
            );

            $mail->line(
                "{$name}: {$targetCompleted} completed, "
                ."{$targetFailed} failed, "
                ."{$targetIncomplete} incomplete; "
                ."High {$targetHigh}, Critical {$targetCritical}."
            );
        }

        return $mail
            ->action(
                'Open CrypticX Lab',
                rtrim(
                    (string) config('app.url'),
                    '/'
                ).'/monitoring'
            )
            ->line(
                'This email summarizes persisted monitoring '
                .'records only. Sign in to review full findings '
                .'and remediation details.'
            );
    }
}
