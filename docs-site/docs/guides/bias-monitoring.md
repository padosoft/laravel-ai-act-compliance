---
title: Bias Monitoring
description: Capture cohort metrics and detect drift.
---

# Bias Monitoring

Bias monitoring snapshots cohort metrics and raises events when drift crosses configured thresholds.

## Metric contract

```php
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric;

class RefusalRateMetric implements CohortParityMetric
{
    public function compute(array $context = []): array
    {
        return [
            'cohort' => $context['cohort'],
            'score' => 0.96,
            'delta' => 0.02,
            'flagged' => false,
        ];
    }
}
```

## Register metrics

```php
app('ai-act.bias')->register('refusal_rate', RefusalRateMetric::class);
```

::: tabs
### Demographic parity

Compare favorable outcome rates across cohorts.

### Equalized odds

Compare error rates across cohorts when ground truth is available.

### Calibration

Compare prediction confidence to observed outcomes.
:::

$$
\Delta = |score_{cohort} - score_{baseline}|
$$
