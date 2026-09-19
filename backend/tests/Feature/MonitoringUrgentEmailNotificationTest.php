<?php

namespace Tests\Feature;

use App\Notifications\MonitoringChangeEmailNotification;
use Tests\TestCase;

class MonitoringUrgentEmailNotificationTest extends TestCase
{
    private function mail(
        string $eventType,
        string $severity
    ) {
        $notification =
            new MonitoringChangeEmailNotification([
                'target' => [
                    'name' => 'Example Target',
                    'hostname' => 'example.test',
                ],
                'change_event' => [
                    'event_type' => $eventType,
                    'detected_at' =>
                        '2026-09-18T12:00:00+00:00',
                    'payload' => [
                        'finding' => [
                            'title' => 'Test finding',
                            'severity' => $severity,
                        ],
                    ],
                ],
            ]);

        return $notification->toMail(
            new \stdClass()
        );
    }

    public function test_critical_new_finding_has_urgent_subject(): void
    {
        $mail = $this->mail(
            'finding_new',
            'critical'
        );

        $this->assertSame(
            'URGENT: Critical security finding detected'
            .' — Example Target',
            $mail->subject
        );

        $this->assertStringContainsString(
            'classified CRITICAL',
            implode(' ', $mail->introLines)
        );
    }

    public function test_high_reappeared_finding_has_urgent_subject(): void
    {
        $mail = $this->mail(
            'finding_reappeared',
            'high'
        );

        $this->assertSame(
            'URGENT: High-severity security finding detected'
            .' — Example Target',
            $mail->subject
        );

        $this->assertStringContainsString(
            'classified HIGH',
            implode(' ', $mail->introLines)
        );
    }

    public function test_medium_finding_keeps_normal_subject(): void
    {
        $mail = $this->mail(
            'finding_new',
            'medium'
        );

        $this->assertSame(
            'New security finding detected'
            .' — Example Target',
            $mail->subject
        );

        $this->assertStringNotContainsString(
            'Priority: Immediate review recommended',
            implode(' ', $mail->introLines)
        );
    }

    public function test_no_longer_detected_is_not_urgent(): void
    {
        $mail = $this->mail(
            'finding_no_longer_detected',
            'critical'
        );

        $this->assertSame(
            'Security finding no longer detected'
            .' — Example Target',
            $mail->subject
        );

        $this->assertStringNotContainsString(
            'URGENT:',
            $mail->subject
        );
    }
}
