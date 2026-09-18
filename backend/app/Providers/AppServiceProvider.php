<?php

namespace App\Providers;

use App\Services\Scanner\DockerScannerRuntime;
use App\Services\Scanner\DnsRecordResolver;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\LaravelReconHttpTransport;
use App\Services\Scanner\PublicRdapClient;
use App\Services\Scanner\RdapClient;
use App\Services\Scanner\ReconHttpTransport;
use App\Services\Scanner\SystemDnsRecordResolver;
use App\Services\Scanner\HttpTransport;
use App\Services\Scanner\IsolatedScannerProcess;
use App\Services\Scanner\ScannerExecutionRuntime;
use App\Services\Scanner\LaravelHttpTransport;
use App\Services\Scanner\SystemDnsResolver;
use App\Services\WorkerHeartbeatService;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Scanner execution boundary.
         *
         * Development currently uses the standalone subprocess runtime.
         * Production container mode will replace this binding explicitly.
         * There must be no silent fallback from a required container
         * runtime to the host subprocess runtime.
         */
        $this->app->bind(
            ScannerExecutionRuntime::class,
            function ($app): ScannerExecutionRuntime {
                $runtime = strtolower(
                    trim((string) config('scanner.runtime', 'process'))
                );

                return match ($runtime) {
                    'process' => $app->make(
                        IsolatedScannerProcess::class
                    ),
                    'container' => $app->make(
                        DockerScannerRuntime::class
                    ),
                    default => throw new \RuntimeException(
                        'Unsupported scanner execution runtime.'
                    ),
                };
            }
        );

        /*
         * Scanner HTTP transport boundary.
         *
         * HttpAssessmentService depends only on the transport contract.
         * The Laravel implementation remains the in-process transport
         * until the isolated scanner runtime becomes the production binding.
         */
        $this->app->bind(
            HttpTransport::class,
            LaravelHttpTransport::class
        );

        $this->app->bind(
            DnsResolver::class,
            SystemDnsResolver::class
        );

        $this->app->bind(
            DnsRecordResolver::class,
            SystemDnsRecordResolver::class
        );

        $this->app->bind(
            ReconHttpTransport::class,
            LaravelReconHttpTransport::class
        );

        $this->app->bind(
            RdapClient::class,
            PublicRdapClient::class
        );

        /*
         * One heartbeat service instance per long-running PHP process.
         */
        $this->app->singleton(
            WorkerHeartbeatService::class,
            fn () => new WorkerHeartbeatService()
        );
    }

    public function boot(): void
    {
        /*
         * Authentication abuse controls.
         *
         * The submitted email address is normalized then SHA-256 hashed
         * before being used in a limiter key. Raw account identifiers
         * are never written to the rate-limiter cache key.
         *
         * Each endpoint has:
         *   1. an IP-wide ceiling, and
         *   2. a tighter identity + IP bucket.
         */
        RateLimiter::for(
            'auth-login',
            function (Request $request): array {
                $ip = (string) (
                    $request->ip()
                    ?: 'unknown'
                );

                $identityHash = hash(
                    'sha256',
                    strtolower(
                        trim(
                            (string) $request->input(
                                'email',
                                ''
                            )
                        )
                    )
                );

                return [
                    Limit::perMinute(20)
                        ->by(
                            'auth-login-ip:'.$ip
                        ),

                    Limit::perMinute(5)
                        ->by(
                            'auth-login-identity:'
                            .$identityHash
                            .'|ip:'
                            .$ip
                        ),
                ];
            }
        );

        RateLimiter::for(
            'auth-register',
            function (Request $request): array {
                $ip = (string) (
                    $request->ip()
                    ?: 'unknown'
                );

                $identityHash = hash(
                    'sha256',
                    strtolower(
                        trim(
                            (string) $request->input(
                                'email',
                                ''
                            )
                        )
                    )
                );

                return [
                    Limit::perMinute(10)
                        ->by(
                            'auth-register-ip:'.$ip
                        ),

                    Limit::perMinute(3)
                        ->by(
                            'auth-register-identity:'
                            .$identityHash
                            .'|ip:'
                            .$ip
                        ),
                ];
            }
        );

        RateLimiter::for(
            'auth-password-forgot',
            function (Request $request): array {
                $ip = (string) (
                    $request->ip()
                    ?: 'unknown'
                );

                $identityHash = hash(
                    'sha256',
                    strtolower(
                        trim(
                            (string) $request->input(
                                'email',
                                ''
                            )
                        )
                    )
                );

                return [
                    Limit::perMinute(6)
                        ->by('auth-password-forgot-ip:'.$ip),

                    Limit::perMinute(3)
                        ->by(
                            'auth-password-forgot-identity:'
                            .$identityHash
                            .'|ip:'
                            .$ip
                        ),
                ];
            }
        );


        RateLimiter::for(
            'auth-password-reset',
            function (Request $request): Limit {
                $ip = (string) (
                    $request->ip()
                    ?: 'unknown'
                );

                return Limit::perMinute(5)
                    ->by('auth-password-reset-ip:'.$ip);
            }
        );

        RateLimiter::for(
            'contact-submit',
            function (Request $request): Limit {
                $ip = (string) (
                    $request->ip()
                    ?: 'unknown'
                );

                return Limit::perMinute(5)
                    ->by('contact-submit-ip:'.$ip);
            }
        );


        Event::listen(
            Looping::class,
            function (Looping $event): void {
                app(WorkerHeartbeatService::class)->beat(
                    $event->connectionName,
                    $event->queue,
                    ['state' => 'idle_or_polling']
                );
            }
        );

        Event::listen(
            JobProcessing::class,
            function (JobProcessing $event): void {
                app(WorkerHeartbeatService::class)->beat(
                    $event->connectionName,
                    $event->job->getQueue(),
                    [
                        'state' => 'processing',
                        'job' => $event->job->resolveName(),
                    ],
                    true
                );
            }
        );

        Event::listen(
            JobProcessed::class,
            function (JobProcessed $event): void {
                app(WorkerHeartbeatService::class)->beat(
                    $event->connectionName,
                    $event->job->getQueue(),
                    [
                        'state' => 'processed',
                        'job' => $event->job->resolveName(),
                    ],
                    true
                );
            }
        );

        Event::listen(
            JobExceptionOccurred::class,
            function (JobExceptionOccurred $event): void {
                app(WorkerHeartbeatService::class)->beat(
                    $event->connectionName,
                    $event->job->getQueue(),
                    [
                        'state' => 'exception',
                        'job' => $event->job->resolveName(),
                        'exception' => get_class($event->exception),
                    ],
                    true
                );
            }
        );

        Event::listen(
            WorkerStopping::class,
            function (WorkerStopping $event): void {
                app(WorkerHeartbeatService::class)->stop([
                    'state' => 'stopped',
                    'exit_status' => $event->status,
                    'reason' => $event->reason
                        ? (string) $event->reason->name
                        : null,
                ]);
            }
        );
    }
}
