<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Exceptions;

use RuntimeException;

/**
 * Thrown at boot when a registered FQCN does not implement
 * {@see \Padosoft\AiActCompliance\BiasMonitoring\Contracts\NamedCohortMetric}.
 *
 * Enforces R23 (pluggable registry FQCN validation) so misconfigured
 * hosts fail loudly at boot instead of silently picking the wrong
 * handler at request time.
 */
class InvalidMetricBindingException extends RuntimeException
{
    public static function notImplementingContract(string $name, string $fqcn): self
    {
        return new self(sprintf(
            'Bias metric "%s" is bound to %s which does not implement %s. '
            .'Every registered metric MUST implement NamedCohortMetric.',
            $name,
            $fqcn,
            \Padosoft\AiActCompliance\BiasMonitoring\Contracts\NamedCohortMetric::class,
        ));
    }

    public static function duplicateName(string $name): self
    {
        return new self(sprintf(
            'Bias metric "%s" is already registered. Names must be '
            .'unique; remove the duplicate from '
            .'config(ai-act-compliance.bias.metrics) or pick a new name.',
            $name,
        ));
    }
}
