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
        // v1.3 — AlertRoute encrypts webhook URLs via Crypt::
        // encryptString, which requires APP_KEY to be set under
        // Orchestra Testbench (no .env is loaded by default).
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.cipher', 'AES-256-CBC');
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
