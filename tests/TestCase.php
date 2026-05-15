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
        // Orchestra Testbench (no .env is loaded by default). A
        // FIXED test key keeps Crypt::encryptString output
        // deterministic across boots so a fixture re-used between
        // tests (none today, but future fixture DBs will) decrypts
        // consistently. Copilot iter-3 review on PR #3.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
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
