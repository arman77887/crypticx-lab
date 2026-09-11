<?php

namespace App\Services\Scanner;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class BoundedProcessRunner
{
    public function run(
        Process $process,
        int $maxStdoutBytes,
        int $maxStderrBytes
    ): array {
        if (
            $maxStdoutBytes < 1 ||
            $maxStderrBytes < 1
        ) {
            throw new RuntimeException(
                'Process output limits must be positive.'
            );
        }

        $stdout = '';
        $stderr = '';

        try {
            $process->start();

            /*
             * getIterator() without ITER_KEEP_OUTPUT clears Symfony's
             * internal stdout/stderr streams as chunks are consumed.
             *
             * This means both our application buffers and Symfony's
             * temporary output buffers remain bounded while the child
             * process is running.
             */
            foreach ($process->getIterator() as $type => $buffer) {
                if ($type === Process::OUT) {
                    if (
                        strlen($stdout) + strlen($buffer) >
                        $maxStdoutBytes
                    ) {
                        $process->stop(0);

                        throw new RuntimeException(
                            'Scanner runtime stdout exceeded its limit.'
                        );
                    }

                    $stdout .= $buffer;

                    continue;
                }

                if (
                    strlen($stderr) + strlen($buffer) >
                    $maxStderrBytes
                ) {
                    $process->stop(0);

                    throw new RuntimeException(
                        'Scanner runtime stderr exceeded its limit.'
                    );
                }

                $stderr .= $buffer;
            }

            $exitCode = $process->getExitCode();
        } catch (ProcessTimedOutException $exception) {
            if ($process->isRunning()) {
                $process->stop(0);
            }

            throw new RuntimeException(
                'Scanner runtime exceeded its execution timeout.',
                0,
                $exception
            );
        }

        if (! is_int($exitCode)) {
            throw new RuntimeException(
                'Scanner runtime did not provide an exit code.'
            );
        }

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
