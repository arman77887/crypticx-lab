<?php

namespace App\Services;

use App\Models\FindingLifecycle;

class RiskScoringService
{
    public function score(FindingLifecycle $lifecycle): array
    {
        $lifecycle->loadMissing('target');

        $severity = strtolower((string) $lifecycle->severity);
        $confidence = strtolower((string) $lifecycle->confidence);
        $status = strtolower((string) $lifecycle->status);

        $metadata = is_array($lifecycle->target?->metadata)
            ? $lifecycle->target->metadata
            : [];

        $assetImportance = strtolower(
            (string) ($metadata['asset_importance'] ?? 'medium')
        );

        $severityScore = match ($severity) {
            'critical' => 40,
            'high' => 32,
            'medium' => 22,
            'low' => 10,
            'info', 'informational' => 4,
            default => 10,
        };

        $confidenceScore = match ($confidence) {
            'high' => 20,
            'medium' => 13,
            'low' => 6,
            default => 10,
        };

        $occurrences = max(1, (int) $lifecycle->occurrence_count);

        $recurrenceScore = match (true) {
            $occurrences >= 10 => 15,
            $occurrences >= 5 => 12,
            $occurrences >= 3 => 9,
            $occurrences >= 2 => 5,
            default => 2,
        };

        $lifecycleScore = match ($status) {
            'confirmed' => 10,
            'reopened' => 9,
            'open' => 7,
            'resolved' => 0,
            default => 5,
        };

        $assetImportanceScore = match ($assetImportance) {
            'critical' => 15,
            'high' => 12,
            'medium' => 8,
            'low' => 4,
            default => 8,
        };

        $score = min(
            100,
            max(
                0,
                $severityScore
                + $confidenceScore
                + $recurrenceScore
                + $lifecycleScore
                + $assetImportanceScore
            )
        );

        $level = match (true) {
            $score >= 85 => 'critical',
            $score >= 70 => 'high',
            $score >= 45 => 'medium',
            $score >= 20 => 'low',
            default => 'informational',
        };

        return [
            'score' => $score,
            'level' => $level,
            'components' => [
                'severity' => [
                    'value' => $severity,
                    'score' => $severityScore,
                    'max' => 40,
                ],
                'confidence' => [
                    'value' => $confidence,
                    'score' => $confidenceScore,
                    'max' => 20,
                ],
                'recurrence' => [
                    'occurrence_count' => $occurrences,
                    'score' => $recurrenceScore,
                    'max' => 15,
                ],
                'lifecycle' => [
                    'status' => $status,
                    'score' => $lifecycleScore,
                    'max' => 10,
                ],
                'asset_importance' => [
                    'value' => $assetImportance,
                    'score' => $assetImportanceScore,
                    'max' => 15,
                ],
            ],
        ];
    }
}
