<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Contracts;

/**
 * Host-app extension point for custom cohort dimensions.
 *
 * Built-in dimensions (`language`, `gender`, `age_band`, `country`,
 * `device_class`) ship as default resolvers. Host apps register
 * additional dimensions (e.g. `credit_band`, `comorbidity_count`)
 * against {@see \Padosoft\AiActCompliance\BiasMonitoring\Services\DimensionRegistry}.
 */
interface CohortDimensionResolver
{
    /**
     * The dimension key this resolver answers for (e.g. 'credit_band').
     */
    public function dimensionKey(): string;

    /**
     * Resolve the cohort label this subject falls into.
     *
     * The $subject is opaque — the host's resolver knows what to do
     * with it (e.g. a User model, an array record, an identifier).
     * Returning null means \"no cohort for this subject\" and the
     * monitor should skip it from the snapshot.
     */
    public function resolveCohortFor(mixed $subject): ?string;
}
