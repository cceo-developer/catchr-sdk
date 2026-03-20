<?php

namespace CceoDeveloper\Catchr;

use CceoDeveloper\Catchr\Console\Commands\CatchrDoctorCommand;
use CceoDeveloper\Catchr\Console\Commands\CatchrTestCommand;
use CceoDeveloper\Catchr\Support\Exceptions\WrappedExceptionHandler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
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

        $this->loadMigrationsFrom($packageBase . '/database/migrations');

        Queue::before(function (\Illuminate\Queue\Events\JobProcessing $event) {
            App::make(\CceoDeveloper\Catchr\Listeners\TrackJobProcessing::class)->handle($event);
        });

        Queue::after(function (\Illuminate\Queue\Events\JobProcessed $event) {
            App::make(\CceoDeveloper\Catchr\Listeners\TrackJobProcessed::class)->handle($event);
        });

        Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            App::make(\CceoDeveloper\Catchr\Listeners\TrackJobFailed::class)->handle($event);
        });
    }
}