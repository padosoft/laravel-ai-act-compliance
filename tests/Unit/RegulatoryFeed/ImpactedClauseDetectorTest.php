<?php

namespace Padosoft\AiActCompliance\Tests\Unit\RegulatoryFeed;

use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentSeverity;
use Padosoft\AiActCompliance\RegulatoryFeed\Services\ImpactedClauseDetector;
use PHPUnit\Framework\TestCase;

class ImpactedClauseDetectorTest extends TestCase
{
    private function detector(): ImpactedClauseDetector
    {
        return new ImpactedClauseDetector([
            'AI Act Art. 5' => ['/\bArt(icle)?\.?\s*5\b/i', '/\bprohibited\s+AI\s+practices?\b/i'],
            'AI Act Art. 9' => ['/\bArt(icle)?\.?\s*9\b/i', '/\brisk\s+management\s+system\b/i'],
            'AI Act Art. 10' => ['/\bArt(icle)?\.?\s*10\b/i', '/\bdata\s+governance\b/i'],
            'AI Act Art. 27' => ['/\bArt(icle)?\.?\s*27\b/i', '/\bFRIA\b/'],
            'AI Act Art. 50' => ['/\bArt(icle)?\.?\s*50\b/i', '/\btransparency\s+obligations?\b/i'],
        ]);
    }

    public function test_no_match_yields_low_severity(): void
    {
        $result = $this->detector()->analyse(
            title: 'Quarterly DG-RTD newsletter',
            summary: 'Research funding updates.',
            body: null,
        );

        self::assertSame([], $result['clauses']);
        self::assertSame(RegulatoryAmendmentSeverity::Low, $result['severity']);
    }

    public function test_art_5_match_is_critical(): void
    {
        $result = $this->detector()->analyse(
            title: 'Amendment clarifying prohibited AI practices',
            summary: 'Revises wording of Art. 5(1)(a).',
            body: null,
        );

        self::assertContains('AI Act Art. 5', $result['clauses']);
        self::assertSame(RegulatoryAmendmentSeverity::Critical, $result['severity']);
    }

    public function test_art_9_match_is_critical(): void
    {
        $result = $this->detector()->analyse(
            title: 'New guidance on risk management system requirements',
            summary: null,
            body: 'High-risk providers must implement a continuous risk management system.',
        );

        self::assertContains('AI Act Art. 9', $result['clauses']);
        self::assertSame(RegulatoryAmendmentSeverity::Critical, $result['severity']);
    }

    public function test_art_27_match_alone_is_high(): void
    {
        $result = $this->detector()->analyse(
            title: 'FRIA template updated',
            summary: 'Fundamental rights impact assessment template revised.',
            body: null,
        );

        self::assertContains('AI Act Art. 27', $result['clauses']);
        self::assertSame(RegulatoryAmendmentSeverity::High, $result['severity']);
    }

    public function test_art_50_match_alone_is_medium(): void
    {
        $result = $this->detector()->analyse(
            title: 'Update to Art. 50 transparency obligations',
            summary: null,
            body: null,
        );

        self::assertContains('AI Act Art. 50', $result['clauses']);
        self::assertSame(RegulatoryAmendmentSeverity::Medium, $result['severity']);
    }

    public function test_art_5_overrides_lower_articles_in_same_text(): void
    {
        // Mixed-clause text — Art. 5 + Art. 50 — must NOT downgrade to
        // Art. 50's medium. The detector picks the highest severity
        // present.
        $result = $this->detector()->analyse(
            title: 'Art. 5 and Art. 50 joint clarification',
            summary: null,
            body: null,
        );

        self::assertContains('AI Act Art. 5', $result['clauses']);
        self::assertContains('AI Act Art. 50', $result['clauses']);
        self::assertSame(RegulatoryAmendmentSeverity::Critical, $result['severity']);
    }

    public function test_empty_text_returns_low_with_no_clauses(): void
    {
        $result = $this->detector()->analyse(title: '', summary: null, body: null);

        self::assertSame([], $result['clauses']);
        self::assertSame(RegulatoryAmendmentSeverity::Low, $result['severity']);
    }

    public function test_unique_clauses_no_duplicates(): void
    {
        // Title AND body both mention Art. 10 — the clause must appear
        // only once in the output.
        $result = $this->detector()->analyse(
            title: 'Art. 10 data governance update',
            summary: 'Art. 10 changes ahead',
            body: 'Data governance obligations under Art. 10 are updated.',
        );

        $art10Count = count(array_filter(
            $result['clauses'],
            static fn (string $c) => $c === 'AI Act Art. 10',
        ));
        self::assertSame(1, $art10Count);
    }
}
