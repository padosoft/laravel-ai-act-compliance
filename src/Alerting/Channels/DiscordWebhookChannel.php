<?php

namespace Padosoft\AiActCompliance\Alerting\Channels;

use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertChannel;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertDispatchResult;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Throwable;

class DiscordWebhookChannel implements AlertChannel
{
    public function send(AlertPayload $payload, string $endpoint): AlertDispatchResult
    {
        try {
            $response = Http::timeout(5)->post($endpoint, $this->shape($payload));
        } catch (Throwable $exception) {
            return AlertDispatchResult::transientFailure(
                httpStatus: null,
                message: 'Discord webhook network error: '.$exception->getMessage(),
            );
        }

        $status = $response->status();
        // Discord returns 204 No Content on success.
        if ($response->successful() || $status === 204) {
            return AlertDispatchResult::success($status);
        }

        if ($status === 429 || ($status >= 500 && $status < 600)) {
            return AlertDispatchResult::transientFailure(
                httpStatus: $status,
                message: 'Discord webhook returned '.$status,
            );
        }

        return AlertDispatchResult::permanentFailure(
            httpStatus: $status,
            message: 'Discord webhook returned '.$status,
        );
    }

    private function shape(AlertPayload $payload): array
    {
        $colour = match (strtolower($payload->severity)) {
            'critical' => 0xE02424,
            'high' => 0xF59E0B,
            'medium' => 0xFACC15,
            default => 0x6B7280,
        };

        $fields = [];
        if ($payload->metricName !== null) {
            $fields[] = ['name' => 'Metric', 'value' => $payload->metricName, 'inline' => true];
        }
        if ($payload->cohort !== null) {
            $fields[] = ['name' => 'Cohort', 'value' => $payload->cohort, 'inline' => true];
        }
        if ($payload->tenantId !== null) {
            $fields[] = ['name' => 'Tenant', 'value' => $payload->tenantId, 'inline' => true];
        }

        return [
            'username' => 'AI Act Compliance',
            'content' => '**'.strtoupper($payload->severity).'** — '.$payload->title,
            'embeds' => [
                [
                    'title' => $payload->title,
                    'description' => $payload->body,
                    'color' => $colour,
                    'url' => $payload->evidenceUrl,
                    'fields' => $fields,
                ],
            ],
        ];
    }
}
