<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Sifrious\Kilgore\KilgoreServiceProvider;

it('registers the service provider', function (): void {
    expect($this->app->getLoadedProviders())->toHaveKey(KilgoreServiceProvider::class);
});

it('merges the package configuration', function (): void {
    expect(config('kilgore'))->toBeArray();
});

it('publishes the package configuration under its own tag', function (): void {
    expect(ServiceProvider::pathsToPublish(KilgoreServiceProvider::class, 'kilgore-config'))->not->toBeEmpty();
});
