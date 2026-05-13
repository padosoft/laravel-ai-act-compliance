<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Services;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Models\BiasSnapshot;

class BiasMonitorService
{
    public function __construct(private readonly CohortParityMetric $metric) {}

    public function capture(array $context = []): BiasSnapshot
    {
        $computed = $this->metric->compute($context);

        return BiasSnapshot::query()->create([
            'cohort' => (string) ($computed['cohort'] ?? 'global'),
            'score' => (float) ($computed['score'] ?? 0),
            'delta' => (float) ($computed['delta'] ?? 0),
            'payload' => $computed,
        ]);
    }
}
