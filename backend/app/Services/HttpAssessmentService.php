<?php

namespace App\Services;

use App\Models\Assessment;
use App\Services\Scanner\ScannerExecutionRuntime;
use RuntimeException;

class HttpAssessmentService
{
    public function __construct(
        private readonly FindingPersistenceService $findingPersistence,
        private readonly ScannerRequestFactory $scannerRequestFactory,
        private readonly ScannerResultValidator $scannerResultValidator,
        private readonly ScannerExecutionRuntime $scannerExecutionRuntime
    ) {
    }

public function run(Assessment $assessment): array
    {
        $request = $this->scannerRequestFactory
            ->fromAssessment($assessment);

        $scanResult = $this->scan($request);

        /*
         * Validate the complete untrusted scanner result before the first
         * finding is persisted. This prevents partial persistence when a
         * later finding in the collection is malformed.
         */
        $scanResult = $this->scannerResultValidator->validate(
            $scanResult,
            (string) $assessment->target_id
        );

        $findings = $scanResult['findings'];

        if (! is_array($findings)) {
            throw new RuntimeException(
                'Scanner returned an invalid findings collection.'
            );
        }

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                throw new RuntimeException(
                    'Scanner returned an invalid finding result.'
                );
            }

            $this->findingPersistence->persist(
                $assessment,
                (string) $assessment->target_id,
                $finding
            );
        }

        /*
         * Findings are persisted separately. Do not duplicate evidence-rich
         * finding payloads inside assessments.execution_metadata.
         */
        unset($scanResult['findings']);

        return $scanResult;
    }

    public function scan(array $request): array
    {
        return $this->scannerExecutionRuntime->scan($request);
    }
}
