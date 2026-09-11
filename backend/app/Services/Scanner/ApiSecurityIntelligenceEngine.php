<?php

namespace App\Services\Scanner;

final class ApiSecurityIntelligenceEngine
{
    /**
     * Analyze already-observed HTTP/API evidence.
     *
     * This engine performs no networking, DNS resolution, database
     * access, authentication attempts, fuzzing, or state-changing
     * requests.
     */
    public function analyze(array $observation): array
    {
        $status = $this->nullableInt(
            $observation['status'] ?? null
        );

        $contentType = $this->nullableString(
            $observation['content_type'] ?? null
        );

        $allowHeader = $this->nullableString(
            $observation['allow_header'] ?? null
        );

        $allowOrigin = $this->nullableString(
            $observation['allow_origin'] ?? null
        );

        $allowCredentials = $this->nullableString(
            $observation['allow_credentials'] ?? null
        );

        $wwwAuthenticate = $this->nullableString(
            $observation['www_authenticate'] ?? null
        );

        $cacheControl = $this->nullableString(
            $observation['cache_control'] ?? null
        );

        $server = $this->nullableString(
            $observation['server'] ?? null
        );

        $poweredBy = $this->nullableString(
            $observation['powered_by'] ?? null
        );

        $apiVersion = $this->nullableString(
            $observation['api_version'] ?? null
        );

        $methods = $this->parseMethods($allowHeader);

        $findings = [];

        $this->analyzeRiskyMethods(
            $methods,
            $findings
        );

        $this->analyzeCors(
            $allowOrigin,
            $allowCredentials,
            $findings
        );

        $this->analyzeTechnologyDisclosure(
            $server,
            $poweredBy,
            $findings
        );

        return [
            'classification' => [
                'status' => $status,
                'json_response' =>
                    $this->isJsonContentType($contentType),
                'content_type' => $contentType,
            ],

            'methods' => [
                'allow_header' => $allowHeader,
                'advertised' => $methods,
                'risky' => array_values(
                    array_intersect(
                        $methods,
                        ['TRACE', 'CONNECT']
                    )
                ),
            ],

            'cors' => [
                'allow_origin' => $allowOrigin,
                'allow_credentials' => $allowCredentials,
            ],

            /*
             * Informational evidence only. These fields do not imply
             * a vulnerability merely because they are present/absent.
             */
            'authentication' => [
                'www_authenticate' => $wwwAuthenticate,
            ],

            'cache' => [
                'cache_control' => $cacheControl,
            ],

            'disclosure' => [
                'server' => $server,
                'powered_by' => $poweredBy,
                'api_version' => $apiVersion,
            ],

            'findings' => $findings,
            'finding_count' => count($findings),
        ];
    }

    private function analyzeRiskyMethods(
        array $methods,
        array &$findings
    ): void {
        $risky = array_values(
            array_intersect(
                $methods,
                ['TRACE', 'CONNECT']
            )
        );

        if ($risky === []) {
            return;
        }

        $findings[] = [
            'type' => 'api_http_methods',
            'title' =>
                'Potentially risky HTTP methods advertised',
            'severity' => 'medium',
            'confidence' => 'high',
            'evidence' => [
                'advertised_risky_methods' => $risky,
            ],
        ];
    }

    private function analyzeCors(
        ?string $allowOrigin,
        ?string $allowCredentials,
        array &$findings
    ): void {
        if ($allowOrigin !== '*') {
            return;
        }

        $credentialsEnabled =
            strtolower(trim((string) $allowCredentials))
                === 'true';

        if ($credentialsEnabled) {
            $findings[] = [
                'type' => 'api_cors',
                'title' =>
                    'Credentialed wildcard CORS configuration',
                'severity' => 'medium',
                'confidence' => 'high',
                'evidence' => [
                    'access_control_allow_origin' => '*',
                    'access_control_allow_credentials' =>
                        $allowCredentials,
                ],
            ];

            return;
        }

        $findings[] = [
            'type' => 'api_cors',
            'title' => 'Wildcard CORS policy advertised',
            'severity' => 'low',
            'confidence' => 'high',
            'evidence' => [
                'access_control_allow_origin' => '*',
            ],
        ];
    }

    private function analyzeTechnologyDisclosure(
        ?string $server,
        ?string $poweredBy,
        array &$findings
    ): void {
        if (
            $server !== null &&
            $this->containsExplicitVersion($server)
        ) {
            $findings[] = [
                'type' => 'api_server_disclosure',
                'title' =>
                    'Detailed server version disclosure',
                'severity' => 'low',
                'confidence' => 'high',
                'evidence' => [
                    'server' => $server,
                ],
            ];
        }

        if ($poweredBy !== null) {
            $findings[] = [
                'type' => 'api_framework_disclosure',
                'title' =>
                    'Application framework disclosure',
                'severity' => 'low',
                'confidence' => 'high',
                'evidence' => [
                    'x_powered_by' => $poweredBy,
                ],
            ];
        }
    }

    private function containsExplicitVersion(
        string $value
    ): bool {
        return preg_match(
            '/\b(?:apache|nginx|iis|php|node|express|tomcat|jetty)(?:\/|\s+)[vV]?(\d+(?:\.\d+)+)\b/i',
            $value
        ) === 1;
    }

    private function parseMethods(
        ?string $value
    ): array {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (string $method): string =>
                            strtoupper(trim($method)),
                        explode(',', $value)
                    )
                )
            )
        );
    }

    private function isJsonContentType(
        ?string $contentType
    ): bool {
        if ($contentType === null) {
            return false;
        }

        $contentType = strtolower($contentType);

        return
            str_contains(
                $contentType,
                'application/json'
            ) ||
            str_contains(
                $contentType,
                '+json'
            );
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function nullableInt(
        mixed $value
    ): ?int {
        return is_int($value) ? $value : null;
    }
}
