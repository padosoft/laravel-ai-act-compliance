<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\RegulatoryFeed\Contracts\RegulatoryFeedDriver;
use Padosoft\AiActCompliance\RegulatoryFeed\Contracts\RegulatoryFeedEntry;
use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentStatus;
use Padosoft\AiActCompliance\RegulatoryFeed\Events\RegulatoryAmendmentDetected;
use Padosoft\AiActCompliance\RegulatoryFeed\Models\RegulatoryAmendment;
use Throwable;

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
            // Catch Throwable, not just RegulatoryFeedFetchException —
            // a misbehaving custom driver, a TLS error, or a downstream
            // exception from an event listener must NOT abort the
            // entire poll. Copilot iter-1 review on PR #4.
            try {
                $driver = $this->resolveDriver((string) $driverFqcn);
                if ($driver === null) {
                    $failures[(string) $driverKey] = 'driver binding missing or invalid: '.$driverFqcn;

                    continue;
                }
                $sourceConfig = (array) ($sources[$driverKey] ?? []);
                $entries = $driver->fetch($sourceConfig);
                foreach ($entries as $entry) {
                    [$created, $amendment] = $this->upsertEntry($driverKey, $entry, $tenantId);
                    if (! $created) {
                        $skipped++;

                        continue;
                    }
                    event(new RegulatoryAmendmentDetected(
                        amendment: $amendment,
                        isNew: true,
                    ));
                    $ingested++;
                }
            } catch (Throwable $exception) {
                $failures[(string) $driverKey] = $exception->getMessage();
            }
        }

        return [
            'ingested' => $ingested,
            'skipped' => $skipped,
            'failures' => $failures,
        ];
    }

    /**
     * Upsert ONE entry. Idempotency is enforced by the composite
     * UNIQUE (tenant_id, source_driver, external_id); a concurrent
     * poll that wins the race causes our INSERT to violate the
     * UNIQUE — we catch the QueryException and treat it as
     * "already existed" (skipped). Bounded columns are truncated to
     * the migration limits so upstream values exceeding 191 / 500 /
     * 1024 chars never abort the poll. Copilot iter-1 review PR #4.
     *
     * @return array{0: bool, 1: RegulatoryAmendment} [created, model]
     */
    private function upsertEntry(string $driverKey, RegulatoryFeedEntry $entry, ?string $tenantId): array
    {
        $externalId = self::truncate($entry->externalId, 191);
        $sourceUrl = self::truncate($entry->sourceUrl, 1024);
        $title = self::truncate($entry->title, 500);

        $existing = RegulatoryAmendment::query()
            ->where('tenant_id', $tenantId)
            ->where('source_driver', $driverKey)
            ->where('external_id', $externalId)
            ->first();
        if ($existing !== null) {
            return [false, $existing];
        }

        $analysis = $this->detector->analyse($title, $entry->summary, $entry->body);

        try {
            $amendment = RegulatoryAmendment::query()->create([
                'tenant_id' => $tenantId,
                'source_driver' => $driverKey,
                'external_id' => $externalId,
                'source_url' => $sourceUrl,
                'title' => $title,
                'summary' => $entry->summary,
                'body' => $entry->body,
                'impacted_clauses_json' => $analysis['clauses'],
                'status' => RegulatoryAmendmentStatus::Pending->value,
                'severity' => $analysis['severity']->value,
                'published_at' => $entry->publishedAt,
                'ingested_at' => Carbon::now(),
            ]);

            return [true, $amendment];
        } catch (QueryException $exception) {
            // Lost a race against another concurrent poll — the
            // UNIQUE constraint just fired. Fetch the winning row and
            // count this attempt as skipped, not failed.
            if (! self::isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
            $winner = RegulatoryAmendment::query()
                ->where('tenant_id', $tenantId)
                ->where('source_driver', $driverKey)
                ->where('external_id', $externalId)
                ->firstOrFail();

            return [false, $winner];
        }
    }

    private static function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit);
    }

    private static function isUniqueConstraintViolation(QueryException $exception): bool
    {
        // SQLSTATE 23000 = Integrity constraint violation; covers
        // MySQL ER_DUP_ENTRY, Postgres unique_violation, and SQLite
        // SQLITE_CONSTRAINT_UNIQUE.
        return $exception->getCode() === '23000'
            || str_contains((string) $exception->getMessage(), 'UNIQUE')
            || str_contains((string) $exception->getMessage(), 'Duplicate entry');
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
