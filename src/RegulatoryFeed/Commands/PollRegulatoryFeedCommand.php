<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Commands;

use Illuminate\Console\Command;
use Padosoft\AiActCompliance\RegulatoryFeed\Services\RegulatoryFeedPoller;

class PollRegulatoryFeedCommand extends Command
{
    protected $signature = 'ai-act:regulatory-poll';

    protected $description = 'Poll configured regulatory feeds and ingest new EU AI Act amendments.';

    public function handle(RegulatoryFeedPoller $poller): int
    {
        if (! config('ai-act-compliance.regulatory_feed.enabled', false)) {
            $this->warn('regulatory_feed.enabled=false — skipping.');

            return self::SUCCESS;
        }

        $result = $poller->poll();
        $this->info(sprintf(
            'Regulatory poll complete: ingested=%d skipped=%d failures=%d',
            $result['ingested'],
            $result['skipped'],
            count($result['failures']),
        ));
        foreach ($result['failures'] as $driver => $error) {
            $this->warn(sprintf('driver=%s: %s', $driver, $error));
        }

        return $result['failures'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
