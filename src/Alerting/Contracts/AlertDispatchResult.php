<?php

namespace Padosoft\AiActCompliance\Alerting\Contracts;

/**
 * Result returned by every {@see AlertChannel::send()}.
 *
 * `transient` distinguishes recoverable failures (HTTP 429, 5xx,
 * network error → retry) from permanent ones (4xx other than 429,
 * webhook URL malformed → trip channel, don't retry).
 */
final class AlertDispatchResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly bool $transient = false,
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function success(?int $httpStatus = 200): self
    {
        return new self(ok: true, transient: false, httpStatus: $httpStatus);
    }

    public static function transientFailure(?int $httpStatus, ?string $message = null): self
    {
        return new self(ok: false, transient: true, httpStatus: $httpStatus, errorMessage: $message);
    }

    public static function permanentFailure(?int $httpStatus, ?string $message = null): self
    {
        return new self(ok: false, transient: false, httpStatus: $httpStatus, errorMessage: $message);
    }
}
