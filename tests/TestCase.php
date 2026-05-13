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
}
