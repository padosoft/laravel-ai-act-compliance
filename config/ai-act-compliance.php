<?php

return [
    'enabled' => env('AI_ACT_COMPLIANCE_ENABLED', true),

    'disclosure' => [
        'enabled' => env('AI_ACT_DISCLOSURE_ENABLED', true),
        'header' => 'X-AI-Disclosure',
        'message' => 'This response may include AI-generated content.',
    ],

    'routes' => [
        'enabled' => env('AI_ACT_COMPLIANCE_ROUTES_ENABLED', true),
        'prefix' => env('AI_ACT_COMPLIANCE_ROUTE_PREFIX', 'api/admin/ai-act-compliance'),
        'middleware' => ['api'],
    ],

    'dsar' => [
        'default_sla_days' => (int) env('AI_ACT_DSAR_SLA_DAYS', 30),
    ],

    'consent' => [
        'default_required_features' => [],

        /*
        |----------------------------------------------------------------------
        | Chat-route consent gate
        |----------------------------------------------------------------------
        |
        | When set to a non-empty string (e.g. `'chat'`), hosts that mount the
        | `ai.consent:<feature>` middleware on their AI chat path can use this
        | as the feature key. Null/empty means the gate is not enforced —
        | callers without a granted ConsentRecord still reach the chat.
        */
        'gate_chat_feature' => env('AI_ACT_CONSENT_GATE_CHAT_FEATURE'),
    ],

    'bias' => [
        /*
        |----------------------------------------------------------------------
        | Bias monitoring — pluggable parity metrics (v1.2)
        |----------------------------------------------------------------------
        |
        | `default_metric` is the metric MetricRegistry resolves when a
        | snapshot call carries no `metric_name`. `metrics` maps the
        | registry key to the FQCN; host apps add custom keys here or
        | call MetricRegistry::register() at boot.
        |
        | `disparity_threshold` controls when individual cohorts in a
        | MetricResult are flagged. `min_sample_size` is a hint for
        | downstream consumers; the metrics themselves never reject
        | smaller cohorts (they still compute, just with wider CIs).
        */
        'default_metric' => env('AI_ACT_BIAS_DEFAULT_METRIC', 'demographic_parity'),
        'metrics' => [
            'demographic_parity' => \Padosoft\AiActCompliance\BiasMonitoring\Metrics\DemographicParityMetric::class,
            'equalized_odds' => \Padosoft\AiActCompliance\BiasMonitoring\Metrics\EqualizedOddsMetric::class,
            'calibration' => \Padosoft\AiActCompliance\BiasMonitoring\Metrics\CalibrationMetric::class,
        ],
        'disparity_threshold' => (float) env('AI_ACT_BIAS_DISPARITY_THRESHOLD', 0.05),
        'min_sample_size' => (int) env('AI_ACT_BIAS_MIN_SAMPLE_SIZE', 30),
    ],

    'alerting' => [
        /*
        |----------------------------------------------------------------------
        | Cohort-drift real-time alerting (v1.3)
        |----------------------------------------------------------------------
        |
        | Default OFF — existing tenants see no behaviour change until a
        | webhook / email recipient is configured on the `alert_routes` table.
        |
        | Throttle suppresses repeat alerts inside `per_cohort_minutes`. The
        | circuit breaker trips a channel after `failures_to_trip` consecutive
        | failures and skips it for `cooldown_minutes`.
        |
        | `channels` maps the channel name persisted on `alert_routes.channel`
        | + `alert_dispatches.channel` to the AlertChannel FQCN that delivers
        | the payload. Host apps add custom channels by inserting an entry
        | here (the AlertDispatcher resolves channels through the container,
        | so any binding the host already owns wins).
        */
        'enabled' => env('AI_ACT_ALERTING_ENABLED', false),

        'throttle' => [
            'per_cohort_minutes' => (int) env('AI_ACT_ALERT_THROTTLE_MINUTES', 60),
        ],

        'circuit_breaker' => [
            'failures_to_trip' => (int) env('AI_ACT_ALERT_CB_FAILURES', 5),
            'cooldown_minutes' => (int) env('AI_ACT_ALERT_CB_COOLDOWN', 30),
        ],

        'channels' => [
            'slack' => \Padosoft\AiActCompliance\Alerting\Channels\SlackWebhookChannel::class,
            'discord' => \Padosoft\AiActCompliance\Alerting\Channels\DiscordWebhookChannel::class,
            'email' => \Padosoft\AiActCompliance\Alerting\Channels\EmailFallbackChannel::class,
        ],
    ],

    'fria' => [
        /*
        |----------------------------------------------------------------------
        | FRIA — Fundamental Rights Impact Assessment (AI Act Art. 27)
        |----------------------------------------------------------------------
        |
        | Defaults applied when a FriaService::open() call doesn't pass an
        | explicit `review_cadence_days`. AI Act Art. 27 doesn't fix a
        | numeric cadence but stipulates that the assessment must stay
        | current; six months is the de-facto enterprise default.
        */
        'default_review_cadence_days' => (int) env('AI_ACT_FRIA_REVIEW_DAYS', 180),
    ],
];
