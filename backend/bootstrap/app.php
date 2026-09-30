<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * Nginx is the only trusted HTTP proxy in front of Laravel.
         * Nginx validates Cloudflare and restores the real visitor IP
         * before forwarding the request to 127.0.0.1:8004.
         */
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1']
        );

        $middleware->prepend(
            \App\Http\Middleware\ForceApiJsonResponse::class
        );

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'trusted.admin.device' =>
                \App\Http\Middleware\RequireTrustedAdminDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
