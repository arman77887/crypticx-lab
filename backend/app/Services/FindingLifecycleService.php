<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingLifecycle;
use App\Models\Target;
use Illuminate\Support\Facades\DB;

class FindingLifecycleService
{
    /**
     * Synchronize lifecycle state after a successful assessment.
     *
     * Lifecycle identity:
     *   target_id + fingerprint
     *
     * A lifecycle is updated only after the assessment has completed
     * successfully, so a failed/partial scan cannot incorrectly resolve
     * findings.
     */
    public function syncAssessment(Assessment $assessment): array
    {
        return DB::transaction(function () use ($assessment): array {
            $target = Target::query()
                ->whereKey($assessment->target_id)
                ->lockForUpdate()
                ->firstOrFail();

            $findings = Finding::query()
                ->where('assessment_id', $assessment->id)
                ->where('target_id', $target->id)
                ->whereNotNull('fingerprint')
                ->orderBy('created_at')
                ->get();

            $seenFingerprints = [];
            $created = 0;
            $updated = 0;
            $reopened = 0;

            foreach ($findings as $finding) {
                $fingerprint = $finding->fingerprint;

                // Defensive guard: one assessment must count once per
                // logical fingerprint even if duplicate rows somehow exist.
                if (isset($seenFingerprints[$fingerprint])) {
                    continue;
                }

                $seenFingerprints[$fingerprint] = true;

                $lifecycle = FindingLifecycle::query()
                    ->where('target_id', $target->id)
                    ->where('fingerprint', $fingerprint)
                    ->lockForUpdate()
                    ->first();

                if ($lifecycle === null) {
                    FindingLifecycle::create([
                        'target_id' => $target->id,
                        'fingerprint' => $fingerprint,
                        'type' => $finding->type,
                        'title' => $finding->title,
                        'severity' => $finding->severity,
                        'confidence' => $finding->confidence,
                        'status' => $finding->status ?: 'open',
                        'first_seen_at' => $finding->created_at,
                        'last_seen_at' => $finding->created_at,
                        'occurrence_count' => 1,
                        'first_assessment_id' => $assessment->id,
                        'last_assessment_id' => $assessment->id,
                        'last_finding_id' => $finding->id,
                    ]);

                    $created++;

                    continue;
                }

                $wasResolved = $lifecycle->status === 'resolved';

                $nextStatus = match (true) {
                    $wasResolved => 'reopened',
                    $lifecycle->status === 'confirmed' => 'confirmed',
                    $lifecycle->status === 'reopened' => 'reopened',
                    default => 'open',
                };

                $finding->update([
                    'status' => $nextStatus,
                ]);

                $lifecycle->update([
                    'type' => $finding->type,
                    'title' => $finding->title,
                    'severity' => $finding->severity,
                    'confidence' => $finding->confidence,
                    'status' => $nextStatus,
                    'last_seen_at' => $finding->created_at,
                    'last_assessment_id' => $assessment->id,
                    'last_finding_id' => $finding->id,
                    'occurrence_count' => $lifecycle->occurrence_count + 1,
                    'reopened_at' => $wasResolved
                        ? $finding->created_at
                        : $lifecycle->reopened_at,
                    'resolved_at' => $wasResolved
                        ? null
                        : $lifecycle->resolved_at,
                ]);

                if ($wasResolved) {
                    $reopened++;
                } else {
                    $updated++;
                }
            }

            /*
             * Absence from one successful assessment does not prove that
             * a vulnerability has been remediated.
             *
             * "resolved" remains an explicit workflow state controlled by
             * the Finding API. Assessment comparison represents absence as
             * "no_longer_detected".
             */
            $previouslyObserved = FindingLifecycle::query()
                ->where('target_id', $target->id)
                ->where('last_assessment_id', '!=', $assessment->id)
                ->pluck('fingerprint');

            $noLongerDetected = $previouslyObserved
                ->reject(
                    fn (string $fingerprint) =>
                        isset($seenFingerprints[$fingerprint])
                )
                ->values();

            return [
                'assessment_id' => $assessment->id,
                'target_id' => $target->id,
                'findings_observed' => count($seenFingerprints),
                'created' => $created,
                'updated' => $updated,
                'reopened' => $reopened,
                'resolved' => 0,
                'no_longer_detected' =>
                    $noLongerDetected->count(),
                'semantics' => [
                    'absence_means' => 'no_longer_detected',
                    'absence_does_not_prove' => 'resolved',
                    'resolved_requires_explicit_workflow' => true,
                ],
            ];
        });
    }
}
