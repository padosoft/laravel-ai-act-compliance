<?php

namespace Padosoft\AiActCompliance\Alerting\Channels;

use Illuminate\Support\Facades\Mail;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertChannel;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertDispatchResult;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Throwable;

class EmailFallbackChannel implements AlertChannel
{
    /**
     * `$endpoint` here is the recipient email address (the v1.3
     * alert_routes table reuses the same column for all channels —
     * SMTP transport itself is configured at the Laravel-Mail level).
     */
    public function send(AlertPayload $payload, string $endpoint): AlertDispatchResult
    {
        try {
            Mail::raw($this->body($payload), function ($message) use ($endpoint, $payload) {
                $message->to($endpoint);
                $message->subject('['.strtoupper($payload->severity).'] '.$payload->title);
            });
        } catch (Throwable $exception) {
            // Classify the SMTP error. The Symfony Mailer transport
            // exception hierarchy distinguishes RECOVERABLE (queue
            // full, server busy, 4xx-class) from PERMANENT (5xx-class,
            // invalid recipient, auth rejection). We pattern-match on
            // class name to avoid hard-binding to Symfony internals —
            // the package supports any Laravel-bound mailer. Copilot
            // review on PR #3 caught the previous always-transient
            // classification as a retry-loop hazard.
            return $this->classify($exception);
        }

        return AlertDispatchResult::success(null);
    }

    private function classify(Throwable $exception): AlertDispatchResult
    {
        $class = $exception::class;
        $message = $exception->getMessage();
        $permanentMarkers = [
            'Symfony\\Component\\Mailer\\Exception\\InvalidArgumentException',
            'Symfony\\Component\\Mime\\Exception\\RfcComplianceException',
        ];
        foreach ($permanentMarkers as $marker) {
            if (is_a($class, $marker, true)) {
                return AlertDispatchResult::permanentFailure(
                    httpStatus: null,
                    message: 'SMTP permanent error: '.$message,
                );
            }
        }

        // Inspect the message for typical 5xx / permanent SMTP codes
        // so hosts whose transport wraps the response in a generic
        // RuntimeException still classify correctly.
        if (preg_match('/\b(5\d\d)\b/', $message, $matches) === 1) {
            return AlertDispatchResult::permanentFailure(
                httpStatus: (int) $matches[1],
                message: 'SMTP permanent error: '.$message,
            );
        }

        return AlertDispatchResult::transientFailure(
            httpStatus: null,
            message: 'SMTP transient error: '.$message,
        );
    }

    private function body(AlertPayload $payload): string
    {
        $lines = [
            $payload->body,
            '',
            'Severity: '.$payload->severity,
        ];
        if ($payload->metricName !== null) {
            $lines[] = 'Metric: '.$payload->metricName;
        }
        if ($payload->cohort !== null) {
            $lines[] = 'Cohort: '.$payload->cohort;
        }
        if ($payload->tenantId !== null) {
            $lines[] = 'Tenant: '.$payload->tenantId;
        }
        if ($payload->articles !== []) {
            $lines[] = 'Articles: '.implode(', ', $payload->articles);
        }
        if ($payload->evidenceUrl !== null) {
            $lines[] = '';
            $lines[] = 'Evidence dashboard: '.$payload->evidenceUrl;
        }

        return implode("\n", $lines);
    }
}
