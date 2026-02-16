<?php

namespace CceoDeveloper\Catchr;

use CceoDeveloper\Catchr\Console\Commands\CatchrDoctorCommand;
use CceoDeveloper\Catchr\Console\Commands\CatchrTestCommand;
use CceoDeveloper\Catchr\Support\WrappedExceptionHandler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class CatchrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $packageBase = dirname(__DIR__);

        $this->mergeConfigFrom($packageBase . '/config/catchr.php', 'catchr');

        $this->app->extend(ExceptionHandler::class, function ($handler) {
            return $handler instanceof WrappedExceptionHandler
                ? $handler
                : new WrappedExceptionHandler($handler);
        });
    }

    public function boot(): void
    {
        $packageBase = dirname(__DIR__);

        $this->publishes([
            $packageBase . '/config/catchr.php' => $this->app->configPath('catchr.php'),
        ], 'catchr-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CatchrTestCommand::class,
                CatchrDoctorCommand::class
            ]);
        }
    }
}