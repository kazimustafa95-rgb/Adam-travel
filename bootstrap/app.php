<?php

use App\Http\Middleware\EnsureActiveUserAccount;
use App\Http\Middleware\EnsureAuthenticatedAdmin;
use App\Http\Middleware\FlushResolvedApiGuards;
use App\Support\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            FlushResolvedApiGuards::class,
        ]);

        $middleware->alias([
            'active.user' => EnsureActiveUserAccount::class,
            'auth.admin' => EnsureAuthenticatedAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Validation failed.',
                    errors: $exception->errors(),
                    status: $exception->status,
                );
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Authentication is required to access this resource.',
                    errors: [
                        'auth' => [$exception->getMessage() ?: 'Unauthenticated.'],
                    ],
                    status: 401,
                );
            }
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'You are not authorized to access this resource.',
                    errors: [
                        'authorization' => [$exception->getMessage() ?: 'This action is unauthorized.'],
                    ],
                    status: 403,
                );
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'You are not authorized to access this resource.',
                    errors: [
                        'authorization' => [$exception->getMessage() ?: 'This action is unauthorized.'],
                    ],
                    status: 403,
                );
            }
        });

        $exceptions->render(function (ConflictHttpException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'The resource is out of date.',
                    errors: [
                        'conflict' => [$exception->getMessage() ?: 'This request conflicts with a newer server state.'],
                    ],
                    status: 409,
                );
            }
        });
    })->create();
