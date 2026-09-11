<?php

namespace Tests\Feature;

use App\Services\Labs\SqlLabAnalysisService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SqlLabAnalysisServiceTest extends TestCase
{
    private SqlLabAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SqlLabAnalysisService();
    }

    public function test_query_analyzer_never_executes_sql(): void
    {
        $result = $this->service->analyze(
            'query-analyzer',
            'SELECT id, email FROM users WHERE active = true;'
        );

        $this->assertFalse($result['executed']);
        $this->assertContains('SELECT', $result['statement_types']);
    }

    public function test_destructive_query_gets_high_risk_signal(): void
    {
        $result = $this->service->analyze(
            'security-analyzer',
            'DROP TABLE users;'
        );

        $this->assertFalse($result['executed']);
        $this->assertSame('high', $result['risk_level']);

        $ids = array_column($result['signals'], 'id');

        $this->assertContains('destructive-drop', $ids);
    }

    public function test_tautology_pattern_is_detected_defensively(): void
    {
        $result = $this->service->analyze(
            'security-analyzer',
            "SELECT id FROM users WHERE id = 5 OR 1=1"
        );

        $ids = array_column($result['signals'], 'id');

        $this->assertContains('tautology', $ids);
    }

    public function test_parameterization_coach_replaces_literals(): void
    {
        $result = $this->service->analyze(
            'parameterization-coach',
            "SELECT id FROM users WHERE email = 'demo@example.com' AND age = 25"
        );

        $this->assertFalse($result['executed']);
        $this->assertSame(2, $result['parameter_count']);
        $this->assertStringContainsString(
            'email = ?',
            $result['parameterized_template']
        );
        $this->assertStringContainsString(
            'age = ?',
            $result['parameterized_template']
        );
    }

    public function test_risk_report_is_bounded_to_one_hundred(): void
    {
        $result = $this->service->analyze(
            'query-risk-report',
            'DROP TABLE users; TRUNCATE TABLE logs; DELETE FROM accounts;'
        );

        $this->assertLessThanOrEqual(100, $result['risk_score']);
        $this->assertFalse($result['executed']);
    }

    public function test_oversized_input_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->analyze(
            'query-analyzer',
            str_repeat('A', SqlLabAnalysisService::MAX_INPUT_BYTES + 1)
        );
    }

    public function test_data_profiler_has_row_and_column_bounds(): void
    {
        $result = $this->service->analyze(
            'data-profiler',
            "name,age\nAlice,28\nBob,31"
        );

        $this->assertFalse($result['executed']);
        $this->assertSame(2, $result['row_count']);
        $this->assertSame(2, $result['column_count']);
    }
}
