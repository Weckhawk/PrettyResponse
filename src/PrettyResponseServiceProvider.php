<?php

namespace Weckhawk\PrettyResponse;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Weckhawk\PrettyResponse\Exceptions\PrettyExceptionHandler;

class PrettyResponseServiceProvider extends ServiceProvider
{
    public static bool $registerExceptionHandler = true;

    public static function disableExceptionHandler(): void
    {
        static::$registerExceptionHandler = false;
    }

    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        if (!static::$registerExceptionHandler) {
            return;
        }

        $this->app->afterResolving(ExceptionHandler::class, function ($handler) {
            if (method_exists($handler, 'renderable')) {
                PrettyExceptionHandler::register($handler);
            }
        });

        if ($this->app->resolved(ExceptionHandler::class)) {
            $handler = $this->app->make(ExceptionHandler::class);

            if (method_exists($handler, 'renderable')) {
                PrettyExceptionHandler::register($handler);
            }
        }
    }
}