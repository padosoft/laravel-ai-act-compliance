<?php

namespace Padosoft\AiActCompliance\RegulatoryFeed\Services;

use InvalidArgumentException;
use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentSeverity;

/**
 * Maps amendment text → AI Act article references.
 *
 * The pattern map is config-driven (`regulatory_feed.impacted_clause_patterns`)
 * so a host adding a new framework (NIS2, GDPR, sector-specific rules)
 * doesn't have to fork the package — drop a new key in config and it
 * surfaces in the audit trail.
 *
 * Severity derivation:
 *   - Any clause hit on Art. 5 / Art. 9          → critical
 *   - Any clause hit on Art. 10 / 14 / 15 / 27   → high
 *   - Any OTHER clause hit (e.g. Art. 50)        → medium
 *   - NO clause hit                              → low
 *
 * Operator can override on triage. An invalid regex in the configured
 * pattern map throws InvalidArgumentException at analyse-time (not
 * silently suppressed) so a typo in a host's config is visible — a
 * suppressed regex would downgrade amendments without warning.
 * Copilot iter-1 review on PR #4.
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
                // Surface invalid regexes loudly: silent suppression
                // would downgrade amendments without operator
                // awareness when a host's pattern map has a typo.
                // The `@` suppresses preg_match's PHP warning so the
                // false return path is the SINGLE source of truth on
                // an invalid pattern (the warning would otherwise
                // make the test flag as risky).
                $match = @preg_match($regex, $haystack);
                if ($match === false) {
                    throw new InvalidArgumentException(sprintf(
                        'Invalid regex "%s" configured for clause "%s" — fix `regulatory_feed.impacted_clause_patterns` in config.',
                        $regex,
                        (string) $clause,
                    ));
                }
                if ($match === 1) {
                    $clauses[] = (string) $clause;
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
