<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Drivers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\RegulatoryFeed\Contracts\RegulatoryFeedDriver;
use Padosoft\AiActCompliance\RegulatoryFeed\Contracts\RegulatoryFeedEntry;
use Padosoft\AiActCompliance\RegulatoryFeed\Exceptions\RegulatoryFeedFetchException;
use SimpleXMLElement;
use Throwable;

/**
 * RSS 2.0 + Atom 1.0 parser for EU AI Act amendment feeds.
 *
 * Uses libxml in safe mode (no external entity loading) so a hostile
 * feed cannot exfiltrate local files via XXE. Single HTTP call,
 * configurable timeout, no SDK dependency — same posture as the
 * AskMyDocs `app/Ai/Providers/*` services.
 */
class RssRegulatoryFeedDriver implements RegulatoryFeedDriver
{
    public function fetch(array $sourceConfig): array
    {
        $url = (string) ($sourceConfig['feed_url'] ?? '');
        if ($url === '') {
            throw new RegulatoryFeedFetchException('regulatory_feed source missing feed_url');
        }
        $timeout = (int) ($sourceConfig['request_timeout_seconds'] ?? 15);
        // Clamp the cap: 0 must return zero entries (predictable cap)
        // and a negative value is treated as 0 — never "no cap" by
        // accident. Copilot iter-1 review on PR #4.
        $max = max(0, (int) ($sourceConfig['max_entries_per_poll'] ?? 50));

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (Throwable $exception) {
            throw new RegulatoryFeedFetchException(
                'regulatory feed network error: '.$exception->getMessage(),
                previous: $exception,
            );
        }
        if (! $response->successful()) {
            throw new RegulatoryFeedFetchException(
                'regulatory feed returned HTTP '.$response->status(),
            );
        }

        return $this->parse($response->body(), $max);
    }

    /**
     * @return array<int, RegulatoryFeedEntry>
     */
    private function parse(string $body, int $maxEntries): array
    {
        // XXE defense:
        //   - LIBXML_NONET blocks ALL network access (no external
        //     entity resolution over HTTP / FTP / file).
        //   - We do NOT pass LIBXML_NOENT — that flag EXPANDS
        //     entities (XXE attack surface) rather than disabling
        //     them. Copilot iter-1 review on PR #4 corrected a
        //     misleading comment that suggested otherwise.
        //   - libxml_disable_entity_loader() is deprecated since
        //     PHP 8.0; LIBXML_NONET is the supported path.
        // The previous error handler is restored even when parsing
        // throws (try/finally).
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = @simplexml_load_string(
                $body,
                SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false) {
            throw new RegulatoryFeedFetchException('regulatory feed body is not valid XML');
        }

        // Detect RSS vs Atom by root element name. Both shapes are
        // common across EU legal-feed sources.
        $root = $xml->getName();
        if ($root === 'rss') {
            return $this->parseRss($xml, $maxEntries);
        }
        if ($root === 'feed') {
            return $this->parseAtom($xml, $maxEntries);
        }

        throw new RegulatoryFeedFetchException(
            'regulatory feed root <'.$root.'> is neither RSS nor Atom',
        );
    }

    /**
     * @return array<int, RegulatoryFeedEntry>
     */
    private function parseRss(SimpleXMLElement $xml, int $maxEntries): array
    {
        if ($maxEntries === 0) {
            return [];
        }
        $items = $xml->channel->item ?? [];
        $entries = [];
        foreach ($items as $item) {
            $title = trim((string) ($item->title ?? ''));
            $link = trim((string) ($item->link ?? ''));
            $description = trim((string) ($item->description ?? ''));
            $pubDate = (string) ($item->pubDate ?? '');
            $guid = trim((string) ($item->guid ?? ''));

            if ($title === '' || $link === '') {
                continue;
            }

            $entries[] = new RegulatoryFeedEntry(
                externalId: $guid !== '' ? $guid : hash('sha256', $link.'|'.$title),
                sourceUrl: $link,
                title: $title,
                summary: $description !== '' ? $description : null,
                body: null,
                publishedAt: $this->parseDate($pubDate),
            );
            if (count($entries) >= $maxEntries) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @return array<int, RegulatoryFeedEntry>
     */
    private function parseAtom(SimpleXMLElement $xml, int $maxEntries): array
    {
        if ($maxEntries === 0) {
            return [];
        }
        $items = $xml->entry ?? [];
        $entries = [];
        foreach ($items as $item) {
            $title = trim((string) ($item->title ?? ''));
            $link = trim($this->preferredAtomLink($item));
            $id = trim((string) ($item->id ?? ''));
            $summary = trim((string) ($item->summary ?? ''));
            $content = trim((string) ($item->content ?? ''));
            $updated = (string) ($item->updated ?? $item->published ?? '');

            if ($title === '' || $link === '') {
                continue;
            }

            $entries[] = new RegulatoryFeedEntry(
                externalId: $id !== '' ? $id : hash('sha256', $link.'|'.$title),
                sourceUrl: $link,
                title: $title,
                summary: $summary !== '' ? $summary : null,
                body: $content !== '' ? $content : null,
                publishedAt: $this->parseDate($updated),
            );
            if (count($entries) >= $maxEntries) {
                break;
            }
        }

        return $entries;
    }

    /**
     * Atom entries can carry multiple <link> elements (e.g. rel=self
     * for the feed API URL + rel=alternate for the human-readable
     * page). The amendment URL we want to persist is the alternate
     * link; rel="self" would persist the feed's own API endpoint
     * which is operationally useless. Copilot iter-1 review on PR #4.
     */
    private function preferredAtomLink(SimpleXMLElement $item): string
    {
        $links = $item->link ?? [];
        $fallback = '';
        foreach ($links as $link) {
            $rel = (string) ($link['rel'] ?? '');
            $href = (string) ($link['href'] ?? '');
            if ($href === '') {
                continue;
            }
            if ($rel === 'alternate' || $rel === '') {
                return $href;
            }
            if ($fallback === '') {
                $fallback = $href;
            }
        }

        return $fallback;
    }

    private function parseDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            // A single malformed pubDate must not poison the whole
            // poll. The amendment still gets a row; published_at is
            // simply null.
            return null;
        }
    }
}
