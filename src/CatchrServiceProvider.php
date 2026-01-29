<?php

namespace CceoDeveloper\Catchr;

use Illuminate\Support\ServiceProvider;

class CatchrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/catchr.php', 'catchr');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/catchr.php' => $this->app->configPath('catchr.php'),
        ], 'catchr-config');
    }
}