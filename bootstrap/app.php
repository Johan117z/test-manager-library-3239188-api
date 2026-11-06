<?php

use App\Domain\Shared\BusinessRuleViolation;
use App\Http\Errors\ApiErrorRenderer;
use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => AuthenticateToken::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never log request input: passwords must not reach the logs (RNF-3).
        $exceptions->dontFlash(['password', 'password_confirmation']);
        // Business outcomes (409, 422, ...) are answers, not failures: they are not logged.
        $exceptions->dontReport([BusinessRuleViolation::class]);

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorRenderer::render($e);
            }
        });
    })->create();
