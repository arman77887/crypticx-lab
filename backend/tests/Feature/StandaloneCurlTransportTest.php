<?php

namespace Tests\Feature;

use App\Services\Scanner\StandaloneCurlTransport;
use Tests\TestCase;

class StandaloneCurlTransportTest extends TestCase
{
    private const PORT = 18991;

    /** @var resource|null */
    private static $serverProcess = null;

    /** @var array<int, resource> */
    private static array $serverPipes = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $router = dirname(__DIR__) .
            '/Fixtures/standalone-transport/router.php';

        if (! is_file($router)) {
            throw new \RuntimeException(
                'Standalone transport test router is missing.'
            );
        }

        /*
         * Use the PHP executable running PHPUnit rather than relying
         * on PATH resolution.
         */
        $command = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . self::PORT,
            $router,
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command,
            $descriptorSpec,
            $pipes,
            dirname(__DIR__, 2)
        );

        if (! is_resource($process)) {
            throw new \RuntimeException(
                'Unable to start standalone transport test server.'
            );
        }

        self::$serverProcess = $process;
        self::$serverPipes = $pipes;

        /*
         * The server starts asynchronously. Bound the readiness wait
         * so a broken fixture cannot hang the test suite.
         */
        $deadline = microtime(true) + 3.0;
        $ready = false;

        do {
            $socket = @fsockopen(
                '127.0.0.1',
                self::PORT,
                $errno,
                $error,
                0.1
            );

            if (is_resource($socket)) {
                fclose($socket);
                $ready = true;
                break;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        if (! $ready) {
            self::stopFixtureServer();

            throw new \RuntimeException(
                'Standalone transport test server did not become ready.'
            );
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::stopFixtureServer();

        parent::tearDownAfterClass();
    }

    private static function stopFixtureServer(): void
    {
        if (is_resource(self::$serverProcess)) {
            @proc_terminate(self::$serverProcess);

            /*
             * Give the child a short bounded window to exit before
             * closing the process handle.
             */
            $deadline = microtime(true) + 1.0;

            do {
                $status = proc_get_status(
                    self::$serverProcess
                );

                if (! ($status['running'] ?? false)) {
                    break;
                }

                usleep(20_000);
            } while (microtime(true) < $deadline);

            @proc_close(self::$serverProcess);
        }

        foreach (self::$serverPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        self::$serverProcess = null;
        self::$serverPipes = [];
    }

    public function test_successful_response_is_streamed_and_bounded(): void
    {
        $transport = new StandaloneCurlTransport();

        $result = $transport->get(
            'http://scanner-test.local:' . self::PORT . '/ok',
            'scanner-test.local',
            self::PORT,
            ['127.0.0.1'],
            2,
            5,
            1024
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['successful']);
        $this->assertSame(5, $result['body_bytes']);
        $this->assertNull($result['error']);

        $headers = array_change_key_case(
            $result['headers'],
            CASE_LOWER
        );

        $this->assertSame(
            ['transport-v1'],
            $headers['x-scanner-test'] ?? null
        );
    }

    public function test_redirect_is_not_followed(): void
    {
        $transport = new StandaloneCurlTransport();

        $result = $transport->get(
            'http://scanner-test.local:' . self::PORT . '/redirect',
            'scanner-test.local',
            self::PORT,
            ['127.0.0.1'],
            2,
            5,
            1024
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(302, $result['status']);
        $this->assertFalse($result['successful']);

        $headers = array_change_key_case(
            $result['headers'],
            CASE_LOWER
        );

        $this->assertSame(
            ['/ok'],
            $headers['location'] ?? null
        );

        /*
         * Redirect body is "redirect" = 8 bytes.
         * If cURL followed /ok this would instead be 5 bytes.
         */
        $this->assertSame(8, $result['body_bytes']);
    }

    public function test_response_larger_than_limit_is_rejected(): void
    {
        $transport = new StandaloneCurlTransport();

        $result = $transport->get(
            'http://scanner-test.local:' . self::PORT . '/large',
            'scanner-test.local',
            self::PORT,
            ['127.0.0.1'],
            2,
            5,
            1024
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(
            'response_too_large',
            $result['error']['type']
        );
        $this->assertFalse(
            $result['error']['retryable']
        );
        $this->assertSame(
            1024,
            $result['error']['limit_bytes']
        );
    }
}
