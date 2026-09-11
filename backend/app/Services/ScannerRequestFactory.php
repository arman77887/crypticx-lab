<?php

namespace App\Services;

use App\Models\Assessment;
use RuntimeException;

class ScannerRequestFactory
{
    public const CONTRACT_VERSION = 1;

    public function fromAssessment(Assessment $assessment): array
    {
        $target = $assessment->target;

        if (! $target) {
            throw new RuntimeException(
                'Assessment target is unavailable.'
            );
        }

        if (! $target->authorization_confirmed) {
            throw new RuntimeException(
                'Target authorization is not confirmed.'
            );
        }

        return [
            'contract_version' => self::CONTRACT_VERSION,
            'assessment_id' => (string) $assessment->id,
            'target' => [
                'id' => (string) $target->id,
                'url' => (string) $target->url,
                'hostname' => (string) $target->hostname,
                'scheme' => (string) $target->scheme,
                'port' => (int) $target->port,
            ],
        ];
    }
}
