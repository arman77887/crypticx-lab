<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Finding;

class FindingPersistenceService
{
    public function persist(
        Assessment $assessment,
        string $targetId,
        array $finding
    ): Finding {
        return Finding::query()->firstOrCreate(
            [
                'assessment_id' => $assessment->id,
                'fingerprint' => $finding['fingerprint'],
            ],
            [
                'target_id' => $targetId,
                'type' => $finding['type'],
                'title' => $finding['title'],
                'description' => $finding['description'],
                'severity' => $finding['severity'],
                'confidence' => $finding['confidence'],
                'evidence' => json_encode(
                    $finding['evidence_data'],
                    JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                ),
                'evidence_data' =>
                    $finding['evidence_data'],
                'remediation' =>
                    $finding['remediation'],
                'status' => 'open',
            ]
        );
    }
}
