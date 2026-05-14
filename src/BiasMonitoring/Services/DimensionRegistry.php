<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Services;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortDimensionResolver;

/**
 * Host-app cohort dimension registry.
 *
 * Built-in dimensions (`language`, `gender`, `age_band`, `country`,
 * `device_class`) are seeded by
 * {@see \Padosoft\AiActCompliance\AiActComplianceServiceProvider}.
 * Host apps add custom dimensions (e.g. `credit_band`) at boot:
 *
 * ```php
 * app(DimensionRegistry::class)->register(new CreditBandResolver());
 * ```
 */
class DimensionRegistry
{
    /** @var array<string, CohortDimensionResolver> */
    private array $resolvers = [];

    public function register(CohortDimensionResolver $resolver): void
    {
        $this->resolvers[$resolver->dimensionKey()] = $resolver;
    }

    public function has(string $dimensionKey): bool
    {
        return isset($this->resolvers[$dimensionKey]);
    }

    public function get(string $dimensionKey): ?CohortDimensionResolver
    {
        return $this->resolvers[$dimensionKey] ?? null;
    }

    /**
     * @return array<string, CohortDimensionResolver>
     */
    public function all(): array
    {
        return $this->resolvers;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->resolvers);
    }
}
