<?php

namespace App\Services\Scanner;

use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;

class DockerScannerRuntime implements ScannerExecutionRuntime
{
    private const MAX_STDIN_BYTES = 65_536;
    private const MAX_STDOUT_BYTES = 2_097_152;
    private const MAX_STDERR_BYTES = 16_384;
    private const PROCESS_TIMEOUT_SECONDS = 45.0;

    public function __construct(
        private readonly BoundedProcessRunner $processRunner
    ) {
    }

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

        $process = new Process(
            $this->command(),
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

        if ($execution['exit_code'] !== 0) {
            throw new RuntimeException(
                'Scanner container execution failed.'
            );
        }

        if (trim($execution['stderr']) !== '') {
            throw new RuntimeException(
                'Scanner container produced unexpected stderr output.'
            );
        }

        if (trim($execution['stdout']) === '') {
            throw new RuntimeException(
                'Scanner container produced no result.'
            );
        }

        try {
            $envelope = json_decode(
                $execution['stdout'],
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Scanner container returned invalid JSON.',
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
                'Scanner container returned an invalid result envelope.'
            );
        }

        return $envelope['result'];
    }

    private function command(): array
    {
        $image = $this->validatedImage();
        $network = $this->validatedNetwork();
        $memory = $this->validatedMemory();
        $cpus = $this->validatedCpus();
        $pidsLimit = $this->validatedPidsLimit();

        return [
            'docker',
            'run',
            '--rm',
            '--interactive',

            /*
             * Filesystem and privilege isolation.
             */
            '--read-only',
            '--cap-drop=ALL',
            '--security-opt=no-new-privileges',
            '--user=10001:10001',

            /*
             * Resource safety ceilings.
             */
            '--pids-limit=' . $pidsLimit,
            '--memory=' . $memory,
            '--cpus=' . $cpus,

            /*
             * Temporary writable storage without modifying the image.
             */
            '--tmpfs=/tmp:rw,noexec,nosuid,nodev,size=16m',

            /*
             * This selects the operator-controlled scanner network.
             * Actual egress filtering remains a separate host/network
             * security boundary.
             */
            '--network=' . $network,

            $image,
        ];
    }

    private function validatedImage(): string
    {
        $value = trim(
            (string) config(
                'scanner.container.image',
                'crypticx/scanner-runtime:local'
            )
        );

        /*
         * Permit ordinary registry/repository/tag/digest syntax while
         * rejecting whitespace, option-like values and control characters.
         */
        if (
            $value === '' ||
            strlen($value) > 255 ||
            $value[0] === '-' ||
            preg_match('/[\s\x00-\x1F\x7F]/', $value) === 1 ||
            preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._\/:@+-]*$/',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'Scanner container image configuration is invalid.'
            );
        }

        return $value;
    }

    private function validatedNetwork(): string
    {
        $value = trim(
            (string) config(
                'scanner.container.network',
                'bridge'
            )
        );

        if (
            $value === '' ||
            strlen($value) > 128 ||
            preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'Scanner container network configuration is invalid.'
            );
        }

        return $value;
    }

    private function validatedMemory(): string
    {
        $value = strtolower(
            trim(
                (string) config(
                    'scanner.container.memory',
                    '256m'
                )
            )
        );

        if (
            preg_match(
                '/^([1-9][0-9]{0,5})([kmg])$/',
                $value,
                $matches
            ) !== 1
        ) {
            throw new RuntimeException(
                'Scanner container memory configuration is invalid.'
            );
        }

        $quantity = (int) $matches[1];
        $unit = $matches[2];

        $bytes = match ($unit) {
            'k' => $quantity * 1024,
            'm' => $quantity * 1024 * 1024,
            'g' => $quantity * 1024 * 1024 * 1024,
        };

        $minimumBytes = 64 * 1024 * 1024;
        $maximumBytes = 4 * 1024 * 1024 * 1024;

        if (
            $bytes < $minimumBytes ||
            $bytes > $maximumBytes
        ) {
            throw new RuntimeException(
                'Scanner container memory configuration is invalid.'
            );
        }

        return $value;
    }

    private function validatedCpus(): string
    {
        $raw = trim(
            (string) config(
                'scanner.container.cpus',
                '0.50'
            )
        );

        if (
            preg_match(
                '/^(?:0\.[0-9]{1,3}|[1-9][0-9]?(?:\.[0-9]{1,3})?)$/',
                $raw
            ) !== 1
        ) {
            throw new RuntimeException(
                'Scanner container CPU configuration is invalid.'
            );
        }

        $value = (float) $raw;

        if ($value < 0.10 || $value > 8.0) {
            throw new RuntimeException(
                'Scanner container CPU configuration is invalid.'
            );
        }

        return $raw;
    }

    private function validatedPidsLimit(): string
    {
        $value = (int) config(
            'scanner.container.pids_limit',
            64
        );

        if ($value < 16 || $value > 512) {
            throw new RuntimeException(
                'Scanner container PID configuration is invalid.'
            );
        }

        return (string) $value;
    }

    private function minimalEnvironment(): array
    {
        /*
         * The docker CLI itself receives no Laravel application secrets.
         * Explicit false values remove inherited variables from the child.
         */
        $environment = [];

        $inherited = getenv();

        if (is_array($inherited)) {
            foreach (array_keys($inherited) as $name) {
                if (is_string($name) && $name !== '') {
                    $environment[$name] = false;
                }
            }
        }

        foreach (['PATH', 'HOME'] as $name) {
            $value = getenv($name);

            if (is_string($value) && $value !== '') {
                $environment[$name] = $value;
            }
        }

        return $environment;
    }
}
