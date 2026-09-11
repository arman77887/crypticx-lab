<?php

namespace App\Services\Labs;

use JsonException;
use RuntimeException;
use Throwable;

final class DataLabAnalysisService
{
    public const MAX_INPUT_BYTES = 500_000;

    private const MAX_LINES = 20_000;
    private const MAX_MATCHES_PER_TYPE = 100;
    private const MAX_JSON_DEPTH = 64;

    public function analyze(string $tool, string $input): array
    {
        if (strlen($input) > self::MAX_INPUT_BYTES) {
            throw new RuntimeException('Data input exceeds the 500 KB limit.');
        }

        if (trim($input) === '') {
            throw new RuntimeException('Data input cannot be empty.');
        }

        return match ($tool) {
            'csv-analyzer' => $this->csvAnalyzer($input),
            'json-inspector' => $this->jsonInspector($input),
            'data-cleaner' => $this->dataCleaner($input),
            'pattern-analysis' => $this->patternAnalysis($input),
            'log-analyzer' => $this->logAnalyzer($input),
            'http-inspector' => $this->httpInspector($input),
            'encoding-studio' => $this->encodingStudio($input),
            'hash-inspector' => $this->hashInspector($input),
            'jwt-inspector' => $this->jwtInspector($input),
            'regex-lab' => $this->regexLab($input),
            'data-diff' => $this->dataDiff($input),
            'sensitive-data-redactor' => $this->sensitiveDataRedactor($input),
            default => throw new RuntimeException('Unknown Data Lab tool.'),
        };
    }

    private function csvAnalyzer(string $input): array
    {
        $rows = $this->parseCsv($input);

        if ($rows === []) {
            throw new RuntimeException('No CSV rows were detected.');
        }

        $header = array_shift($rows);

        if (! is_array($header) || $header === []) {
            throw new RuntimeException('CSV header could not be detected.');
        }

        if (count($header) > 250) {
            throw new RuntimeException('CSV analysis is limited to 250 columns.');
        }

        $expected = count($header);
        $invalidRows = 0;

        foreach ($rows as $row) {
            if (count($row) !== $expected) {
                $invalidRows++;
            }
        }

        return [
            'mode' => 'offline-csv-analysis',
            'row_count' => count($rows),
            'column_count' => $expected,
            'headers' => $header,
            'invalid_row_count' => $invalidRows,
            'consistent_columns' => $invalidRows === 0,
            'preview' => array_slice($rows, 0, 10),
        ];
    }

