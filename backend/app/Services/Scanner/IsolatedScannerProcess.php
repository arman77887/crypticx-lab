<?php

namespace App\Services\Scanner;

use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;

class IsolatedScannerProcess implements ScannerExecutionRuntime
{
    public function __construct(
        private readonly BoundedProcessRunner $processRunner =
            new BoundedProcessRunner()
    ) {
    }

    private const MAX_STDIN_BYTES = 65_536;
    private const MAX_STDOUT_BYTES = 2_097_152;
    private const MAX_STDERR_BYTES = 16_384;
    private const PROCESS_TIMEOUT_SECONDS = 45.0;

    public function scan(array $request): array
    {
        try {
            $input = json_encode(
                $request,
                JSON_THROW_ON_ERROR |
                JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Scanner request could not be encoded.',
                0,
                $exception
            );
        }

        if (strlen($input) > self::MAX_STDIN_BYTES) {
            throw new RuntimeException(
                'Scanner request exceeds the runtime input limit.'
            );
        }

        $runtime = base_path('bin/scanner-runtime.php');

        if (! is_file($runtime)) {
            throw new RuntimeException(
                'Standalone scanner runtime is unavailable.'
            );
        }

        /*
         * Array command form bypasses the shell. Neither executable nor
         * runtime path comes from user-controlled scanner input.
         */
        $process = new Process(
            [
                PHP_BINARY,
                $runtime,
            ],
            base_path(),
            $this->minimalEnvironment(),
            $input,
            self::PROCESS_TIMEOUT_SECONDS
        );

        $execution = $this->processRunner->run(
            $process,
            self::MAX_STDOUT_BYTES,
            self::MAX_STDERR_BYTES
        );

        $exitCode = $execution['exit_code'];
        $stdout = $execution['stdout'];
        $stderr = $execution['stderr'];

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Scanner runtime execution failed.'
            );
        }

        if (trim($stderr) !== '') {
            throw new RuntimeException(
                'Scanner runtime produced unexpected stderr output.'
            );
        }

        if (trim($stdout) === '') {
            throw new RuntimeException(
                'Scanner runtime produced no result.'
            );
        }

        try {
            $envelope = json_decode(
                $stdout,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Scanner runtime returned invalid JSON.',
                0,
                $exception
            );
        }

        if (
            ! is_array($envelope) ||
            ($envelope['ok'] ?? null) !== true ||
            ! isset($envelope['result']) ||
            ! is_array($envelope['result'])
        ) {
            throw new RuntimeException(
                'Scanner runtime returned an invalid result envelope.'
            );
        }

        return $envelope['result'];
    }

    private function minimalEnvironment(): array
    {
        /*
         * Symfony Process inherits the parent environment unless variables
         * are explicitly unset with false values.
         *
         * Start by denying every inherited variable, then restore only the
         * small runtime allowlist required by PHP/cURL/TLS.
         */
        $environment = [];

        $inherited = getenv();

        if (is_array($inherited)) {
            foreach (array_keys($inherited) as $name) {
                if (! is_string($name) || $name === '') {
                    continue;
                }

                $environment[$name] = false;
            }
        }

        foreach (
            [
                'PATH',
                'HOME',
                'LANG',
                'LC_ALL',
                'SSL_CERT_FILE',
                'SSL_CERT_DIR',
            ] as $name
        ) {
            $value = getenv($name);

            if (
                is_string($value) &&
                $value !== ''
            ) {
                $environment[$name] = $value;
            }
        }

        return $environment;
    }
}
