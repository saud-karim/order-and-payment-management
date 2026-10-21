<?php

use App\Exceptions\InvalidMoneyException;
use App\Exceptions\OrderConflictException;
use App\Exceptions\PaymentConflictException;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    'The given data was invalid.',
                    422,
                    $exception->errors()
                );
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (TokenExpiredException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Token has expired.', 401);
            }
        });

        $exceptions->render(function (TokenInvalidException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Token is invalid.', 401);
            }
        });

        $exceptions->render(function (JWTException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Could not process token.', 401);
            }
        });

        $exceptions->render(function (OrderConflictException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage(), 409);
            }
        });

        $exceptions->render(function (PaymentConflictException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage(), 409);
            }
        });

        $exceptions->render(function (InvalidMoneyException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage(), 422);
            }
        });
    })->create();
