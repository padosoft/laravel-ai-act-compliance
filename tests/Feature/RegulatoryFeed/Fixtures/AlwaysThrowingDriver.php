<?php

namespace Padosoft\AiActCompliance\Tests\Feature\RegulatoryFeed\Fixtures;

use Padosoft\AiActCompliance\RegulatoryFeed\Contracts\RegulatoryFeedDriver;

class AlwaysThrowingDriver implements RegulatoryFeedDriver
{
    public function fetch(array $sourceConfig): array
    {
        throw new \RuntimeException('custom driver boom');
    }
}
