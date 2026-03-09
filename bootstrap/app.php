<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Middleware applicati SOLO al gruppo API
        $middleware->api(append: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        // Alias middleware
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (\Throwable $e, $request) {

            // Applica custom JSON handler SOLO per api/*
            if (! str_starts_with($request->path(), 'api/')) {
                return null;
            }

            // 401 Unauthenticated (Sanctum / auth)
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'errors'  => null,
                    'data'    => null,
                ], 401);
            }

            // 403 Forbidden (policy/gate)
            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'errors'  => null,
                    'data'    => null,
                ], 403);
            }

            // Validation (422)
            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error.',
                    'errors'  => $e->errors(),
                    'data'    => null,
                ], 422);
            }

            // HttpException: 401/403/404/409 ecc.
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                $defaultMessage = match ($status) {
                    401 => 'Unauthenticated.',
                    403 => 'Forbidden.',
                    404 => 'Not found.',
                    default => 'Error.',
                };

                $message = $e->getMessage() ?: $defaultMessage;

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors'  => null,
                    'data'    => null,
                ], $status);
            }

            // Fallback (500)
            return response()->json([
                'success' => false,
                'message' => 'Server error.',
                'errors'  => config('app.debug') ? [
                    'exception' => $e->getMessage(),
                    'type' => get_class($e),
                ] : null,
                'data'    => null,
            ], 500);
        });
    })
    ->create();
