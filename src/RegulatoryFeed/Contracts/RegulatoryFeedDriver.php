<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Contracts;

interface RegulatoryFeedDriver
{
    /**
     * Fetch + parse the upstream feed. Implementations MUST NOT
     * persist anything — the poller writes to the DB after running
     * the clause detector. A driver that cannot reach the feed
     * SHOULD throw rather than return an empty array, so the poller
     * can mark the run as failed and surface it to the operator.
     *
     * @param  array<string,mixed>  $sourceConfig  the merged
     *         `regulatory_feed.sources.<driver_key>` block.
     * @return array<int, RegulatoryFeedEntry>
     */
    public function fetch(array $sourceConfig): array;
}
