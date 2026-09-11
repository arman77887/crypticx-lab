<?php

namespace App\Services;

use RuntimeException;

class ScannerResultValidator
{
    private const MAX_FINDINGS = 100;

    private const MAX_TYPE_LENGTH = 80;
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 10_000;
    private const MAX_REMEDIATION_LENGTH = 10_000;

    /*
     * Bound serialized evidence returned by an untrusted scanner runtime.
     */
    private const MAX_EVIDENCE_BYTES = 65_536;

    /*
     * DNS Intelligence V1 is returned by an isolated scanner runtime
     * and must be treated as untrusted input.
     */
    private const MAX_DNS_RECORDS_PER_TYPE = 100;
    private const MAX_DNS_RECORD_BYTES = 16_384;
    private const MAX_DNS_TXT_LENGTH = 4_096;
    private const MAX_DNS_DURATION_MS = 120_000;

    private const DNS_RECORD_TYPES = [
        'A',
        'AAAA',
        'CNAME',
        'MX',
        'NS',
        'TXT',
        'CAA',
        'SOA',
    ];

    /*
     * TLS Intelligence V1 crosses the isolated scanner trust boundary.
     * Keep all attacker-controlled strings and collections bounded.
     */
    private const MAX_TLS_CHAIN_LENGTH = 16;
    private const MAX_TLS_NAME_LENGTH = 1_024;
    private const MAX_TLS_DETAIL_FIELDS = 64;
    private const MAX_TLS_DETAIL_VALUE_LENGTH = 4_096;
    private const MAX_TLS_SAN_COUNT = 128;
    private const MAX_TLS_SAN_VALUE_LENGTH = 1_024;
    private const MAX_TLS_DAYS_ABS = 36_500;

    private const ALLOWED_SEVERITIES = [
        'critical',
        'high',
        'medium',
        'low',
        'info',
        'informational',
    ];

    private const ALLOWED_CONFIDENCES = [
        'high',
        'medium',
        'low',
    ];

    public function validate(
        array $result,
        string $targetId
    ): array {
        if (
            ($result['engine_version'] ?? null)
            !== 'http-assessment-v3'
        ) {
            throw new RuntimeException(
                'Scanner result engine version is invalid.'
            );
        }

        $findings = $result['findings'] ?? null;

        if (! is_array($findings)) {
            throw new RuntimeException(
                'Scanner result findings collection is invalid.'
            );
        }

        if (count($findings) > self::MAX_FINDINGS) {
            throw new RuntimeException(
                'Scanner result contains too many findings.'
            );
        }

        if (
            ! isset($result['finding_count']) ||
            ! is_int($result['finding_count']) ||
            $result['finding_count'] !== count($findings)
        ) {
            throw new RuntimeException(
                'Scanner result finding count is inconsistent.'
            );
        }

        $validated = [];
        $fingerprints = [];

        foreach ($findings as $finding) {
            $validatedFinding = $this->validateFinding(
                $finding,
                $targetId
            );

            $fingerprint = $validatedFinding['fingerprint'];

            if (isset($fingerprints[$fingerprint])) {
                throw new RuntimeException(
                    'Scanner result contains duplicate finding fingerprints.'
                );
            }

            $fingerprints[$fingerprint] = true;
            $validated[] = $validatedFinding;
        }

        $result['findings'] = $validated;

        if (array_key_exists('dns_intelligence', $result)) {
            $result['dns_intelligence'] =
                $this->validateDnsIntelligence(
                    $result['dns_intelligence']
                );
        }

        if (array_key_exists('tls', $result)) {
            $result['tls'] = $this->validateTls(
                $result['tls']
            );
        }

        return $result;
    }

