<?php

namespace Padosoft\AiActCompliance\Alerting\Channels;

use Illuminate\Support\Facades\Http;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertChannel;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertDispatchResult;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Throwable;

class SlackWebhookChannel implements AlertChannel
{
    public function send(AlertPayload $payload, string $endpoint): AlertDispatchResult
    {
        try {
            $response = Http::timeout(5)->post($endpoint, $this->shape($payload));
        } catch (Throwable $exception) {
            return AlertDispatchResult::transientFailure(
                httpStatus: null,
                message: 'Slack webhook network error: '.$exception->getMessage(),
            );
        }

        $status = $response->status();
        if ($response->successful()) {
            return AlertDispatchResult::success($status);
        }

        // 429 + 5xx → recoverable; everything else → permanent.
        if ($status === 429 || ($status >= 500 && $status < 600)) {
            return AlertDispatchResult::transientFailure(
                httpStatus: $status,
                message: 'Slack webhook returned '.$status,
            );
        }

        return AlertDispatchResult::permanentFailure(
            httpStatus: $status,
            message: 'Slack webhook returned '.$status,
        );
    }

    private function shape(AlertPayload $payload): array
    {
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => '['.strtoupper($payload->severity).'] '.$payload->title,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $payload->body,
                ],
            ],
        ];

        $context = array_filter([
            $payload->metricName ? '*Metric:* '.$payload->metricName : null,
            $payload->cohort ? '*Cohort:* '.$payload->cohort : null,
            $payload->tenantId ? '*Tenant:* '.$payload->tenantId : null,
        ]);
        if ($context !== []) {
            $blocks[] = [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => implode(' · ', $context)],
            ];
        }
        if ($payload->evidenceUrl !== null) {
            $blocks[] = [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '<'.$payload->evidenceUrl.'|Evidence dashboard>'],
            ];
        }

        return [
            'text' => $payload->title,
            'blocks' => $blocks,
        ];
    }
}
