<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PromoSeven\AzureMailer\AzureMailerServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AzureMailerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }
}
