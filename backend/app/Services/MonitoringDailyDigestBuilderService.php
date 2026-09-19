<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\MonitoringChangeEvent;
use App\Models\MonitoringPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

class MonitoringDailyDigestBuilderService
{
    /**
     * Build a factual monitoring snapshot for one user's local day.
     *
     * The snapshot is derived only from persisted monitoring records.
     * It never claims that a target is "safe" or "secure".
     */
    public function build(
        User $user,
        string $digestDate,
        string $timezone = 'UTC',
    ): array {
        $timezone = $this->validatedTimezone($timezone);

        try {
            $localStart = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $digestDate,
                $timezone
            );

            if ($localStart === false) {
                throw new InvalidArgumentException(
                    'Invalid digest date.'
                );
            }
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                'Invalid digest date.',
                previous: $e
            );
        }

        $localEnd = $localStart->addDay();

        $utcStart = $localStart->utc();
        $utcEnd = $localEnd->utc();

        $policies = MonitoringPolicy::query()
            ->with('target:id,user_id,name,url,hostname,status,authorization_confirmed')
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->get()
            ->filter(
                fn (MonitoringPolicy $policy): bool =>
                    $policy->target !== null &&
                    $policy->target->user_id === $user->id
            )
            ->values();

        $targets = [];
        $totals = [
            'monitored_targets' => $policies->count(),
            'completed_scans' => 0,
            'failed_scans' => 0,
            'incomplete_scans' => 0,
            'change_events' => 0,
            'new_findings' => 0,
            'reappeared_findings' => 0,
            'reopened_findings' => 0,
            'no_longer_detected_findings' => 0,
            'risk_changes' => 0,
            'high_findings_detected' => 0,
            'critical_findings_detected' => 0,
        ];

        foreach ($policies as $policy) {
            $target = $policy->target;

            $assessments = Assessment::query()
                ->where('user_id', $user->id)
                ->where('target_id', $target->id)
                ->where('created_at', '>=', $utcStart)
                ->where('created_at', '<', $utcEnd)
                ->orderBy('created_at')
                ->get();

            $completed = $assessments
                ->where('status', 'completed')
                ->values();

            $failed = $assessments
                ->where('status', 'failed')
                ->values();

            $incomplete = $assessments
                ->reject(
                    fn (Assessment $assessment): bool =>
                        in_array(
                            $assessment->status,
                            ['completed', 'failed'],
                            true
                        )
                )
                ->values();

            /*
             * Change events are generated only from completed
             * assessments by ChangeDetectionService.
             */
            $events = MonitoringChangeEvent::query()
                ->where('user_id', $user->id)
                ->where('target_id', $target->id)
                ->where('detected_at', '>=', $utcStart)
                ->where('detected_at', '<', $utcEnd)
                ->orderBy('detected_at')
                ->get();

            $eventCounts = [
                'finding_new' => 0,
                'finding_reappeared' => 0,
                'finding_reopened' => 0,
                'finding_no_longer_detected' => 0,
                'risk_changed' => 0,
            ];

            $high = 0;
            $critical = 0;

            foreach ($events as $event) {
                if (array_key_exists(
                    $event->event_type,
                    $eventCounts
                )) {
                    $eventCounts[$event->event_type]++;
                }

                if (! in_array(
                    $event->event_type,
                    [
                        'finding_new',
                        'finding_reappeared',
                        'finding_reopened',
                    ],
                    true
                )) {
                    continue;
                }

                $severity = strtolower(
                    (string) (
                        $event->payload['finding']['severity']
                        ?? ''
                    )
                );

                if ($severity === 'high') {
                    $high++;
                }

                if ($severity === 'critical') {
                    $critical++;
                }
            }

            $totals['completed_scans'] += $completed->count();
            $totals['failed_scans'] += $failed->count();
            $totals['incomplete_scans'] += $incomplete->count();
            $totals['change_events'] += $events->count();
            $totals['new_findings'] +=
                $eventCounts['finding_new'];
            $totals['reappeared_findings'] +=
                $eventCounts['finding_reappeared'];
            $totals['reopened_findings'] +=
                $eventCounts['finding_reopened'];
            $totals['no_longer_detected_findings'] +=
                $eventCounts['finding_no_longer_detected'];
            $totals['risk_changes'] +=
                $eventCounts['risk_changed'];
            $totals['high_findings_detected'] += $high;
            $totals['critical_findings_detected'] += $critical;

            $latestCompleted = $completed->last();

            $targets[] = [
                'id' => $target->id,
                'name' => $target->name,
                'hostname' => $target->hostname,
                'url' => $target->url,

                'scan_summary' => [
                    'completed' => $completed->count(),
                    'failed' => $failed->count(),
                    'incomplete' => $incomplete->count(),
                ],

                'latest_completed_assessment' =>
                    $latestCompleted
                        ? [
                            'id' => $latestCompleted->id,
                            'profile' =>
                                $latestCompleted->profile,
                            'completed_at' =>
                                $latestCompleted->completed_at
                                    ?->toIso8601String(),
                        ]
                        : null,

                'changes' => $eventCounts,

                'severity_events' => [
                    'high' => $high,
                    'critical' => $critical,
                ],

                /*
                 * We expose only bounded failure facts.
                 * Raw error messages are intentionally excluded
                 * from email snapshots.
                 */
                'monitoring_complete_for_day' =>
                    $failed->isEmpty() &&
                    $incomplete->isEmpty(),

                'had_completed_scan' =>
                    $completed->isNotEmpty(),
            ];
        }

        return [
            'version' => 1,

            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],

            'digest_date' => $digestDate,
            'timezone' => $timezone,

            'window' => [
                'start' => $utcStart->toIso8601String(),
                'end_exclusive' => $utcEnd->toIso8601String(),
            ],

            'totals' => $totals,
            'targets' => $targets,

            'truthfulness' => [
                'source' =>
                    'persisted_monitoring_records',
                'completed_scan_required_for_change_detection' =>
                    true,
                'no_findings_does_not_mean_safe' => true,
                'failed_or_incomplete_scans_disclosed' => true,
            ],

            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function validatedTimezone(string $timezone): string
    {
        $timezone = trim($timezone);

        if (
            $timezone === '' ||
            ! in_array(
                $timezone,
                DateTimeZone::listIdentifiers(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid daily digest timezone.'
            );
        }

        return $timezone;
    }
}
