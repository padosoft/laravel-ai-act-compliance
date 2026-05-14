<?php

namespace Padosoft\AiActCompliance\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Padosoft\AiActCompliance\AiActComplianceServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AiActComplianceServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', [
            '--database' => 'testing',
            '--realpath' => true,
            '--path' => __DIR__ . '/../database/migrations',
        ])->run();
    }
}
