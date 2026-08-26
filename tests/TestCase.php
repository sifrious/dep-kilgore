<?php

declare(strict_types=1);

namespace Sifrious\Kilgore\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sifrious\Kilgore\KilgoreServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [KilgoreServiceProvider::class];
    }
}
