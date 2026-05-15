<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Contracts;

use LogicException;

/**
 * Sentinel placeholder used by {@see \Padosoft\AiActCompliance\AiActComplianceServiceProvider::register()}
 * to bind {@see CohortParityMetric} BEFORE the registry can be
 * consulted (the registry is only seeded in `boot()` after every
 * provider's `register()` has run).
 *
 * The SP's `boot()` rebinds the container to the configured default
 * metric IFF the current binding still resolves to an instance of
 * this sentinel — that test is what prevents the rebind from silently
 * overwriting a host application's own binding registered in its
 * `AppServiceProvider::register()` (which runs AFTER this provider's
 * `register()` but BEFORE its `boot()` per Laravel's two-phase
 * provider lifecycle).
 */
final class UnboundPlaceholderMetric implements CohortParityMetric
{
    public function compute(array $context = []): array
    {
        throw new LogicException(
            'Bind an implementation of '.CohortParityMetric::class
            .' before capturing bias snapshots.',
        );
    }
}