    private function jsonInspector(string $input): array
    {
        try {
            $decoded = json_decode(
                $input,
                true,
                self::MAX_JSON_DEPTH,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException(
                'Invalid JSON: ' . $e->getMessage()
            );
        }

        return [
            'mode' => 'offline-json-inspection',
            'valid' => true,
            'root_type' => gettype($decoded),
            'top_level_count' => is_array($decoded)
                ? count($decoded)
                : null,
            'depth' => $this->depth($decoded),
            'pretty_json' => json_encode(
                $decoded,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE
            ),
        ];
    }

    private function dataCleaner(string $input): array
    {
        $lines = $this->lines($input);
        $originalCount = count($lines);

        $cleaned = [];
        $seen = [];

        foreach ($lines as $line) {
            $line = trim(
                preg_replace('/[ \t]+/u', ' ', $line) ?? $line
            );

            if ($line === '' || isset($seen[$line])) {
                continue;
            }

            $seen[$line] = true;
            $cleaned[] = $line;
        }

        return [
            'mode' => 'offline-data-cleaning',
            'original_line_count' => $originalCount,
            'cleaned_line_count' => count($cleaned),
            'removed_line_count' => $originalCount - count($cleaned),
            'cleaned_text' => implode("\n", $cleaned),
        ];
    }

    private function patternAnalysis(string $input): array
    {
        $patterns = [
            'ipv4' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            'ipv6' => '/\b(?:[0-9a-f]{1,4}:){2,7}[0-9a-f]{0,4}\b/i',
            'email' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            'url' => '#\bhttps?://[^\s<>"\']+#i',
            'domain' => '/\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}\b/i',
            'cve' => '/\bCVE-\d{4}-\d{4,7}\b/i',
            'sha256' => '/\b[a-f0-9]{64}\b/i',
            'sha1' => '/\b[a-f0-9]{40}\b/i',
            'md5' => '/\b[a-f0-9]{32}\b/i',
            'uuid' => '/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i',
        ];

        $matches = [];

        foreach ($patterns as $name => $pattern) {
            preg_match_all($pattern, $input, $found);

            $values = array_values(array_unique(
                array_slice(
                    $found[0] ?? [],
                    0,
                    self::MAX_MATCHES_PER_TYPE
                )
            ));

            $matches[$name] = [
                'count' => count($values),
                'values' => $values,
            ];
        }

        return [
            'mode' => 'offline-pattern-analysis',
            'character_count' => mb_strlen($input),
            'line_count' => count($this->lines($input)),
            'patterns' => $matches,
        ];
    }

    private function logAnalyzer(string $input): array
    {
        $lines = $this->lines($input);

        $statusCounts = [];
        $ipCounts = [];
        $methodCounts = [];
        $suspicious = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/\b([1-5]\d{2})\b/', $line, $m)) {
                $statusCounts[$m[1]] = ($statusCounts[$m[1]] ?? 0) + 1;
            }

            if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $line, $m)) {
                $ipCounts[$m[0]] = ($ipCounts[$m[0]] ?? 0) + 1;
            }

            if (preg_match('/\b(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\b/i', $line, $m)) {
                $method = strtoupper($m[1]);
                $methodCounts[$method] = ($methodCounts[$method] ?? 0) + 1;
            }

            if (
                preg_match(
                    '/failed login|authentication failed|unauthorized|forbidden|invalid password|exception|fatal error/i',
                    $line
                ) &&
                count($suspicious) < 50
            ) {
                $suspicious[] = [
                    'line' => $index + 1,
                    'preview' => mb_substr($line, 0, 300),
                ];
            }
        }

        arsort($statusCounts);
        arsort($ipCounts);
        arsort($methodCounts);

        return [
            'mode' => 'offline-log-analysis',
            'line_count' => count($lines),
            'http_status_counts' => $statusCounts,
            'method_counts' => $methodCounts,
            'top_ips' => array_slice($ipCounts, 0, 20, true),
            'security_relevant_events' => $suspicious,
            'security_relevant_event_count' => count($suspicious),
        ];
    }

    private function httpInspector(string $input): array
    {
        $normalized = str_replace("\r\n", "\n", trim($input));
        [$head, $body] = array_pad(
            explode("\n\n", $normalized, 2),
            2,
            ''
        );

        $lines = explode("\n", $head);
        $startLine = trim(array_shift($lines) ?? '');

        if ($startLine === '') {
            throw new RuntimeException('HTTP start line is missing.');
        }

        $headers = [];

        foreach (array_slice($lines, 0, 100) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);

            $name = strtolower(trim($name));

            if ($name === '') {
                continue;
            }

            $headers[$name] = mb_substr(trim($value), 0, 4000);
        }

        $type = str_starts_with(strtoupper($startLine), 'HTTP/')
            ? 'response'
            : 'request';

        return [
            'mode' => 'offline-http-inspection',
            'message_type' => $type,
            'start_line' => $startLine,
            'header_count' => count($headers),
            'headers' => $headers,
            'body_bytes' => strlen($body),
            'security_notes' => $this->httpSecurityNotes($type, $headers),
            'network_request_sent' => false,
        ];
    }

    private function encodingStudio(string $input): array
    {
        $trimmed = trim($input);

        return [
            'mode' => 'offline-encoding',
            'original_bytes' => strlen($input),
            'base64_encode' => base64_encode($input),
            'url_encode' => rawurlencode($input),
            'hex_encode' => bin2hex($input),
            'base64_decode_if_valid' => $this->safeBase64Decode($trimmed),
            'url_decode' => rawurldecode($trimmed),
            'hex_decode_if_valid' => $this->safeHexDecode($trimmed),
        ];
    }

    private function hashInspector(string $input): array
    {
        $value = strtolower(trim($input));

        $possible = [];

        if (preg_match('/^[a-f0-9]{32}$/', $value)) {
            $possible[] = 'MD5';
        }

        if (preg_match('/^[a-f0-9]{40}$/', $value)) {
            $possible[] = 'SHA-1';
        }

        if (preg_match('/^[a-f0-9]{64}$/', $value)) {
            $possible[] = 'SHA-256';
        }

        if (preg_match('/^[a-f0-9]{128}$/', $value)) {
            $possible[] = 'SHA-512';
        }

        return [
            'mode' => 'hash-format-identification-only',
            'length' => strlen($value),
            'possible_algorithms' => $possible,
            'recognized' => $possible !== [],
            'cracking_performed' => false,
        ];
    }

    private function jwtInspector(string $input): array
    {
        $token = trim($input);
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('JWT must contain exactly three segments.');
        }

        $header = $this->decodeJwtSegment($parts[0], 'header');
        $payload = $this->decodeJwtSegment($parts[1], 'payload');

        $notes = [];

        $alg = is_array($header) ? ($header['alg'] ?? null) : null;

        if (is_string($alg) && strtolower($alg) === 'none') {
            $notes[] = 'JWT declares the none algorithm. Treat unsigned tokens as unsafe unless explicitly required by a trusted protocol.';
        }

        foreach (['exp', 'iat', 'nbf', 'iss', 'aud'] as $claim) {
            if (is_array($payload) && array_key_exists($claim, $payload)) {
                continue;
            }

            $notes[] = "Claim {$claim} is not present.";
        }

        return [
            'mode' => 'offline-jwt-inspection',
            'header' => $header,
            'payload' => $payload,
            'signature_segment_present' => $parts[2] !== '',
            'signature_verified' => false,
            'notes' => $notes,
        ];
    }

    private function regexLab(string $input): array
    {
        try {
            $payload = json_decode(
                $input,
                true,
                self::MAX_JSON_DEPTH,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new RuntimeException(
                'Regex Lab expects JSON: {"pattern":"...","text":"..."}'
            );
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Regex Lab payload must be an object.');
        }

        $pattern = $payload['pattern'] ?? null;
        $text = $payload['text'] ?? null;

        if (! is_string($pattern) || ! is_string($text)) {
            throw new RuntimeException('Regex Lab requires pattern and text strings.');
        }

        if (strlen($pattern) > 256) {
            throw new RuntimeException('Regex pattern is limited to 256 bytes.');
        }

        if (strlen($text) > 100_000) {
            throw new RuntimeException('Regex test text is limited to 100 KB.');
        }

        $escaped = str_replace('~', '\~', $pattern);

        $safePattern =
            '~(*LIMIT_MATCH=50000)(*LIMIT_RECURSION=5000)(?:' .
            $escaped .
            ')~u';

        set_error_handler(static function (): bool {
            return true;
        });

        try {
            $result = preg_match_all(
                $safePattern,
                $text,
                $matches,
                PREG_SET_ORDER
            );
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            throw new RuntimeException('Invalid or unsupported regular expression.');
        }

        return [
            'mode' => 'bounded-offline-regex-test',
            'match_count' => $result,
            'matches' => array_slice($matches, 0, 100),
            'pattern_bytes' => strlen($pattern),
            'text_bytes' => strlen($text),
        ];
    }

    private function dataDiff(string $input): array
    {
        try {
            $payload = json_decode(
                $input,
                true,
                self::MAX_JSON_DEPTH,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new RuntimeException(
                'Data Diff expects JSON: {"left": {...}, "right": {...}}'
            );
        }

        if (
            ! is_array($payload) ||
            ! array_key_exists('left', $payload) ||
            ! array_key_exists('right', $payload)
        ) {
            throw new RuntimeException('Data Diff requires left and right values.');
        }

        $left = $payload['left'];
        $right = $payload['right'];

        return [
            'mode' => 'offline-structured-diff',
            'equal' => $left === $right,
            'changes' => $this->diffValues($left, $right),
        ];
    }

    private function sensitiveDataRedactor(string $input): array
    {
        $rules = [
            'email' => [
                '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
                '[REDACTED_EMAIL]',
            ],
            'ipv4' => [
                '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
                '[REDACTED_IP]',
            ],
            'bearer_token' => [
                '/\bBearer\s+[A-Za-z0-9._~+\/=-]{12,}\b/i',
                'Bearer [REDACTED_TOKEN]',
            ],
            'jwt_like' => [
                '/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*\b/',
                '[REDACTED_JWT]',
            ],
        ];

        $redacted = $input;
        $counts = [];

        foreach ($rules as $name => [$pattern, $replacement]) {
            $count = 0;

            $redacted = preg_replace(
                $pattern,
                $replacement,
                $redacted,
                -1,
                $count
            ) ?? $redacted;

            $counts[$name] = $count;
        }

        return [
            'mode' => 'offline-sensitive-data-redaction',
            'redaction_counts' => $counts,
            'total_redactions' => array_sum($counts),
            'redacted_text' => $redacted,
        ];
    }

    private function parseCsv(string $input): array
    {
        $lines = $this->lines(trim($input));
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = str_getcsv($line, ',', '"', '');
        }

        return $rows;
    }

    private function lines(string $input): array
    {
        $lines = preg_split('/\R/u', $input) ?: [];

        if (count($lines) > self::MAX_LINES) {
            throw new RuntimeException('Input is limited to 20,000 lines.');
        }

        return $lines;
    }

    private function depth(mixed $value, int $current = 1): int
    {
        if ($current > self::MAX_JSON_DEPTH) {
            return self::MAX_JSON_DEPTH;
        }

        if (! is_array($value) || $value === []) {
            return $current;
        }

        $max = $current;

        foreach ($value as $child) {
            $max = max(
                $max,
                $this->depth($child, $current + 1)
            );
        }

        return $max;
    }

    private function safeBase64Decode(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return null;
        }

        return mb_check_encoding($decoded, 'UTF-8')
            ? $decoded
            : '[binary data]';
    }

    private function safeHexDecode(string $value): ?string
    {
        if (
            $value === '' ||
            strlen($value) % 2 !== 0 ||
            ! ctype_xdigit($value)
        ) {
            return null;
        }

        $decoded = hex2bin($value);

        if ($decoded === false) {
            return null;
        }

        return mb_check_encoding($decoded, 'UTF-8')
            ? $decoded
            : '[binary data]';
    }

    private function decodeJwtSegment(string $segment, string $label): array
    {
        $segment = strtr($segment, '-_', '+/');
        $padding = strlen($segment) % 4;

        if ($padding !== 0) {
            $segment .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($segment, true);

        if ($decoded === false) {
            throw new RuntimeException("Invalid JWT {$label} encoding.");
        }

        try {
            $json = json_decode(
                $decoded,
                true,
                self::MAX_JSON_DEPTH,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new RuntimeException("Invalid JWT {$label} JSON.");
        }

        if (! is_array($json)) {
            throw new RuntimeException("JWT {$label} must be a JSON object.");
        }

        return $json;
    }

    private function httpSecurityNotes(string $type, array $headers): array
    {
        $notes = [];

        if ($type === 'response') {
            if (! isset($headers['content-security-policy'])) {
                $notes[] = 'Content-Security-Policy header is not present in this supplied response.';
            }

            if (! isset($headers['x-content-type-options'])) {
                $notes[] = 'X-Content-Type-Options header is not present in this supplied response.';
            }

            if (isset($headers['server'])) {
                $notes[] = 'Server header is present; review whether implementation details are unnecessarily disclosed.';
            }
        }

        if (isset($headers['authorization'])) {
            $notes[] = 'Authorization header is present. Redact credentials before sharing captured traffic.';
        }

        if (isset($headers['cookie'])) {
            $notes[] = 'Cookie header is present. Treat captured session values as sensitive.';
        }

        return $notes;
    }

    private function diffValues(
        mixed $left,
        mixed $right,
        string $path = '$',
        int $depth = 0
    ): array {
        if ($depth >= 20) {
            return [[
                'path' => $path,
                'type' => 'depth-limit',
            ]];
        }

        if ($left === $right) {
            return [];
        }

        if (! is_array($left) || ! is_array($right)) {
            return [[
                'path' => $path,
                'type' => 'changed',
                'left' => $left,
                'right' => $right,
            ]];
        }

        $changes = [];

        $keys = array_values(array_unique(array_merge(
            array_keys($left),
            array_keys($right)
        )));

        foreach (array_slice($keys, 0, 500) as $key) {
            $next = $path . '.' . (string) $key;

            if (! array_key_exists($key, $left)) {
                $changes[] = [
                    'path' => $next,
                    'type' => 'added',
                    'right' => $right[$key],
                ];
                continue;
            }

            if (! array_key_exists($key, $right)) {
                $changes[] = [
                    'path' => $next,
                    'type' => 'removed',
                    'left' => $left[$key],
                ];
                continue;
            }

            $changes = array_merge(
                $changes,
                $this->diffValues(
                    $left[$key],
                    $right[$key],
                    $next,
                    $depth + 1
                )
            );

            if (count($changes) >= 500) {
                break;
            }
        }

        return array_slice($changes, 0, 500);
    }
}
