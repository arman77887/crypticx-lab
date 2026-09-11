<?php

namespace Tests\Feature;

use Tests\TestCase;

class ScannerContainerFilesystemTest extends TestCase
{
    public function test_scanner_build_context_contains_required_minimal_files(): void
    {
        foreach ([
            'docker/scanner/Dockerfile',
            'docker/scanner/.dockerignore',
            'docker/scanner/composer.json',
            'docker/scanner/composer.lock',
            'docker/scanner/scanner-runtime.php',
            'docker/scanner/src/Scanner/HttpScannerEngine.php',
            'docker/scanner/src/Scanner/HttpTransport.php',
            'docker/scanner/src/Scanner/StandaloneCurlTransport.php',
        ] as $path) {
            $this->assertFileExists(
                base_path($path),
                "Missing scanner build-context file: {$path}"
            );
        }

        $this->assertGreaterThan(
            0,
            filesize(base_path('docker/scanner/composer.lock'))
        );
    }

    public function test_scanner_context_contains_no_forbidden_secret_files(): void
    {
        $root = base_path('docker/scanner');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        $forbidden = [];

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            $isForbidden =
                $name === '.env' ||
                str_starts_with($name, '.env.') ||
                $name === 'artisan' ||
                preg_match(
                    '/\.(?:pem|key|p12|pfx)$/i',
                    $name
                ) === 1;

            if ($isForbidden) {
                $forbidden[] = $file->getPathname();
            }
        }

        $this->assertSame([], $forbidden);
    }

    public function test_dockerfile_enforces_immutable_non_root_runtime(): void
    {
        $dockerfile = file_get_contents(
            base_path('docker/scanner/Dockerfile')
        );

        $this->assertIsString($dockerfile);

        foreach ([
            'COPY composer.json composer.lock ./',
            'chown -R root:root /scanner',
            'chmod 0555',
            'chmod 0444',
            'USER 10001:10001',
            'ENTRYPOINT ["php", "/scanner/scanner-runtime.php"]',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $dockerfile
            );
        }
    }

    public function test_dockerfile_does_not_mount_or_copy_backend_tree(): void
    {
        $dockerfile = file_get_contents(
            base_path('docker/scanner/Dockerfile')
        );

        $this->assertIsString($dockerfile);

        foreach ([
            'COPY . ',
            'COPY . .',
            'ADD . ',
            'VOLUME',
            '/var/run/docker.sock',
            '.env',
            'artisan',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $dockerfile
            );
        }
    }

    public function test_runtime_contract_supplies_only_bounded_tmpfs_write_area(): void
    {
        $source = file_get_contents(
            app_path('Services/Scanner/DockerScannerRuntime.php')
        );

        $this->assertIsString($source);

        $this->assertStringContainsString(
            "'--read-only'",
            $source
        );

        $this->assertStringContainsString(
            "'--tmpfs=/tmp:rw,noexec,nosuid,nodev,size=16m'",
            $source
        );

        foreach ([
            '--volume',
            '--mount',
            '/var/run/docker.sock',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
