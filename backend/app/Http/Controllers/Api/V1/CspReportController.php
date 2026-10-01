<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CspReportController extends Controller
{
    private const MAX_BODY_BYTES = 16_384;
    private const MAX_FIELD_LENGTH = 2_048;

    public function store(Request $request): Response
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($contentLength > self::MAX_BODY_BYTES) {
            return response()->noContent(413);
        }

        $raw = $request->getContent();

        if (strlen($raw) > self::MAX_BODY_BYTES) {
            return response()->noContent(413);
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return response()->noContent(204);
        }

        $report = $payload['csp-report'] ?? $payload;

        if (! is_array($report)) {
            return response()->noContent(204);
        }

        $allowed = [
            'document-uri',
            'blocked-uri',
            'effective-directive',
            'violated-directive',
            'source-file',
            'line-number',
            'column-number',
            'disposition',
            'status-code',
        ];

        $sanitized = [];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $report)) {
                continue;
            }

            $value = $report[$key];

            if (in_array($key, [
                'document-uri',
                'blocked-uri',
                'source-file',
            ], true)) {
                $value = $this->sanitizeUrl($value);
            }

            if (is_string($value)) {
                $value = mb_substr(
                    $value,
                    0,
                    self::MAX_FIELD_LENGTH
                );
            } elseif (! is_int($value) && ! is_float($value)) {
                continue;
            }

            $sanitized[$key] = $value;
        }

        Log::channel('csp')->warning(
            'CSP violation',
            $sanitized
        );

        return response()->noContent();
    }

    private function sanitizeUrl(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = mb_substr(
            $value,
            0,
            self::MAX_FIELD_LENGTH
        );

        $parts = parse_url($value);

        if ($parts === false) {
            return '[invalid-url]';
        }

        if (! isset($parts['scheme'])) {
            return $value;
        }

        $url = $parts['scheme'].'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        $url .= $parts['path'] ?? '';

        return $url;
    }
}
