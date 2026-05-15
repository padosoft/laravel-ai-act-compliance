<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Services;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\MetricResult;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\NamedCohortMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Exceptions\UnknownMetricException;
use Padosoft\AiActCompliance\BiasMonitoring\Metrics\AbstractCohortMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Models\BiasSnapshot;

// MetricResult is referenced from the persistStructured() signature
// (and reachable from the AbstractCohortMetric branch above), so the
// import stays load-bearing even after the v1.1 array path is the
// only branch that processes a raw compute() return.

class BiasMonitorService
{
    public function __construct(
        private readonly CohortParityMetric $metric,
        private readonly ?MetricRegistry $registry = null,
    ) {}

    /**
     * Capture a snapshot using either the explicit metric injected at
     * construction time (v1.1 contract — preserved) OR a metric resolved
     * via the v1.2 {@see MetricRegistry} when `$context['metric_name']`
     * is provided.
     */
    public function capture(array $context = []): BiasSnapshot
    {
        $metric = $this->resolveMetric($context);

        // v1.2 reference metrics extend AbstractCohortMetric — call the
        // structured `computeResult()` so the snapshot row carries the
        // metric_name + cohort_dimension + article_evidence_json fields.
        if ($metric instanceof AbstractCohortMetric) {
            return $this->persistStructured($metric->computeResult($context), $metric);
        }

        // Bare CohortParityMetric (the v1.1 contract) — `compute()`
        // returns `array` per the interface signature, so a MetricResult
        // return is impossible to reach here under PHP's type checker.
        // Persist via the legacy path; NamedCohortMetric (the v1.2
        // extension) is detected inside persistLegacy() so the
        // metric_name + metric_version columns are populated from the
        // instance instead of falling back to the 'legacy' sentinel.
        return $this->persistLegacy($metric->compute($context), $metric);
    }

    private function resolveMetric(array $context): CohortParityMetric
    {
        $metricName = (string) ($context['metric_name'] ?? '');
        if ($metricName !== '') {
            // Loud-fail when the caller explicitly names a metric that
            // isn't registered — a silent fallback would persist the
            // snapshot under a DIFFERENT metric, contaminating the
            // audit trail (a typo like 'equalised_odds' must not end
            // up persisted as 'demographic_parity'). Mirrors the
            // boot-time R23 stance for FQCN validation. Copilot
            // review on PR #2 caught the silent-fallback hazard.
            if ($this->registry !== null && $this->registry->has($metricName)) {
                return $this->registry->resolve($metricName);
            }
            throw UnknownMetricException::forName($metricName);
        }

        // The constructor-injected metric is the host-bound contract
        // when explicit, or — for fresh hosts that rely purely on
        // `bias.default_metric` + `bias.metrics` config without an
        // explicit binding — the SP-managed default (rebound to the
        // configured default in {@see AiActComplianceServiceProvider::boot()}).
        return $this->metric;
    }

    private function persistStructured(MetricResult $result, CohortParityMetric $metric): BiasSnapshot
    {
        return BiasSnapshot::query()->create([
            'cohort' => $result->worstCohort ?? 'global',
            'score' => $result->disparityScore,
            'delta' => $result->disparityScore,
            'metric_name' => $result->metricName,
            // metric_version is sourced from the metric instance so
            // each metric owns its own version stamp. Reference
            // metrics return '1.0' via AbstractCohortMetric; host-app
            // metrics bump this on every algorithmic change.
            'metric_version' => $metric instanceof NamedCohortMetric ? $metric->version() : '1.0',
            'article_evidence_json' => $result->articleEvidence,
            'disparity_score' => $result->disparityScore,
            'cohort_dimension' => $result->cohortDimension,
            'payload' => $result->toArray(),
        ]);
    }

    private function persistLegacy(array $computed, CohortParityMetric $metric): BiasSnapshot
    {
        // Derive the metric_name from the instance when available.
        // Hard-coding 'demographic_parity' here would misattribute a
        // host-supplied legacy metric in the audit trail and skew the
        // `(tenant_id, metric_name, cohort_dimension)` index.
        // NamedCohortMetric → exposes `name()`; otherwise we mark the
        // row as `legacy` so the audit trail surfaces the unknown
        // provenance cleanly. Copilot review on PR #2 caught this.
        $metricName = $metric instanceof NamedCohortMetric
            ? $metric->name()
            : 'legacy';

        return BiasSnapshot::query()->create([
            'cohort' => (string) ($computed['cohort'] ?? 'global'),
            'score' => (float) ($computed['score'] ?? 0),
            'delta' => (float) ($computed['delta'] ?? 0),
            // SQLite's `ALTER TABLE ... ADD COLUMN ... DEFAULT 'x'`
            // populates the schema default but Laravel's Eloquent
            // INSERT omits unsupplied columns, so the SELECT-back path
            // returns null on SQLite for rows where the column was
            // never explicitly written. Explicitly set the v1.2 column
            // defaults so the audit trail is consistent across drivers.
            'metric_name' => $metricName,
            'metric_version' => $metric instanceof NamedCohortMetric ? $metric->version() : '1.0',
            'payload' => $computed,
        ]);
    }
}
