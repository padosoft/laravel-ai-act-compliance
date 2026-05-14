<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Exceptions;

use RuntimeException;

class UnknownMetricException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self(sprintf(
            'Bias parity metric "%s" is not registered. Add it to '
            .'config(ai-act-compliance.bias.metrics) or call '
            .'MetricRegistry::register() in a service-provider boot.',
            $name,
        ));
    }
}
