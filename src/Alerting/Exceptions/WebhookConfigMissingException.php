<?php

namespace Padosoft\AiActCompliance\Alerting\Exceptions;

use RuntimeException;

class WebhookConfigMissingException extends RuntimeException
{
    public static function forChannel(string $channel, ?string $tenantId): self
    {
        return new self(sprintf(
            'Alert channel "%s" enabled for tenant "%s" but no webhook URL is configured. '
            .'Either disable the channel or supply a webhook via the alert_routes row.',
            $channel,
            $tenantId ?? 'global',
        ));
    }
}
