<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Contracts;

use Illuminate\Support\Carbon;

/**
 * Driver-neutral DTO for a single feed entry.
 *
 * `externalId` MUST be stable across polls — drivers that consume a
 * feed without a stable id field should synthesize it as
 * SHA-256(sourceUrl + title) so re-polling doesn't duplicate rows
 * (anchored by the (source_driver, external_id) UNIQUE).
 */
final class RegulatoryFeedEntry
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $sourceUrl,
        public readonly string $title,
        public readonly ?string $summary,
        public readonly ?string $body,
        public readonly ?Carbon $publishedAt,
    ) {}
}
