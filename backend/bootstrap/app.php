<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
            $status = 500;
            $code = 'SERVER_ERROR';
            $message = 'An unexpected error occurred.';
            $details = null;

            if ($exception instanceof ValidationException) {
                $status = 422;
                $code = 'VALIDATION_ERROR';
                $message = 'The given data was invalid.';
                $details = $exception->errors();
            } elseif ($exception instanceof AuthenticationException) {
                $status = 401;
                $code = 'UNAUTHENTICATED';
                $message = 'Authentication is required.';
            } elseif ($exception instanceof ModelNotFoundException) {
                $status = 404;
                $code = 'RESOURCE_NOT_FOUND';
                $message = 'The requested resource was not found.';
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $code = match ($status) {
                    401 => 'UNAUTHENTICATED',
                    403 => 'FORBIDDEN',
                    404 => 'ROUTE_NOT_FOUND',
                    429 => 'TOO_MANY_REQUESTS',
                    default => 'HTTP_ERROR',
                };
                $message = match (true) {
                    $status >= 500 => 'An unexpected error occurred.',
                    $status === 404 => 'The requested route was not found.',
                    default => $exception->getMessage() ?: 'The request could not be completed.',
                };
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                    'details' => $details,
                ],
                'meta' => [
                    'request_id' => $requestId,
                ],
            ], $status, [
                'X-Request-Id' => $requestId,
            ]);
        });
    })->create();
