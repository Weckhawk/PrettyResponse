<?php

namespace Weckhawk\PrettyResponse;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class ApiResponse implements Responsable
{
    use HasHttpStatusCodes;

    /**
     * @param array<string, mixed>|null $errors
     * @param array<string, mixed>|null $meta
     */
    private function __construct(
        private bool $success,
        private int $statusCode,
        private string $message,
        private mixed $data = null,
        private ?array $errors = null,
        private ?array $meta = null,
    ) {}

    // -----------------------------------------------------------------------
    // Main
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $meta
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $statusCode = Response::HTTP_OK,
        ?array $meta = null,
    ): self {
        return new self(
            success: true,
            statusCode: $statusCode,
            message: $message,
            data: $data,
            meta: $meta,
        );
    }

    public static function created(
        mixed $data = null,
        string $message = 'Resource created successfully',
    ): self {
        return new self(
            success: true,
            statusCode: Response::HTTP_CREATED,
            message: $message,
            data: $data,
        );
    }

    public static function noContent(
        string $message = 'Resource deleted successfully',
    ): self {
        return new self(
            success: true,
            statusCode: Response::HTTP_NO_CONTENT,
            message: $message,
        );
    }

    /**
     * @param array<string, mixed>|null $errors
     */
    public static function error(
        string $message = 'An error occurred',
        ?array $errors = null,
        int $statusCode = Response::HTTP_BAD_REQUEST,
    ): self {
        return new self(
            success: false,
            statusCode: $statusCode,
            message: $message,
            errors: $errors,
        );
    }

    public static function notFound(
        string $message = 'Resource not found',
    ): self {
        return self::error($message, statusCode: Response::HTTP_NOT_FOUND);
    }

    public static function unauthorized(
        string $message = 'Unauthorized',
    ): self {
        return self::error($message, statusCode: Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(
        string $message = 'Forbidden',
    ): self {
        return self::error($message, statusCode: Response::HTTP_FORBIDDEN);
    }

    /**
     * @param array<string, mixed> $errors
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed',
    ): self {
        return new self(
            success: false,
            statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            message: $message,
            errors: $errors,
        );
    }

    public static function serverError(
        string $message = 'Internal server error',
    ): self {
        return self::error($message, statusCode: Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function fromException(
        Throwable $exception,
        bool $debug = false,
    ): self {
        if ($exception instanceof ValidationException) {
            return self::validationError(
                errors: $exception->errors(),
                message: $exception->getMessage(),
            );
        }

        if ($exception instanceof HttpExceptionInterface) {
            return self::error(
                message: $exception->getMessage() ?: 'An error occurred',
                statusCode: $exception->getStatusCode(),
            );
        }

        return self::serverError(
            message: $debug ? $exception->getMessage() : 'Internal server error',
        );
    }

    // -----------------------------------------------------------------------
    // Fluent mutators
    // -----------------------------------------------------------------------

    /**
     * Return a new instance with the given meta entries merged on top of any
     * existing meta (e.g. paginator meta), without touching success/data/errors.
     *
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        $existingMeta = $this->meta ?? $this->resolveMeta() ?? [];

        return new self(
            success: $this->success,
            statusCode: $this->statusCode,
            message: $this->message,
            data: $this->data,
            errors: $this->errors,
            meta: [...$existingMeta, ...$meta],
        );
    }

    // -----------------------------------------------------------------------
    // Response building
    // -----------------------------------------------------------------------

    public function toResponse($request): JsonResponse
    {
        // A 204 response must not carry a body (RFC 9110 §15.3.5).
        if ($this->statusCode === Response::HTTP_NO_CONTENT) {
            return new JsonResponse(status: $this->statusCode);
        }

        return new JsonResponse(
            data: $this->buildPayload(),
            status: $this->statusCode,
        );
    }

    public function toJsonResponse(): JsonResponse
    {
        return $this->toResponse(app('request'));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(): array
    {
        $payload = [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->resolveData(),
        ];

        if ($this->errors !== null) {
            $payload['errors'] = $this->errors;
        }

        $meta = $this->resolveMeta();
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return $payload;
    }

    private function resolveData(): mixed
    {
        return match (true) {
            $this->data instanceof ResourceCollection => $this->data->resolve(),
            $this->data instanceof JsonResource => $this->data->resolve(),
            default => $this->data,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveMeta(): ?array
    {
        if ($this->meta !== null) {
            return $this->meta;
        }

        $source = match (true) {
            $this->data instanceof ResourceCollection => $this->data->resource,
            default => $this->data,
        };

        // LengthAwarePaginator (paginate()) — has total, lastPage
        if ($source instanceof LengthAwarePaginator) {
            return [
                'total' => $source->total(),
                'per_page' => $source->perPage(),
                'current_page' => $source->currentPage(),
                'last_page' => $source->lastPage(),
                'from' => $source->firstItem(),
                'to' => $source->lastItem(),
            ];
        }

        // SimplePaginator (simplePaginate()) — has no total()/lastPage(), only hasMorePages()
        if ($source instanceof Paginator) {
            return [
                'per_page' => $source->perPage(),
                'current_page' => $source->currentPage(),
                'has_more' => $source->hasMorePages(),
                'from' => $source->firstItem(),
                'to' => $source->lastItem(),
            ];
        }

        // CursorPaginator (cursorPaginate())
        if ($source instanceof CursorPaginator) {
            return [
                'per_page' => $source->perPage(),
                'has_more' => $source->hasMorePages(),
                'next_cursor' => $source->nextCursor()?->encode(),
                'prev_cursor' => $source->previousCursor()?->encode(),
            ];
        }

        return null;
    }
}