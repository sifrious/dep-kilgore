<?php

declare(strict_types=1);

namespace Sifrious\Kilgore;

use Illuminate\Support\ServiceProvider;

class KilgoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/kilgore.php', 'kilgore');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/kilgore.php' => $this->app->configPath('kilgore.php'),
            ], 'kilgore-config');
        }
    }
}
