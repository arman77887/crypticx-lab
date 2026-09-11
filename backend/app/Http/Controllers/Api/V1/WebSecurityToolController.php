<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WebSecurityToolController extends Controller
{
    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        try {
            $url = trim($validated['url']);

            if (! preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }

            $parsed = parse_url($url);

            if (
                ! is_array($parsed) ||
                ! isset($parsed['scheme'], $parsed['host'])
            ) {
                throw new RuntimeException('Invalid target URL.');
            }

            $scheme = strtolower($parsed['scheme']);

            if (! in_array($scheme, ['http', 'https'], true)) {
                throw new RuntimeException('Only HTTP and HTTPS URLs are allowed.');
            }

            if (isset($parsed['user']) || isset($parsed['pass'])) {
                throw new RuntimeException('URLs containing credentials are not allowed.');
            }

            $host = strtolower(trim($parsed['host'], '[]'));

            $port = isset($parsed['port'])
                ? (int) $parsed['port']
                : ($scheme === 'https' ? 443 : 80);

            if (
                ($scheme === 'http' && $port !== 80) ||
                ($scheme === 'https' && $port !== 443)
            ) {
                throw new RuntimeException('Only standard HTTP/HTTPS ports are allowed for this tool.');
            }

            $resolvedIps = $this->resolveAndValidateHost($host);

            $started = microtime(true);

            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => true,
                    'curl' => [
                        CURLOPT_RESOLVE => $this->curlResolveEntries(
                            $host,
                            $port,
                            $resolvedIps
                        ),
                    ],
                ])
                ->get($url);

            $durationMs = (int) round(
                (microtime(true) - $started) * 1000
            );

            $rawHeaders = $response->headers();
            $headers = $this->normalizeHeaders($rawHeaders);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'hostname' => $host,
                    'resolved_ips' => $resolvedIps,
                    'http' => [
                        'status' => $response->status(),
                        'successful' => $response->successful(),
                        'duration_ms' => $durationMs,
                        'content_type' => $this->header($headers, 'content-type'),
                        'content_length' => $this->header($headers, 'content-length'),
                        'server' => $this->header($headers, 'server'),
                        'powered_by' => $this->header($headers, 'x-powered-by'),
                        'location' => $this->header($headers, 'location'),
                    ],
                    'security_headers' => $this->securityHeaders($headers),
                    'cookies' => $this->cookies(
                        $rawHeaders['Set-Cookie'] ??
                        $rawHeaders['set-cookie'] ??
                        [],
                        $scheme
                    ),
                    'cors' => $this->cors($headers),
                    'checked_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'The target could not be analyzed.',
            ], 502);
        }
    }

    private function resolveAndValidateHost(string $hostname): array
    {
        if ($hostname === '') {
            throw new RuntimeException('Target hostname is empty.');
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($hostname);

            return [$hostname];
        }

        if (! preg_match(
            '/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/',
            $hostname
        )) {
            throw new RuntimeException('Target hostname is invalid.');
        }

        $records = dns_get_record(
            $hostname,
            DNS_A | DNS_AAAA
        );

        if ($records === false || count($records) === 0) {
            throw new RuntimeException('Target hostname could not be resolved.');
        }

        $ips = [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($ip)) {
                continue;
            }

            $this->assertPublicIp($ip);
            $ips[] = $ip;
        }

        $ips = array_values(array_unique($ips));

        if ($ips === []) {
            throw new RuntimeException(
                'Target did not resolve to a usable public IP address.'
            );
        }

        return $ips;
    }

    private function assertPublicIp(string $ip): void
    {
        if (
            ! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )
        ) {
            throw new RuntimeException(
                'Private, reserved, loopback, link-local, or unsafe IP targets are not allowed.'
            );
        }
    }

    private function curlResolveEntries(
        string $host,
        int $port,
        array $ips
    ): array {
        return array_map(
            fn (string $ip) => "{$host}:{$port}:{$ip}",
            $ips
        );
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[strtolower($name)] = is_array($values)
                ? implode(', ', $values)
                : (string) $values;
        }

        return $normalized;
    }

    private function securityHeaders(array $headers): array
    {
        $definitions = [
            'strict-transport-security' => 'Strict-Transport-Security',
            'content-security-policy' => 'Content-Security-Policy',
            'x-content-type-options' => 'X-Content-Type-Options',
            'referrer-policy' => 'Referrer-Policy',
            'permissions-policy' => 'Permissions-Policy',
            'cross-origin-opener-policy' => 'Cross-Origin-Opener-Policy',
            'cross-origin-resource-policy' => 'Cross-Origin-Resource-Policy',
        ];

        $results = [];

        foreach ($definitions as $key => $label) {
            $value = $this->header($headers, $key);

            $results[] = [
                'name' => $label,
                'present' => $value !== null,
                'value' => $value,
            ];
        }

        return $results;
    }

    private function cookies(array|string $setCookies, string $scheme): array
    {
        if (is_string($setCookies)) {
            $setCookies = [$setCookies];
        }

        $results = [];

        foreach ($setCookies as $cookie) {
            if (! is_string($cookie) || trim($cookie) === '') {
                continue;
            }

            $firstPart = explode(';', $cookie, 2)[0] ?? '';
            $name = trim(explode('=', $firstPart, 2)[0] ?? '');

            if ($name === '') {
                continue;
            }

            $secure = stripos($cookie, '; secure') !== false;
            $httpOnly = stripos($cookie, '; httponly') !== false;

            $sameSite = preg_match(
                '/;\s*samesite\s*=\s*(strict|lax|none)/i',
                $cookie,
                $match
            )
                ? strtolower($match[1])
                : null;

            $results[] = [
                'name' => $name,
                'secure' => $secure,
                'secure_required' => $scheme === 'https',
                'http_only' => $httpOnly,
                'same_site' => $sameSite,
            ];
        }

        return $results;
    }

    private function cors(array $headers): array
    {
        $origin = $this->header(
            $headers,
            'access-control-allow-origin'
        );

        $credentials = $this->header(
            $headers,
            'access-control-allow-credentials'
        );

        $methods = $this->header(
            $headers,
            'access-control-allow-methods'
        );

        $allowedHeaders = $this->header(
            $headers,
            'access-control-allow-headers'
        );

        return [
            'allow_origin' => $origin,
            'allow_credentials' => $credentials,
            'allow_methods' => $methods,
            'allow_headers' => $allowedHeaders,
            'wildcard_origin' => trim((string) $origin) === '*',
            'credentialed' => strtolower(trim((string) $credentials)) === 'true',
        ];
    }

    private function header(array $headers, string $name): ?string
    {
        $value = $headers[strtolower($name)] ?? null;

        return is_string($value) && $value !== ''
            ? $value
            : null;
    }
}
