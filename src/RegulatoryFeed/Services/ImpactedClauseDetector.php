<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Services;

use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentSeverity;

/**
 * Maps amendment text → AI Act article references.
 *
 * The pattern map is config-driven (`regulatory_feed.impacted_clause_patterns`)
 * so a host adding a new framework (NIS2, GDPR, sector-specific rules)
 * doesn't have to fork the package — drop a new key in config and it
 * surfaces in the audit trail.
 *
 * Severity is derived from clause weight: any hit on Art. 5
 * (prohibited practices) or Art. 9 (risk management) maps to
 * `critical`; Art. 10 / Art. 14 / Art. 15 / Art. 27 are `high`;
 * everything else default `low`. Operator can override on triage.
 */
class ImpactedClauseDetector
{
    /**
     * @param  array<string,array<int,string>>  $patterns
     */
    public function __construct(private readonly array $patterns) {}

    /**
     * @return array{
     *     clauses: array<int,string>,
     *     severity: \Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentSeverity
     * }
     */
    public function analyse(string $title, ?string $summary, ?string $body): array
    {
        $haystack = trim($title.' '.($summary ?? '').' '.($body ?? ''));
        if ($haystack === '') {
            return [
                'clauses' => [],
                'severity' => RegulatoryAmendmentSeverity::Low,
            ];
        }

        $clauses = [];
        foreach ($this->patterns as $clause => $regexList) {
            foreach ($regexList as $regex) {
                if (@preg_match($regex, $haystack) === 1) {
                    $clauses[] = $clause;
                    break;
                }
            }
        }
        $clauses = array_values(array_unique($clauses));

        return [
            'clauses' => $clauses,
            'severity' => $this->deriveSeverity($clauses),
        ];
    }

    /**
     * @param  array<int,string>  $clauses
     */
    private function deriveSeverity(array $clauses): RegulatoryAmendmentSeverity
    {
        if ($clauses === []) {
            return RegulatoryAmendmentSeverity::Low;
        }
        // Use whole-clause set membership (NOT str_contains) — a
        // naive `str_contains('Art. 50', 'Art. 5')` is true and would
        // miscategorise Art. 50 hits as critical. The clause set is
        // always a closed list (Art. 5 / Art. 9 / Art. 10 / Art. 14
        // / Art. 15 / Art. 27 / Art. 50) so an exact `in_array` check
        // is both safer and clearer.
        if (
            in_array('AI Act Art. 5', $clauses, true)
            || in_array('AI Act Art. 9', $clauses, true)
        ) {
            return RegulatoryAmendmentSeverity::Critical;
        }
        if (
            in_array('AI Act Art. 10', $clauses, true)
            || in_array('AI Act Art. 14', $clauses, true)
            || in_array('AI Act Art. 15', $clauses, true)
            || in_array('AI Act Art. 27', $clauses, true)
        ) {
            return RegulatoryAmendmentSeverity::High;
        }

        return RegulatoryAmendmentSeverity::Medium;
    }
}
