<?php

namespace PromoSeven\UltraMessage\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use PromoSeven\UltraMessage\UltraMessageServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [UltraMessageServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('ultra-message.instance_id', 'instance123');
        $app['config']->set('ultra-message.token', 'test-token');
        $app['config']->set('ultra-message.enabled', true);
    }
}
