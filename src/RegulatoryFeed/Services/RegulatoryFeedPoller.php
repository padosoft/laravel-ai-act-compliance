<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\RegulatoryFeed\Contracts\RegulatoryFeedDriver;
use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentStatus;
use Padosoft\AiActCompliance\RegulatoryFeed\Events\RegulatoryAmendmentDetected;
use Padosoft\AiActCompliance\RegulatoryFeed\Exceptions\RegulatoryFeedFetchException;
use Padosoft\AiActCompliance\RegulatoryFeed\Models\RegulatoryAmendment;

/**
 * Orchestrates a single regulatory-feed poll across every configured
 * driver and persists newly-seen amendments.
 *
 * Idempotent: the migration enforces UNIQUE(source_driver, external_id)
 * so re-polling the same feed never duplicates. New rows fire
 * RegulatoryAmendmentDetected; previously-seen entries are skipped
 * silently.
 */
class RegulatoryFeedPoller
{
    public function __construct(
        private readonly Container $container,
        private readonly ImpactedClauseDetector $detector,
    ) {}

    /**
     * @return array{
     *     ingested: int,
     *     skipped: int,
     *     failures: array<string, string>
     * }
     */
    public function poll(): array
    {
        $ingested = 0;
        $skipped = 0;
        $failures = [];

        $drivers = (array) config('ai-act-compliance.regulatory_feed.drivers', []);
        $sources = (array) config('ai-act-compliance.regulatory_feed.sources', []);
        $tenantId = (string) config('ai-act-compliance.tenant_id', '') ?: null;

        foreach ($drivers as $driverKey => $driverFqcn) {
            try {
                $driver = $this->resolveDriver((string) $driverFqcn);
                if ($driver === null) {
                    $failures[(string) $driverKey] = 'driver binding missing or invalid: '.$driverFqcn;

                    continue;
                }
                $sourceConfig = (array) ($sources[$driverKey] ?? []);
                $entries = $driver->fetch($sourceConfig);
                foreach ($entries as $entry) {
                    $existing = RegulatoryAmendment::query()
                        ->where('source_driver', $driverKey)
                        ->where('external_id', $entry->externalId)
                        ->first();
                    if ($existing !== null) {
                        $skipped++;

                        continue;
                    }
                    $analysis = $this->detector->analyse(
                        $entry->title,
                        $entry->summary,
                        $entry->body,
                    );
                    $amendment = RegulatoryAmendment::query()->create([
                        'tenant_id' => $tenantId,
                        'source_driver' => $driverKey,
                        'external_id' => $entry->externalId,
                        'source_url' => $entry->sourceUrl,
                        'title' => $entry->title,
                        'summary' => $entry->summary,
                        'body' => $entry->body,
                        'impacted_clauses_json' => $analysis['clauses'],
                        'status' => RegulatoryAmendmentStatus::Pending->value,
                        'severity' => $analysis['severity']->value,
                        'published_at' => $entry->publishedAt,
                        'ingested_at' => Carbon::now(),
                    ]);
                    event(new RegulatoryAmendmentDetected(
                        amendment: $amendment,
                        isNew: true,
                    ));
                    $ingested++;
                }
            } catch (RegulatoryFeedFetchException $exception) {
                $failures[(string) $driverKey] = $exception->getMessage();
            }
        }

        return [
            'ingested' => $ingested,
            'skipped' => $skipped,
            'failures' => $failures,
        ];
    }

    private function resolveDriver(string $fqcn): ?RegulatoryFeedDriver
    {
        if (! class_exists($fqcn) || ! is_subclass_of($fqcn, RegulatoryFeedDriver::class)) {
            return null;
        }
        $instance = $this->container->make($fqcn);

        return $instance instanceof RegulatoryFeedDriver ? $instance : null;
    }
}
