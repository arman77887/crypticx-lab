#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * CrypticX Lab — Standalone HTTP Scanner Runtime V1
 *
 * Protocol:
 *   stdin  = exactly one bounded JSON Scanner Request V1
 *   stdout = exactly one JSON result/envelope
 *
 * This runtime does NOT bootstrap Laravel.
 */

use App\Services\Scanner\HttpScannerEngine;
use App\Services\Scanner\NetworkScannerEngine;
use App\Services\Scanner\StandaloneCurlTransport;
use App\Services\Scanner\StreamTcpConnector;

const MAX_STDIN_BYTES = 65_536;

function emit(array $payload, int $exitCode): never
{
    try {
        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR |
            JSON_UNESCAPED_SLASHES
        );
    } catch (Throwable) {
        $json = '{"ok":false,"error":{"type":"runtime_json_encode_failed"}}';
        $exitCode = 70;
    }

    fwrite(STDOUT, $json . PHP_EOL);
    exit($exitCode);
}

function failRuntime(
    string $type,
    int $exitCode
): never {
    emit([
        'ok' => false,
        'error' => [
            'type' => $type,
        ],
    ], $exitCode);
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (! is_file($autoload)) {
    failRuntime(
        'runtime_autoload_missing',
        70
    );
}

require $autoload;

/*
 * Read at most MAX_STDIN_BYTES + 1.
 * The extra byte lets us distinguish an exactly-full valid request
 * from an oversized request without unbounded input buffering.
 */
$input = stream_get_contents(
    STDIN,
    MAX_STDIN_BYTES + 1
);

if ($input === false) {
    failRuntime(
        'runtime_input_read_failed',
        65
    );
}

if (strlen($input) > MAX_STDIN_BYTES) {
    failRuntime(
        'runtime_input_too_large',
        65
    );
}

if (trim($input) === '') {
    failRuntime(
        'runtime_input_empty',
        65
    );
}

try {
    $request = json_decode(
        $input,
        true,
        64,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException) {
    failRuntime(
        'runtime_invalid_json',
        65
    );
}

if (! is_array($request)) {
    failRuntime(
        'runtime_request_not_object',
        65
    );
}

try {
    $scanner = $request['scanner'] ?? 'http';

    if ($scanner === 'network') {
        $engine = new NetworkScannerEngine(
            new StreamTcpConnector()
        );
    } elseif ($scanner === 'http') {
        $engine = new HttpScannerEngine(
            new StandaloneCurlTransport()
        );
    } else {
        throw new RuntimeException(
            'Unsupported scanner mode.'
        );
    }

    $result = $engine->scan($request);

    emit([
        'ok' => true,
        'result' => $result,
    ], 0);
} catch (Throwable $exception) {
    /*
     * Do not expose exception messages, stack traces, filesystem paths,
     * environment values, or secrets over the scanner protocol.
     */
    emit([
        'ok' => false,
        'error' => [
            'type' => 'scanner_execution_failed',
            'class' => get_class($exception),
        ],
    ], 1);
}
