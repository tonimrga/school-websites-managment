<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The "api" rate limiter is defined in AppServiceProvider.
        $middleware->throttleApi();

        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // The public API answers its documented errors with a stable JSON
        // body, also for paths that match no route.
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api', 'api/*')) {
                return null;
            }

            $error = match ($exception->getStatusCode()) {
                404 => 'not_found',
                405 => 'method_not_allowed',
                429 => 'too_many_requests',
                default => null,
            };

            return $error === null
                ? null
                : response()->json(['error' => $error], $exception->getStatusCode(), $exception->getHeaders());
        });
    })->create();