    private function validateFinding(
        mixed $finding,
        string $targetId
    ): array {
        if (! is_array($finding)) {
            throw new RuntimeException(
                'Scanner result contains an invalid finding.'
            );
        }

        $required = [
            'fingerprint',
            'type',
            'title',
            'description',
            'severity',
            'confidence',
            'evidence_data',
            'remediation',
        ];

        foreach ($required as $field) {
            if (! array_key_exists($field, $finding)) {
                throw new RuntimeException(
                    "Scanner finding field is missing: {$field}."
                );
            }
        }

        $fingerprint = $this->requiredString(
            $finding['fingerprint'],
            'fingerprint',
            64
        );

        if (
            strlen($fingerprint) !== 64 ||
            ! ctype_xdigit($fingerprint)
        ) {
            throw new RuntimeException(
                'Scanner finding fingerprint is invalid.'
            );
        }

        $fingerprint = strtolower($fingerprint);

        $type = $this->requiredString(
            $finding['type'],
            'type',
            self::MAX_TYPE_LENGTH
        );

        $title = $this->requiredString(
            $finding['title'],
            'title',
            self::MAX_TITLE_LENGTH
        );

        $description = $this->boundedString(
            $finding['description'],
            'description',
            self::MAX_DESCRIPTION_LENGTH
        );

        $severity = strtolower(
            $this->requiredString(
                $finding['severity'],
                'severity',
                20
            )
        );

        if (
            ! in_array(
                $severity,
                self::ALLOWED_SEVERITIES,
                true
            )
        ) {
            throw new RuntimeException(
                'Scanner finding severity is invalid.'
            );
        }

        $confidence = strtolower(
            $this->requiredString(
                $finding['confidence'],
                'confidence',
                20
            )
        );

        if (
            ! in_array(
                $confidence,
                self::ALLOWED_CONFIDENCES,
                true
            )
        ) {
            throw new RuntimeException(
                'Scanner finding confidence is invalid.'
            );
        }

        if (! is_array($finding['evidence_data'])) {
            throw new RuntimeException(
                'Scanner finding evidence data is invalid.'
            );
        }

        try {
            $encodedEvidence = json_encode(
                $finding['evidence_data'],
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            throw new RuntimeException(
                'Scanner finding evidence data is not valid JSON.'
            );
        }

        if (strlen($encodedEvidence) > self::MAX_EVIDENCE_BYTES) {
            throw new RuntimeException(
                'Scanner finding evidence data exceeds the size limit.'
            );
        }

        $remediation = $this->boundedString(
            $finding['remediation'],
            'remediation',
            self::MAX_REMEDIATION_LENGTH
        );

        /*
         * Never trust identity supplied by an isolated runtime.
         * Recompute using the canonical Finding Identity V1 algorithm.
         */
        $expectedFingerprint = hash(
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

        if (! hash_equals($expectedFingerprint, $fingerprint)) {
            throw new RuntimeException(
                'Scanner finding fingerprint does not match its identity.'
            );
        }

        return [
            'fingerprint' => $expectedFingerprint,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'confidence' => $confidence,
            'evidence_data' => $finding['evidence_data'],
            'remediation' => $remediation,
        ];
    }

    private function validateTls(
        mixed $tls
    ): array {
        if (! is_array($tls)) {
            throw new RuntimeException(
                'Scanner TLS payload is invalid.'
            );
        }

        $enabled = $tls['enabled'] ?? null;

        if (! is_bool($enabled)) {
            throw new RuntimeException(
                'Scanner TLS enabled state is invalid.'
            );
        }

        if ($enabled === false) {
            $allowed = [
                'enabled',
                'status',
                'certificate',
                'connection',
            ];

            $this->assertOnlyKeys(
                $tls,
                $allowed,
                'Scanner TLS disabled payload contains unknown fields.'
            );

            if (
                array_key_exists('certificate', $tls) &&
                $tls['certificate'] !== null
            ) {
                throw new RuntimeException(
                    'Scanner TLS disabled certificate is invalid.'
                );
            }

            if (
                array_key_exists('connection', $tls) &&
                $tls['connection'] !== null
            ) {
                throw new RuntimeException(
                    'Scanner TLS disabled connection is invalid.'
                );
            }

            $status = $tls['status'] ?? 'not_observed';

            if ($status !== 'not_observed') {
                throw new RuntimeException(
                    'Scanner TLS disabled status is invalid.'
                );
            }

            return [
                'enabled' => false,
                'status' => 'not_observed',
                'certificate' => null,
                'connection' => null,
            ];
        }

        $status = $tls['status'] ?? null;

        if (
            ! is_string($status) ||
            ! in_array(
                $status,
                [
                    'verified',
                    'connection_failed',
                    'certificate_unavailable',
                    'certificate_parse_failed',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Scanner TLS status is invalid.'
            );
        }

        if ($status === 'connection_failed') {
            $this->assertOnlyKeys(
                $tls,
                [
                    'enabled',
                    'status',
                    'certificate',
                    'connection',
                    'error',
                ],
                'Scanner TLS failure payload contains unknown fields.'
            );

            if (($tls['certificate'] ?? null) !== null) {
                throw new RuntimeException(
                    'Scanner TLS failure certificate is invalid.'
                );
            }

            return [
                'enabled' => true,
                'status' => $status,
                'certificate' => null,
                'connection' =>
                    $this->validateTlsConnection(
                        $tls['connection'] ?? null,
                        false
                    ),
                'error' =>
                    $this->validateTlsError(
                        $tls['error'] ?? null,
                        'tls_connection_failed'
                    ),
            ];
        }

        $connection = $this->validateTlsConnection(
            $tls['connection'] ?? null,
            true
        );

        if (
            $status === 'certificate_unavailable' ||
            $status === 'certificate_parse_failed'
        ) {
            $this->assertOnlyKeys(
                $tls,
                [
                    'enabled',
                    'status',
                    'certificate',
                    'connection',
                    'error',
                ],
                'Scanner TLS certificate failure payload contains unknown fields.'
            );

            if (($tls['certificate'] ?? null) !== null) {
                throw new RuntimeException(
                    'Scanner TLS certificate failure payload is inconsistent.'
                );
            }

            $expectedError = $status === 'certificate_unavailable'
                ? 'certificate_not_captured'
                : 'certificate_parse_failed';

            return [
                'enabled' => true,
                'status' => $status,
                'certificate' => null,
                'connection' => $connection,
                'error' => $this->validateTlsError(
                    $tls['error'] ?? null,
                    $expectedError
                ),
            ];
        }

        $this->assertOnlyKeys(
            $tls,
            [
                'enabled',
                'status',
                'connection',
                'certificate',
                'chain_length',
                'certificate_chain',
            ],
            'Scanner TLS verified payload contains unknown fields.'
        );

        $certificate = $this->validateTlsCertificate(
            $tls['certificate'] ?? null
        );

        $chain = $tls['certificate_chain'] ?? [];

        if (
            ! is_array($chain) ||
            count($chain) > self::MAX_TLS_CHAIN_LENGTH
        ) {
            throw new RuntimeException(
                'Scanner TLS certificate chain is invalid.'
            );
        }

        $validatedChain = [];

        foreach ($chain as $index => $item) {
            $validatedChain[] =
                $this->validateTlsChainItem(
                    $item,
                    $index + 1
                );
        }

        $chainLength = $tls['chain_length'] ?? 0;

        if (
            ! is_int($chainLength) ||
            $chainLength !== count($validatedChain)
        ) {
            throw new RuntimeException(
                'Scanner TLS certificate chain length is inconsistent.'
            );
        }

        return [
            'enabled' => true,
            'status' => 'verified',
            'connection' => $connection,
            'certificate' => $certificate,
            'chain_length' => $chainLength,
            'certificate_chain' => $validatedChain,
        ];
    }

    private function validateTlsConnection(
        mixed $connection,
        bool $cryptoRequired
    ): array {
        if (! is_array($connection)) {
            throw new RuntimeException(
                'Scanner TLS connection is invalid.'
            );
        }

        $this->assertOnlyKeys(
            $connection,
            [
                'ip',
                'port',
                'protocol',
                'cipher_name',
                'cipher_bits',
                'cipher_version',
            ],
            'Scanner TLS connection contains unknown fields.'
        );

        $ip = $connection['ip'] ?? null;
        $port = $connection['port'] ?? null;

        if (
            ! is_string($ip) ||
            filter_var($ip, FILTER_VALIDATE_IP) === false
        ) {
            throw new RuntimeException(
                'Scanner TLS connection IP is invalid.'
            );
        }

        if (
            ! is_int($port) ||
            $port < 1 ||
            $port > 65535
        ) {
            throw new RuntimeException(
                'Scanner TLS connection port is invalid.'
            );
        }

        $protocol = $this->nullableTlsString(
            $connection['protocol'] ?? null,
            'protocol',
            64
        );

        $cipherName = $this->nullableTlsString(
            $connection['cipher_name'] ?? null,
            'cipher_name',
            255
        );

        $cipherVersion = $this->nullableTlsString(
            $connection['cipher_version'] ?? null,
            'cipher_version',
            64
        );

        $cipherBits = $connection['cipher_bits'] ?? null;

        if (
            $cipherBits !== null &&
            (
                ! is_int($cipherBits) ||
                $cipherBits < 0 ||
                $cipherBits > 65_536
            )
        ) {
            throw new RuntimeException(
                'Scanner TLS cipher bits are invalid.'
            );
        }

        if (
            $cryptoRequired &&
            $protocol === null &&
            $cipherName === null
        ) {
            throw new RuntimeException(
                'Scanner TLS crypto metadata is missing.'
            );
        }

        return [
            'ip' => $ip,
            'port' => $port,
            'protocol' => $protocol,
            'cipher_name' => $cipherName,
            'cipher_bits' => $cipherBits,
            'cipher_version' => $cipherVersion,
        ];
    }

    private function validateTlsCertificate(
        mixed $certificate
    ): array {
        if (! is_array($certificate)) {
            throw new RuntimeException(
                'Scanner TLS certificate is invalid.'
            );
        }

        $this->assertOnlyKeys(
            $certificate,
            [
                'subject',
                'issuer',
                'subject_details',
                'issuer_details',
                'subject_alt_names',
                'valid_from',
                'valid_to',
                'days_remaining',
                'expired',
                'not_yet_valid',
                'serial',
                'signature_algorithm',
                'sha256_fingerprint',
            ],
            'Scanner TLS certificate contains unknown fields.'
        );

        $subject = $this->nullableTlsString(
            $certificate['subject'] ?? null,
            'certificate subject',
            self::MAX_TLS_NAME_LENGTH
        );

        $issuer = $this->nullableTlsString(
            $certificate['issuer'] ?? null,
            'certificate issuer',
            self::MAX_TLS_NAME_LENGTH
        );

        $subjectDetails = $this->validateTlsDetails(
            $certificate['subject_details'] ?? [],
            'subject'
        );

        $issuerDetails = $this->validateTlsDetails(
            $certificate['issuer_details'] ?? [],
            'issuer'
        );

        $sans = $certificate['subject_alt_names'] ?? [];

        if (
            ! is_array($sans) ||
            count($sans) > self::MAX_TLS_SAN_COUNT
        ) {
            throw new RuntimeException(
                'Scanner TLS subject alternative names are invalid.'
            );
        }

        $validatedSans = [];

        foreach ($sans as $san) {
            if (! is_array($san)) {
                throw new RuntimeException(
                    'Scanner TLS subject alternative name is invalid.'
                );
            }

            $this->assertOnlyKeys(
                $san,
                ['type', 'value'],
                'Scanner TLS subject alternative name contains unknown fields.'
            );

            $type = $san['type'] ?? null;
            $value = $san['value'] ?? null;

            if (
                ! is_string($type) ||
                ! in_array(
                    $type,
                    ['dns', 'ip', 'other'],
                    true
                ) ||
                ! is_string($value) ||
                $value === '' ||
                strlen($value)
                    > self::MAX_TLS_SAN_VALUE_LENGTH
            ) {
                throw new RuntimeException(
                    'Scanner TLS subject alternative name is invalid.'
                );
            }

            $validatedSans[] = [
                'type' => $type,
                'value' => $value,
            ];
        }

        $validFrom = $this->nullableTlsDate(
            $certificate['valid_from'] ?? null,
            'valid_from'
        );

        $validTo = $this->nullableTlsDate(
            $certificate['valid_to'] ?? null,
            'valid_to'
        );

        $days = $certificate['days_remaining'] ?? null;

        if (
            $days !== null &&
            (
                ! is_int($days) ||
                abs($days) > self::MAX_TLS_DAYS_ABS
            )
        ) {
            throw new RuntimeException(
                'Scanner TLS certificate days remaining is invalid.'
            );
        }

        $expired = $certificate['expired'] ?? null;
        $notYetValid = $certificate['not_yet_valid'] ?? null;

        if (
            ! is_bool($expired) ||
            ! is_bool($notYetValid) ||
            ($expired && $notYetValid)
        ) {
            throw new RuntimeException(
                'Scanner TLS certificate validity state is invalid.'
            );
        }

        if (
            $validTo !== null &&
            $days !== null
        ) {
            $expectedDays = (int) floor(
                (strtotime($validTo) - time()) / 86400
            );

            /*
             * Validation may cross a second/day boundary while the
             * isolated runtime result is in transit.
             */
            if (abs($expectedDays - $days) > 1) {
                throw new RuntimeException(
                    'Scanner TLS certificate days remaining is inconsistent.'
                );
            }
        }

        $serial = $this->nullableTlsString(
            $certificate['serial'] ?? null,
            'certificate serial',
            512
        );

        $signature = $this->nullableTlsString(
            $certificate['signature_algorithm'] ?? null,
            'certificate signature algorithm',
            255
        );

        $fingerprint = $certificate[
            'sha256_fingerprint'
        ] ?? null;

        if (
            $fingerprint !== null &&
            (
                ! is_string($fingerprint) ||
                strlen($fingerprint) > 128 ||
                ! preg_match(
                    '/^[0-9a-f]{2}(?::?[0-9a-f]{2}){31}$/i',
                    $fingerprint
                )
            )
        ) {
            throw new RuntimeException(
                'Scanner TLS certificate fingerprint is invalid.'
            );
        }

        return [
            'subject' => $subject,
            'issuer' => $issuer,
            'subject_details' => $subjectDetails,
            'issuer_details' => $issuerDetails,
            'subject_alt_names' => $validatedSans,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'days_remaining' => $days,
            'expired' => $expired,
            'not_yet_valid' => $notYetValid,
            'serial' => $serial,
            'signature_algorithm' => $signature,
            'sha256_fingerprint' => $fingerprint !== null
                ? strtolower($fingerprint)
                : null,
        ];
    }

    private function validateTlsChainItem(
        mixed $item,
        int $expectedPosition
    ): array {
        if (! is_array($item)) {
            throw new RuntimeException(
                'Scanner TLS certificate chain item is invalid.'
            );
        }

        $this->assertOnlyKeys(
            $item,
            [
                'position',
                'subject',
                'issuer',
                'serial',
                'valid_from',
                'valid_to',
            ],
            'Scanner TLS certificate chain item contains unknown fields.'
        );

        if (
            ($item['position'] ?? null)
            !== $expectedPosition
        ) {
            throw new RuntimeException(
                'Scanner TLS certificate chain position is invalid.'
            );
        }

        return [
            'position' => $expectedPosition,
            'subject' => $this->nullableTlsString(
                $item['subject'] ?? null,
                'chain subject',
                self::MAX_TLS_NAME_LENGTH
            ),
            'issuer' => $this->nullableTlsString(
                $item['issuer'] ?? null,
                'chain issuer',
                self::MAX_TLS_NAME_LENGTH
            ),
            'serial' => $this->nullableTlsString(
                $item['serial'] ?? null,
                'chain serial',
                512
            ),
            'valid_from' => $this->nullableTlsDate(
                $item['valid_from'] ?? null,
                'chain valid_from'
            ),
            'valid_to' => $this->nullableTlsDate(
                $item['valid_to'] ?? null,
                'chain valid_to'
            ),
        ];
    }

    private function validateTlsDetails(
        mixed $details,
        string $name
    ): array {
        if (
            ! is_array($details) ||
            count($details) > self::MAX_TLS_DETAIL_FIELDS
        ) {
            throw new RuntimeException(
                "Scanner TLS {$name} details are invalid."
            );
        }

        $validated = [];

        foreach ($details as $key => $value) {
            if (
                ! is_string($key) ||
                $key === '' ||
                strlen($key) > 128 ||
                ! is_string($value) ||
                strlen($value)
                    > self::MAX_TLS_DETAIL_VALUE_LENGTH
            ) {
                throw new RuntimeException(
                    "Scanner TLS {$name} details are invalid."
                );
            }

            $validated[$key] = $value;
        }

        return $validated;
    }

    private function validateTlsError(
        mixed $error,
        string $expectedType
    ): array {
        if (! is_array($error)) {
            throw new RuntimeException(
                'Scanner TLS error payload is invalid.'
            );
        }

        $allowed = ['type', 'message'];

        if ($expectedType === 'tls_connection_failed') {
            $allowed[] = 'code';
        }

        $this->assertOnlyKeys(
            $error,
            $allowed,
            'Scanner TLS error payload contains unknown fields.'
        );

        if (($error['type'] ?? null) !== $expectedType) {
            throw new RuntimeException(
                'Scanner TLS error type is invalid.'
            );
        }

        $message = $this->nullableTlsString(
            $error['message'] ?? null,
            'error message',
            1_024
        );

        if ($message === null) {
            throw new RuntimeException(
                'Scanner TLS error message is invalid.'
            );
        }

        $validated = [
            'type' => $expectedType,
            'message' => $message,
        ];

        if ($expectedType === 'tls_connection_failed') {
            $code = $error['code'] ?? null;

            if (
                ! is_int($code) ||
                $code < 0 ||
                $code > 1_000_000
            ) {
                throw new RuntimeException(
                    'Scanner TLS error code is invalid.'
                );
            }

            $validated['code'] = $code;
        }

        return $validated;
    }

    private function nullableTlsString(
        mixed $value,
        string $field,
        int $maxLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (
            ! is_string($value) ||
            strlen($value) > $maxLength
        ) {
            throw new RuntimeException(
                "Scanner TLS {$field} is invalid."
            );
        }

        return $value;
    }

    private function nullableTlsDate(
        mixed $value,
        string $field
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (
            ! is_string($value) ||
            strlen($value) > 64 ||
            strtotime($value) === false
        ) {
            throw new RuntimeException(
                "Scanner TLS {$field} is invalid."
            );
        }

        return $value;
    }

    private function assertOnlyKeys(
        array $value,
        array $allowed,
        string $message
    ): void {
        foreach (array_keys($value) as $key) {
            if (
                ! is_string($key) ||
                ! in_array($key, $allowed, true)
            ) {
                throw new RuntimeException($message);
            }
        }
    }

    private function validateDnsIntelligence(
        mixed $dns
    ): array {
        if (! is_array($dns)) {
            throw new RuntimeException(
                'Scanner DNS intelligence payload is invalid.'
            );
        }

        if (
            ($dns['engine_version'] ?? null)
            !== 'dns-intelligence-v1'
        ) {
            throw new RuntimeException(
                'Scanner DNS intelligence engine version is invalid.'
            );
        }

        $hostname = $dns['hostname'] ?? null;

        if (
            ! is_string($hostname) ||
            $hostname === '' ||
            strlen($hostname) > 253 ||
            $hostname !== strtolower($hostname) ||
            str_ends_with($hostname, '.') ||
            filter_var(
                $hostname,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            ) === false
        ) {
            throw new RuntimeException(
                'Scanner DNS intelligence hostname is invalid.'
            );
        }

        $records = $dns['records'] ?? null;

        if (! is_array($records)) {
            throw new RuntimeException(
                'Scanner DNS intelligence records are invalid.'
            );
        }

        if (
            array_keys($records)
            !== self::DNS_RECORD_TYPES
        ) {
            throw new RuntimeException(
                'Scanner DNS intelligence record types are invalid.'
            );
        }

        $validatedRecords = [];

        foreach (self::DNS_RECORD_TYPES as $type) {
            $collection = $records[$type];

            if (! is_array($collection)) {
                throw new RuntimeException(
                    "Scanner DNS {$type} records are invalid."
                );
            }

            if (
                count($collection)
                > self::MAX_DNS_RECORDS_PER_TYPE
            ) {
                throw new RuntimeException(
                    "Scanner DNS {$type} records exceed the limit."
                );
            }

            $validatedRecords[$type] = [];

            foreach ($collection as $record) {
                $validatedRecords[$type][] =
                    $this->validateDnsRecord(
                        $record,
                        $type
                    );
            }
        }

        $summary = $dns['summary'] ?? null;

        if (! is_array($summary)) {
            throw new RuntimeException(
                'Scanner DNS intelligence summary is invalid.'
            );
        }

        $summaryMap = [
            'a' => 'A',
            'aaaa' => 'AAAA',
            'cname' => 'CNAME',
            'mx' => 'MX',
            'ns' => 'NS',
            'txt' => 'TXT',
            'caa' => 'CAA',
            'soa' => 'SOA',
        ];

        if (
            array_keys($summary)
            !== array_keys($summaryMap)
        ) {
            throw new RuntimeException(
                'Scanner DNS intelligence summary fields are invalid.'
            );
        }

        $validatedSummary = [];

        foreach ($summaryMap as $field => $type) {
            $count = $summary[$field];

            if (
                ! is_int($count) ||
                $count < 0 ||
                $count !== count(
                    $validatedRecords[$type]
                )
            ) {
                throw new RuntimeException(
                    "Scanner DNS summary {$field} is inconsistent."
                );
            }

            $validatedSummary[$field] = $count;
        }

        $security = $dns['security'] ?? null;

        $securityFields = [
            'spf_present',
            'dmarc_present',
            'caa_present',
            'multiple_nameservers',
        ];

        if (
            ! is_array($security) ||
            array_keys($security) !== $securityFields
        ) {
            throw new RuntimeException(
                'Scanner DNS intelligence security summary is invalid.'
            );
        }

        $validatedSecurity = [];

        foreach ($securityFields as $field) {
            if (! is_bool($security[$field])) {
                throw new RuntimeException(
                    "Scanner DNS security field {$field} is invalid."
                );
            }

            $validatedSecurity[$field] =
                $security[$field];
        }

        if (
            $validatedSecurity['caa_present']
            !== ($validatedRecords['CAA'] !== [])
        ) {
            throw new RuntimeException(
                'Scanner DNS CAA security summary is inconsistent.'
            );
        }

        if (
            $validatedSecurity['multiple_nameservers']
            !== (count($validatedRecords['NS']) >= 2)
        ) {
            throw new RuntimeException(
                'Scanner DNS nameserver security summary is inconsistent.'
            );
        }

        $dmarcRecords = $dns['dmarc_records'] ?? null;

        if (
            ! is_array($dmarcRecords) ||
            count($dmarcRecords)
                > self::MAX_DNS_RECORDS_PER_TYPE
        ) {
            throw new RuntimeException(
                'Scanner DNS DMARC records are invalid.'
            );
        }

        $validatedDmarc = [];

        foreach ($dmarcRecords as $record) {
            $validatedDmarc[] =
                $this->validateDnsRecord(
                    $record,
                    'TXT'
                );
        }

        $spfPresent = $this->dnsPolicyPresent(
            $validatedRecords['TXT'],
            'v=spf1'
        );

        $dmarcPresent = $this->dnsPolicyPresent(
            $validatedDmarc,
            'v=dmarc1'
        );

        if (
            $validatedSecurity['spf_present']
            !== $spfPresent
        ) {
            throw new RuntimeException(
                'Scanner DNS SPF security summary is inconsistent.'
            );
        }

        if (
            $validatedSecurity['dmarc_present']
            !== $dmarcPresent
        ) {
            throw new RuntimeException(
                'Scanner DNS DMARC security summary is inconsistent.'
            );
        }

        $duration = $dns['duration_ms'] ?? null;

        if (
            ! is_int($duration) ||
            $duration < 0 ||
            $duration > self::MAX_DNS_DURATION_MS
        ) {
            throw new RuntimeException(
                'Scanner DNS intelligence duration is invalid.'
            );
        }

        return [
            'engine_version' => 'dns-intelligence-v1',
            'hostname' => $hostname,
            'records' => $validatedRecords,
            'summary' => $validatedSummary,
            'security' => $validatedSecurity,
            'dmarc_records' => $validatedDmarc,
            'duration_ms' => $duration,
        ];
    }

    private function validateDnsRecord(
        mixed $record,
        string $expectedType
    ): array {
        if (! is_array($record)) {
            throw new RuntimeException(
                'Scanner DNS record is invalid.'
            );
        }

        $allowed = [
            'host',
            'class',
            'ttl',
            'type',
            'ip',
            'ipv6',
            'target',
            'pri',
            'weight',
            'port',
            'txt',
            'entries',
            'value',
            'tag',
            'mname',
            'rname',
            'serial',
            'refresh',
            'retry',
            'expire',
            'minimum-ttl',
        ];

        foreach (array_keys($record) as $field) {
            if (! in_array($field, $allowed, true)) {
                throw new RuntimeException(
                    'Scanner DNS record contains an unsupported field.'
                );
            }
        }

        if (
            isset($record['type']) &&
            (
                ! is_string($record['type']) ||
                strtoupper($record['type'])
                    !== $expectedType
            )
        ) {
            throw new RuntimeException(
                'Scanner DNS record type is inconsistent.'
            );
        }

        foreach ($record as $field => $value) {
            if ($field === 'entries') {
                if (
                    ! is_array($value) ||
                    count($value) > 32
                ) {
                    throw new RuntimeException(
                        'Scanner DNS record entries are invalid.'
                    );
                }

                foreach ($value as $entry) {
                    if (
                        ! is_string($entry) ||
                        strlen($entry)
                            > self::MAX_DNS_TXT_LENGTH
                    ) {
                        throw new RuntimeException(
                            'Scanner DNS record entry exceeds the limit.'
                        );
                    }
                }

                continue;
            }

            if (
                ! is_null($value) &&
                ! is_string($value) &&
                ! is_int($value)
            ) {
                throw new RuntimeException(
                    'Scanner DNS record value is invalid.'
                );
            }

            if (
                $field === 'txt' &&
                is_string($value) &&
                strlen($value)
                    > self::MAX_DNS_TXT_LENGTH
            ) {
                throw new RuntimeException(
                    'Scanner DNS TXT record exceeds the limit.'
                );
            }
        }

        try {
            $encoded = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            throw new RuntimeException(
                'Scanner DNS record is not valid JSON.'
            );
        }

        if (
            strlen($encoded)
            > self::MAX_DNS_RECORD_BYTES
        ) {
            throw new RuntimeException(
                'Scanner DNS record exceeds the size limit.'
            );
        }

        return $record;
    }

    private function dnsPolicyPresent(
        array $records,
        string $prefix
    ): bool {
        $prefix = strtolower($prefix);

        foreach ($records as $record) {
            $txt = $record['txt'] ?? null;

            if (
                is_string($txt) &&
                str_starts_with(
                    strtolower(trim($txt)),
                    $prefix
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function requiredString(
        mixed $value,
        string $field,
        int $maxLength
    ): string {
        if (! is_string($value)) {
            throw new RuntimeException(
                "Scanner finding {$field} must be a string."
            );
        }

        $value = trim($value);

        if ($value === '') {
            throw new RuntimeException(
                "Scanner finding {$field} must not be empty."
            );
        }

        if (strlen($value) > $maxLength) {
            throw new RuntimeException(
                "Scanner finding {$field} exceeds its length limit."
            );
        }

        return $value;
    }

    private function boundedString(
        mixed $value,
        string $field,
        int $maxLength
    ): string {
        if (! is_string($value)) {
            throw new RuntimeException(
                "Scanner finding {$field} must be a string."
            );
        }

        if (strlen($value) > $maxLength) {
            throw new RuntimeException(
                "Scanner finding {$field} exceeds its length limit."
            );
        }

        return $value;
    }
}
