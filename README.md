# Laravel API Response Builder

[![Packagist Version](https://img.shields.io/packagist/v/weckhawk/pretty-response)](https://packagist.org/packages/weckhawk/pretty-response)
[![License](https://img.shields.io/packagist/l/weckhawk/pretty-response)](LICENSE)

A fluent, expressive, and consistent API response wrapper for Laravel applications. It helps you standardize JSON responses, handle paginators automatically, and keep your controllers clean.

## Features

- **Standardized Structure**: Ensures every JSON response follows a predictable format (`success`, `message`, `data`, `errors`, `meta`).
- **Smart Pagination Handling**: Automatically extracts metadata from Eloquent `LengthAwarePaginator`, `SimplePaginator`, and `CursorPaginator`.
- **Resource Resolution**: Seamlessly unwraps Laravel's `JsonResource` and `ResourceCollection`.
- **Semantic Named Constructors**: Clean static methods like `ApiResponse::success()`, `ApiResponse::validationError()`, etc.
- **Global exception handling**: Automatically converts `ValidationException`, `ModelNotFoundException`, `AuthenticationException`, `AuthorizationException`, and any other `Throwable` into the same JSON envelope for requests that expect JSON — with a safe fallback for unhandled errors.

## Installation

```bash
composer require weckhawk/pretty-response
```

The service provider is auto-discovered. No manual registration is required.

## Basic Usage

### Success Response

```php
use Weckhawk\PrettyResponse\ApiResponse;

return ApiResponse::success($users, 'Users fetched successfully');
```

### Paginated Response

Pass any Laravel paginator directly into the `success` method. The package will automatically append the `meta` object:

```php
$paginatedUsers = User::paginate(15);

return ApiResponse::success($paginatedUsers);
```

### Error Responses

```php
return ApiResponse::notFound('User not found');

return ApiResponse::validationError([
    'email' => ['The email field is required.']
]);
```

### Building a response from a caught exception

Useful when you want to convert an exception to an `ApiResponse` yourself, inside a `try`/`catch`, without relying on the global exception handler:

```php
try {
    $this->process($request);
} catch (\Throwable $e) {
    return ApiResponse::fromException($e, debug: config('app.debug'))->toJsonResponse();
}
```

### Attaching extra meta after the fact

```php
return ApiResponse::success($paginatedUsers)
    ->withMeta(['request_id' => $request->header('X-Request-Id')]);
```

`withMeta()` merges on top of any existing meta — including auto-detected paginator meta — without touching `success`, `data`, or `errors`.

## Configuration

The global exception handler is registered automatically. If you'd rather handle exceptions yourself (e.g. only using `ApiResponse::fromException()` manually), disable it from your own `AppServiceProvider::register()`:

```php
use Weckhawk\PrettyResponse\PrettyResponseServiceProvider;

public function register(): void
{
    PrettyResponseServiceProvider::disableExceptionHandler();
}
```

## Testing

```bash
composer install
composer test
```

Static analysis and code style checks:

```bash
composer stan
composer pint-test
```

## Development & release workflow

- **`dev`** — every push runs the full test matrix, PHPStan, and Pint.
- **`main`** — every pull request runs the same checks before it can be merged.
- **Releasing** — from the *Actions* tab, run the **Release** workflow manually (it only runs against `main`), enter the version (e.g. `1.2.0`). It re-runs the full quality gate against that exact commit and, only if everything passes, creates the `vX.Y.Z` tag and a GitHub Release. Packagist picks up the new tag automatically through its existing GitHub webhook — no extra step needed.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
