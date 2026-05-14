<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Services;

use Illuminate\Contracts\Container\Container;
use Padosoft\AiActCompliance\BiasMonitoring\Contracts\NamedCohortMetric;
use Padosoft\AiActCompliance\BiasMonitoring\Exceptions\InvalidMetricBindingException;
use Padosoft\AiActCompliance\BiasMonitoring\Exceptions\UnknownMetricException;

/**
 * Strategy-pattern registry for {@see NamedCohortMetric} implementations.
 *
 * Boot-time invariants (R23):
 *  - Every registered FQCN MUST implement NamedCohortMetric. Violations
 *    throw {@see InvalidMetricBindingException::notImplementingContract()}.
 *  - Names MUST be unique. Duplicate registrations throw
 *    {@see InvalidMetricBindingException::duplicateName()}.
 *
 * Resolution is cached per-name so repeated calls during a single
 * snapshot run share one instance.
 */
class MetricRegistry
{
    /** @var array<string, string> name → FQCN map */
    private array $bindings = [];

    /** @var array<string, NamedCohortMetric> name → instance cache */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    public function register(string $name, string $fqcn): void
    {
        if ($name === '') {
            throw new InvalidMetricBindingException(
                'Bias metric name cannot be empty.',
            );
        }

        if (isset($this->bindings[$name])) {
            throw InvalidMetricBindingException::duplicateName($name);
        }

        if (! class_exists($fqcn) || ! is_subclass_of($fqcn, NamedCohortMetric::class)) {
            throw InvalidMetricBindingException::notImplementingContract($name, $fqcn);
        }

        $this->bindings[$name] = $fqcn;
    }

    public function has(string $name): bool
    {
        return isset($this->bindings[$name]);
    }

    public function resolve(string $name): NamedCohortMetric
    {
        if (! isset($this->bindings[$name])) {
            throw UnknownMetricException::forName($name);
        }

        if (! isset($this->instances[$name])) {
            $instance = $this->container->make($this->bindings[$name]);
            assert($instance instanceof NamedCohortMetric);
            $this->instances[$name] = $instance;
        }

        return $this->instances[$name];
    }

    /**
     * @return array<string, string> name → FQCN
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /**
     * @return array<int, string> registered names
     */
    public function names(): array
    {
        return array_keys($this->bindings);
    }
}
