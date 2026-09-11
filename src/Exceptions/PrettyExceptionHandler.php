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

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    message: $e->getMessage() ?: 'An error occurred',
                    statusCode: $e->getStatusCode(),
                )->toJsonResponse();
            }

            return ApiResponse::serverError(
                message: config('app.debug') ? $e->getMessage() : 'Internal server error',
            )->toJsonResponse();
        });

        $handler->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::notFound()->toJsonResponse();
            }
        });

        $handler->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Method not allowed',
                    statusCode: ApiResponse::HTTP_METHOD_NOT_ALLOWED,
                )->toJsonResponse();
            }
        });

        $handler->renderable(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Too many requests',
                    statusCode: ApiResponse::HTTP_TOO_MANY_REQUESTS,
                )->toJsonResponse();
            }
        });

        $handler->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::validationError(
                    errors: $e->errors(),
                    message: $e->getMessage(),
                )->toJsonResponse();
            }
        });

        $handler->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::notFound()->toJsonResponse();
            }
        });

        $handler->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::unauthorized()->toJsonResponse();
            }
        });

        $handler->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::forbidden($e->getMessage() ?: 'Forbidden')->toJsonResponse();
            }
        });
    }
}