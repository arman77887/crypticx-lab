<?php

namespace App\Services\Labs;

use RuntimeException;

final class SqlLabAnalysisService
{
    public const MAX_INPUT_BYTES = 200_000;

    public function analyze(string $tool, string $input): array
    {
        if (strlen($input) > self::MAX_INPUT_BYTES) {
            throw new RuntimeException('SQL input exceeds the 200 KB limit.');
        }

        $input = trim($input);

        if ($input === '') {
            throw new RuntimeException('SQL input cannot be empty.');
        }

        return match ($tool) {
            'query-analyzer' => $this->queryAnalyzer($input),
            'schema-inspector' => $this->schemaInspector($input),
            'sql-formatter' => $this->sqlFormatter($input),
            'data-profiler' => $this->dataProfiler($input),

            'security-analyzer' => $this->securityAnalyzer($input),
            'parameterization-coach' => $this->parameterizationCoach($input),
            'query-risk-report' => $this->queryRiskReport($input),

            default => throw new RuntimeException('Unknown SQL Lab tool.'),
        };
    }

    private function queryAnalyzer(string $sql): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);

        preg_match_all(
            '/\b(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|TRUNCATE|WITH|MERGE|GRANT|REVOKE)\b/i',
            $sql,
            $matches
        );

        $statementTypes = array_values(array_unique(
            array_map('strtoupper', $matches[1] ?? [])
        ));

        $warnings = $this->securitySignals($sql);

        if (preg_match('/\bSELECT\s+\*/i', $sql)) {
            $warnings[] = [
                'id' => 'select-star',
                'severity' => 'info',
                'message' => 'SELECT * detected. Explicit columns are usually preferable.',
            ];
        }

        return [
            'mode' => 'static-analysis-only',
            'normalized_sql' => $normalized,
            'statement_types' => $statementTypes,
            'statement_count' => $this->statementCount($sql),
            'character_count' => mb_strlen($sql),
            'contains_where' => preg_match('/\bWHERE\b/i', $sql) === 1,
            'contains_join' => preg_match('/\bJOIN\b/i', $sql) === 1,
            'contains_subquery' => preg_match('/\(\s*SELECT\b/i', $sql) === 1,
            'warnings' => $warnings,
            'warning_count' => count($warnings),
            'executed' => false,
        ];
    }

    private function schemaInspector(string $sql): array
    {
        preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["`]?([a-zA-Z0-9_.-]+)["`]?\s*\((.*?)\)\s*;?/is',
            $sql,
            $tables,
            PREG_SET_ORDER
        );

        $output = [];

        foreach (array_slice($tables, 0, 50) as $table) {
            $name = $table[1];
            $body = $table[2];

            $parts = preg_split('/,(?![^(]*\))/', $body) ?: [];

            $columns = [];
            $constraints = [];

            foreach (array_slice($parts, 0, 250) as $part) {
                $part = trim($part);

                if ($part === '') {
                    continue;
                }

                if (preg_match(
                    '/^(PRIMARY\s+KEY|FOREIGN\s+KEY|UNIQUE|CONSTRAINT|CHECK)\b/i',
                    $part
                )) {
                    $constraints[] = mb_substr($part, 0, 2000);
                    continue;
                }

                if (preg_match(
                    '/^["`]?([a-zA-Z0-9_-]+)["`]?\s+([a-zA-Z0-9_]+(?:\s*\([^)]*\))?)(.*)$/is',
                    $part,
                    $column
                )) {
                    $columns[] = [
                        'name' => $column[1],
                        'type' => trim($column[2]),
                        'nullable' => ! preg_match('/\bNOT\s+NULL\b/i', $column[3]),
                        'primary_key' => preg_match('/\bPRIMARY\s+KEY\b/i', $column[3]) === 1,
                        'unique' => preg_match('/\bUNIQUE\b/i', $column[3]) === 1,
                    ];
                }
            }

            $output[] = [
                'table' => $name,
                'column_count' => count($columns),
                'columns' => $columns,
                'constraints' => $constraints,
            ];
        }

        return [
            'mode' => 'ddl-static-inspection',
            'table_count' => count($output),
            'tables' => $output,
            'executed' => false,
        ];
    }

    private function sqlFormatter(string $sql): array
    {
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'GROUP BY', 'ORDER BY',
            'HAVING', 'LIMIT', 'OFFSET', 'INSERT INTO', 'VALUES',
            'UPDATE', 'SET', 'DELETE FROM', 'CREATE TABLE',
            'ALTER TABLE', 'DROP TABLE', 'LEFT JOIN', 'RIGHT JOIN',
            'INNER JOIN', 'FULL JOIN', 'JOIN', 'ON', 'UNION',
            'UNION ALL', 'WITH', 'RETURNING',
        ];

        $formatted = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);

        foreach ($keywords as $keyword) {
            $formatted = preg_replace(
                '/\s+\b' . preg_quote($keyword, '/') . '\b/i',
                "\n{$keyword}",
                $formatted
            ) ?? $formatted;
        }

        $formatted = preg_replace(
            '/\s*,\s*/',
            ",\n    ",
            $formatted
        ) ?? $formatted;

        return [
            'mode' => 'static-formatting-only',
            'formatted_sql' => trim($formatted),
            'original_length' => mb_strlen($sql),
            'formatted_length' => mb_strlen(trim($formatted)),
            'executed' => false,
        ];
    }

    private function dataProfiler(string $input): array
    {
        $lines = preg_split('/\R/', trim($input)) ?: [];

        if (count($lines) > 10_000) {
            throw new RuntimeException('Data profile is limited to 10,000 lines.');
        }

        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = str_getcsv($line, ',', '"', '');
        }

        if ($rows === []) {
            throw new RuntimeException('No delimited rows could be detected.');
        }

        $header = array_shift($rows);

        if (! is_array($header) || $header === []) {
            throw new RuntimeException('Could not detect column headers.');
        }

        if (count($header) > 250) {
            throw new RuntimeException('Data profile is limited to 250 columns.');
        }

        $columns = [];

        foreach ($header as $index => $name) {
            $values = [];

            foreach ($rows as $row) {
                $values[] = $row[$index] ?? null;
            }

            $nonEmpty = array_values(array_filter(
                $values,
                fn ($value) =>
                    $value !== null &&
                    trim((string) $value) !== ''
            ));

            $numericCount = count(array_filter(
                $nonEmpty,
                fn ($value) => is_numeric($value)
            ));

            $columns[] = [
                'name' => trim((string) $name),
                'row_count' => count($values),
                'non_empty_count' => count($nonEmpty),
                'null_or_empty_count' => count($values) - count($nonEmpty),
                'unique_count' => count(array_unique(
                    array_map('strval', $nonEmpty)
                )),
                'numeric_count' => $numericCount,
                'inferred_type' =>
                    count($nonEmpty) > 0 &&
                    $numericCount === count($nonEmpty)
                        ? 'numeric'
                        : 'text/mixed',
            ];
        }

        return [
            'mode' => 'csv-like-text-profile',
            'row_count' => count($rows),
            'column_count' => count($header),
            'columns' => $columns,
            'executed' => false,
        ];
    }

    private function securityAnalyzer(string $sql): array
    {
        $signals = $this->securitySignals($sql);
        $score = $this->riskScore($signals);

        return [
            'mode' => 'defensive-static-security-analysis',
            'risk_score' => $score,
            'risk_level' => $this->riskLevel($score),
            'signals' => $signals,
            'signal_count' => count($signals),
            'guidance' => [
                'Use parameterized queries or prepared statements for untrusted values.',
                'Keep database accounts least-privileged.',
                'Do not expose database errors directly to end users.',
                'Validate business rules independently from SQL syntax.',
            ],
            'executed' => false,
        ];
    }

    private function parameterizationCoach(string $sql): array
    {
        $parameters = [];
        $counter = 0;

        $template = preg_replace_callback(
            "/'(?:''|[^'])*'|\b\d+(?:\.\d+)?\b/",
            function (array $match) use (&$parameters, &$counter): string {
                $counter++;

                $raw = $match[0];

                if (str_starts_with($raw, "'")) {
                    $value = substr($raw, 1, -1);
                    $value = str_replace("''", "'", $value);
                    $type = 'string';
                } else {
                    $value = $raw;
                    $type = str_contains($raw, '.')
                        ? 'numeric'
                        : 'integer';
                }

                $parameters[] = [
                    'position' => $counter,
                    'type' => $type,
                    'example_value' => mb_substr((string) $value, 0, 200),
                ];

                return '?';
            },
            $sql
        );

        if (! is_string($template)) {
            throw new RuntimeException('Could not generate parameterization guidance.');
        }

        return [
            'mode' => 'parameterization-guidance-only',
            'parameterized_template' => $template,
            'parameter_count' => count($parameters),
            'parameters' => $parameters,
            'recommendation' =>
                'Bind values through your database driver or ORM. Do not build SQL by concatenating untrusted input.',
            'executed' => false,
        ];
    }

    private function queryRiskReport(string $sql): array
    {
        $signals = $this->securitySignals($sql);
        $score = $this->riskScore($signals);

        $categories = [
            'destructive' => 0,
            'injection-pattern' => 0,
            'authorization-impact' => 0,
            'data-exposure' => 0,
        ];

        foreach ($signals as $signal) {
            $category = $signal['category'] ?? null;

            if (is_string($category) && array_key_exists($category, $categories)) {
                $categories[$category]++;
            }
        }

        return [
            'mode' => 'static-query-risk-report',
            'risk_score' => $score,
            'risk_level' => $this->riskLevel($score),
            'statement_count' => $this->statementCount($sql),
            'categories' => $categories,
            'signals' => $signals,
            'recommendations' => $this->recommendationsFor($signals),
            'executed' => false,
        ];
    }

    private function securitySignals(string $sql): array
    {
        $signals = [];

        $this->addSignal(
            $signals,
            preg_match('/\b(UPDATE|DELETE)\b/i', $sql) === 1 &&
                preg_match('/\bWHERE\b/i', $sql) !== 1,
            'missing-where',
            'high',
            'authorization-impact',
            'UPDATE or DELETE appears without a WHERE clause.'
        );

        $this->addSignal(
            $signals,
            preg_match('/\bDROP\s+(TABLE|DATABASE|SCHEMA)\b/i', $sql) === 1,
            'destructive-drop',
            'high',
            'destructive',
            'Destructive DROP statement detected.'
        );

        $this->addSignal(
            $signals,
            preg_match('/\bTRUNCATE\b/i', $sql) === 1,
            'destructive-truncate',
            'high',
            'destructive',
            'TRUNCATE statement detected.'
        );

        $this->addSignal(
            $signals,
            preg_match('/\bUNION\s+(?:ALL\s+)?SELECT\b/i', $sql) === 1,
            'union-select',
            'medium',
            'injection-pattern',
            'UNION SELECT pattern detected. Review how the query is constructed and parameterized.'
        );

        $this->addSignal(
            $signals,
            preg_match('/\bOR\s+1\s*=\s*1\b/i', $sql) === 1 ||
                preg_match('/\bAND\s+1\s*=\s*1\b/i', $sql) === 1,
            'tautology',
            'medium',
            'injection-pattern',
            'A tautology-like condition was detected.'
        );

        $this->addSignal(
            $signals,
            preg_match('/(--|\/\*|\*\/|#)/', $sql) === 1,
            'sql-comments',
            'info',
            'injection-pattern',
            'SQL comment syntax was detected.'
        );

        $this->addSignal(
            $signals,
            $this->statementCount($sql) > 1,
            'multiple-statements',
            'medium',
            'injection-pattern',
            'Multiple SQL statements were detected. Disable stacked statements where they are not required.'
        );

        $this->addSignal(
            $signals,
            preg_match('/\bSELECT\s+\*/i', $sql) === 1,
            'broad-select',
            'info',
            'data-exposure',
            'SELECT * may expose more columns than the application needs.'
        );

        $this->addSignal(
            $signals,
            preg_match('/\b(GRANT|REVOKE|ALTER\s+USER|CREATE\s+USER)\b/i', $sql) === 1,
            'privilege-management',
            'high',
            'authorization-impact',
            'Database privilege-management statement detected.'
        );

        return $signals;
    }

    private function addSignal(
        array &$signals,
        bool $condition,
        string $id,
        string $severity,
        string $category,
        string $message
    ): void {
        if (! $condition) {
            return;
        }

        $signals[] = [
            'id' => $id,
            'severity' => $severity,
            'category' => $category,
            'message' => $message,
        ];
    }

    private function statementCount(string $sql): int
    {
        $trimmed = trim($sql);

        if ($trimmed === '') {
            return 0;
        }

        $parts = preg_split('/;+(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $trimmed);

        if (! is_array($parts)) {
            return 1;
        }

        return max(
            1,
            count(array_filter(
                array_map('trim', $parts),
                fn (string $part) => $part !== ''
            ))
        );
    }

    private function riskScore(array $signals): int
    {
        $weights = [
            'info' => 5,
            'low' => 15,
            'medium' => 35,
            'high' => 70,
            'critical' => 100,
        ];

        $score = 0;

        foreach ($signals as $signal) {
            $severity = $signal['severity'] ?? 'info';
            $score += $weights[$severity] ?? 5;
        }

        return min(100, $score);
    }

    private function riskLevel(int $score): string
    {
        return match (true) {
            $score >= 70 => 'high',
            $score >= 35 => 'medium',
            $score >= 15 => 'low',
            default => 'informational',
        };
    }

    private function recommendationsFor(array $signals): array
    {
        $recommendations = [
            'Use prepared statements or ORM parameter binding for untrusted values.',
        ];

        $ids = array_column($signals, 'id');

        if (in_array('missing-where', $ids, true)) {
            $recommendations[] =
                'Require an explicit scope or WHERE condition before destructive data modification.';
        }

        if (
            in_array('destructive-drop', $ids, true) ||
            in_array('destructive-truncate', $ids, true)
        ) {
            $recommendations[] =
                'Separate schema-administration privileges from normal application credentials.';
        }

        if (
            in_array('union-select', $ids, true) ||
            in_array('tautology', $ids, true) ||
            in_array('multiple-statements', $ids, true)
        ) {
            $recommendations[] =
                'Review every location where application input is concatenated into SQL.';
        }

        if (in_array('broad-select', $ids, true)) {
            $recommendations[] =
                'Return only the columns required by the application workflow.';
        }

        return array_values(array_unique($recommendations));
    }
}
