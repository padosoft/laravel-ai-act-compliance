<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Services;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\MetricResult;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\NamedCohortMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Metrics\AbstractCohortMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Models\BiasSnapshot;

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
            return $this->persistStructured($metric->computeResult($context));
        }

        $raw = $metric->compute($context);

        // Custom NamedCohortMetric implementations that return a
        // MetricResult directly still flow through the structured path.
        return $raw instanceof MetricResult
            ? $this->persistStructured($raw)
            : $this->persistLegacy($raw);
    }

    private function resolveMetric(array $context): CohortParityMetric
    {
        $metricName = (string) ($context['metric_name'] ?? '');
        if ($metricName !== '' && $this->registry !== null && $this->registry->has($metricName)) {
            return $this->registry->resolve($metricName);
        }

        return $this->metric;
    }

    private function persistStructured(MetricResult $result): BiasSnapshot
    {
        return BiasSnapshot::query()->create([
            'cohort' => $result->worstCohort ?? 'global',
            'score' => $result->disparityScore,
            'delta' => $result->disparityScore,
            'metric_name' => $result->metricName,
            'metric_version' => '1.0',
            'article_evidence_json' => $result->articleEvidence,
            'disparity_score' => $result->disparityScore,
            'cohort_dimension' => $result->cohortDimension,
            'payload' => $result->toArray(),
        ]);
    }

    private function persistLegacy(array $computed): BiasSnapshot
    {
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
            'metric_name' => 'demographic_parity',
            'metric_version' => '1.0',
            'payload' => $computed,
        ]);
    }
}
