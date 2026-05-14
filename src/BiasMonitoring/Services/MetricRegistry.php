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
 *
 * ## Precedence between this provider's seed loop and host bindings
 *
 * The package's {@see \Padosoft\AiActCompliance\AiActComplianceServiceProvider::boot()}
 * seeds the registry from `config('ai-act-compliance.bias.metrics')`
 * using a `has()`-guarded loop (skips names that are already present).
 * Host applications register custom metrics via either:
 *
 *   1. Adding their name to `config(...)` BEFORE the package's boot()
 *      runs — typically by republishing the config and shipping the
 *      host's entries in `bias.metrics`. In this case the seed loop
 *      registers the host metric and the package never tries to
 *      duplicate it.
 *
 *   2. Calling `app(MetricRegistry::class)->register(name, fqcn)` from
 *      the host's own `boot()` — typically AFTER the package's boot()
 *      (Laravel boots providers in registration order; package
 *      providers usually boot first). In this case the package has
 *      already seeded its three reference metrics; the host's
 *      register() succeeds for ANY name the package didn't claim, and
 *      throws {@see InvalidMetricBindingException::duplicateName()} for
 *      conflicts (e.g. a host trying to rebind `demographic_parity` —
 *      a loud failure that surfaces the misconfiguration at boot).
 *
 * The loud-fail-on-conflict + has()-guard-on-package-seed combination
 * means: HOST CONFIG WINS when it precedes the boot loop (path #1),
 * and the PACKAGE WINS otherwise (path #2). Hosts that want to OVERRIDE
 * a reference metric MUST go through path #1 (config) — there is no
 * `register(..., $override: true)` flag by design, because allowing a
 * silent overwrite would defeat the loud-fail R23 stance the rest of
 * the registry relies on.
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
