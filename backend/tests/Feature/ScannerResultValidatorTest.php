<?php

namespace Tests\Feature;

use App\Services\ScannerResultValidator;
use RuntimeException;
use Tests\TestCase;

class ScannerResultValidatorTest extends TestCase
{
    private function finding(
        string $targetId = 'target-1'
    ): array {
        $type = 'security_header';
        $title = 'Content-Security-Policy header missing';

        $fingerprint = hash(
            'sha256',
            json_encode(
                [
                    'target_id' => $targetId,
                    'type' => $type,
                    'title' => $title,
                ],
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            )
        );

        return [
            'fingerprint' => $fingerprint,
            'type' => $type,
            'title' => $title,
            'description' => 'Description',
            'severity' => 'medium',
            'confidence' => 'high',
            'evidence_data' => [
                'header' => 'content-security-policy',
            ],
            'remediation' => 'Configure CSP.',
        ];
    }

    private function scannerResult(array $findings): array
    {
        return [
            'engine_version' => 'http-assessment-v3',
            'finding_count' => count($findings),
            'findings' => $findings,
        ];
    }

    public function test_valid_result_is_accepted(): void
    {
        $validator = app(ScannerResultValidator::class);

        $validated = $validator->validate(
            $this->scannerResult([$this->finding()]),
            'target-1'
        );

        $this->assertCount(1, $validated['findings']);
        $this->assertSame(
            1,
            $validated['finding_count']
        );
    }

    public function test_forged_fingerprint_is_rejected(): void
    {
        $finding = $this->finding();
        $finding['fingerprint'] = str_repeat('a', 64);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner finding fingerprint does not match its identity.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult([$finding]),
            'target-1'
        );
    }

    public function test_invalid_severity_is_rejected(): void
    {
        $finding = $this->finding();
        $finding['severity'] = 'extreme';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner finding severity is invalid.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult([$finding]),
            'target-1'
        );
    }

    public function test_inconsistent_finding_count_is_rejected(): void
    {
        $result = $this->scannerResult([$this->finding()]);
        $result['finding_count'] = 99;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner result finding count is inconsistent.'
        );

        app(ScannerResultValidator::class)->validate(
            $result,
            'target-1'
        );
    }

    public function test_duplicate_fingerprints_are_rejected(): void
    {
        $finding = $this->finding();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Scanner result contains duplicate finding fingerprints.'
        );

        app(ScannerResultValidator::class)->validate(
            $this->scannerResult([$finding, $finding]),
            'target-1'
        );
    }
}
