<?php

namespace Padosoft\AiActCompliance\Alerting\Channels;

use Illuminate\Support\Facades\Mail;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertChannel;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertDispatchResult;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Throwable;

class EmailFallbackChannel implements AlertChannel
{
    public function name(): string
    {
        return 'email';
    }

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
            // SMTP failures are typically transient (server busy /
            // queue full); permanent failure (auth rejection) still
            // returns a transient classification — the operator
            // sees the message in the audit row either way.
            return AlertDispatchResult::transientFailure(
                httpStatus: null,
                message: 'SMTP send error: '.$exception->getMessage(),
            );
        }

        return AlertDispatchResult::success(null);
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
