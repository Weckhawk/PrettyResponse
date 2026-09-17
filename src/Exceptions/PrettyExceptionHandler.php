<?php

namespace Weckhawk\PrettyResponse\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;
use Weckhawk\PrettyResponse\ApiResponse;

class PrettyExceptionHandler
{
    public static function register($handler): void
    {
        $handler->renderable(function (Throwable $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::validationError(
                    errors: $e->errors(),
                    message: $e->getMessage(),
                )->toJsonResponse(),

                $e instanceof ModelNotFoundException => ApiResponse::notFound()->toJsonResponse(),

                $e instanceof AuthenticationException => ApiResponse::unauthorized()->toJsonResponse(),

                $e instanceof AuthorizationException => ApiResponse::forbidden(
                    $e->getMessage() ?: 'Forbidden',
                )->toJsonResponse(),

                $e instanceof NotFoundHttpException => ApiResponse::notFound()->toJsonResponse(),

                $e instanceof MethodNotAllowedHttpException => ApiResponse::error(
                    message: 'Method not allowed',
                    statusCode: ApiResponse::HTTP_METHOD_NOT_ALLOWED,
                )->toJsonResponse(),

                $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                    message: 'Too many requests',
                    statusCode: ApiResponse::HTTP_TOO_MANY_REQUESTS,
                )->toJsonResponse(),

                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    message: $e->getMessage() ?: 'An error occurred',
                    statusCode: $e->getStatusCode(),
                )->toJsonResponse(),

                default => ApiResponse::serverError(
                    message: config('app.debug') ? $e->getMessage() : 'Internal server error',
                )->toJsonResponse(),
            };
        });
    }
}