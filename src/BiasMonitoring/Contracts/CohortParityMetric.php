<?php

namespace Padosoft\AiActCompliance\BiasMonitoring\Contracts;

interface CohortParityMetric
{
    public function compute(array $context = []): array;
}
