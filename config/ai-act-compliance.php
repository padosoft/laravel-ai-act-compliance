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

        /*
        |--------------------------------------------------------------
        | Evidence URL template
        |--------------------------------------------------------------
        |
        | Persisted on every alert as the link the DPO clicks to
        | inspect the underlying snapshot. Supports `{tenant_id}`,
        | `{metric_name}`, and `{cohort}` placeholders. Hosts using
        | the companion admin SPA typically point this at
        | `/admin/ai-act-compliance/bias?tenant={tenant_id}&metric={metric_name}`.
        | Leave null to omit the link from alert messages.
        */
        'evidence_url_template' => env('AI_ACT_ALERT_EVIDENCE_URL_TEMPLATE'),
    ],

    'regulatory_feed' => [
        /*
        |----------------------------------------------------------------------
        | Regulatory change auto-flagger (v1.4)
        |----------------------------------------------------------------------
        |
        | Default OFF — existing tenants see no behaviour change. When
        | enabled, the `ai-act:regulatory-poll` Artisan command (intended
        | to be scheduled daily) walks every configured driver, ingests
        | `RegulatoryAmendment` rows for entries not yet seen, and fires
        | `RegulatoryAmendmentDetected` for downstream listeners.
        |
        | `drivers` maps the driver name persisted on
        | `regulatory_amendments.source_driver` to the FQCN that knows how
        | to fetch + parse the upstream feed. Host apps add custom drivers
        | here. Each driver MUST implement RegulatoryFeedDriver.
        |
        | `impacted_clause_patterns` is the keyword/regex map used by
        | ImpactedClauseDetector to map amendment text to AI Act article
        | references (Art. 5 / Art. 9 / Art. 10 / Art. 14 / Art. 15 /
        | Art. 27 / Art. 50). Hosts may override or extend.
        */
        'enabled' => env('AI_ACT_REGULATORY_FEED_ENABLED', false),

        'drivers' => [
            'eu-ai-act-rss' => \Padosoft\AiActCompliance\RegulatoryFeed\Drivers\RssRegulatoryFeedDriver::class,
        ],

        'sources' => [
            'eu-ai-act-rss' => [
                'feed_url' => env(
                    'AI_ACT_REGULATORY_FEED_URL',
                    'https://eur-lex.europa.eu/EN/legal-content/summaries/AI-act.xml',
                ),
                'max_entries_per_poll' => (int) env('AI_ACT_REGULATORY_FEED_MAX_ENTRIES', 50),
                'request_timeout_seconds' => (int) env('AI_ACT_REGULATORY_FEED_TIMEOUT', 15),
            ],
        ],

        // Patterns are case-insensitive across the board. Legal feed
        // text commonly uses plural forms ("Articles 5 and 9", "Arts.
        // 10 to 15") so `Articles?` / `Arts?` are accepted alongside
        // the singular `Art` / `Article`. Copilot iter-1 review on
        // PR #4 caught the plural + case-sensitive `FRIA` gaps.
        'impacted_clause_patterns' => [
            'AI Act Art. 5' => ['/\b(?:Art|Article)s?\.?\s*5\b/i', '/\bprohibited\s+AI\s+practices?\b/i'],
            'AI Act Art. 9' => ['/\b(?:Art|Article)s?\.?\s*9\b/i', '/\brisk\s+management\s+system\b/i'],
            'AI Act Art. 10' => ['/\b(?:Art|Article)s?\.?\s*10\b/i', '/\bdata\s+governance\b/i', '/\btraining\s+data\b/i'],
            'AI Act Art. 14' => ['/\b(?:Art|Article)s?\.?\s*14\b/i', '/\bhuman\s+oversight\b/i'],
            'AI Act Art. 15' => ['/\b(?:Art|Article)s?\.?\s*15\b/i', '/\baccuracy\b.*\brobustness\b/i', '/\bcyber\s*security\b/i'],
            'AI Act Art. 27' => ['/\b(?:Art|Article)s?\.?\s*27\b/i', '/\bfundamental\s+rights\s+impact\b/i', '/\bFRIA\b/i'],
            'AI Act Art. 50' => ['/\b(?:Art|Article)s?\.?\s*50\b/i', '/\btransparency\s+obligations?\b/i'],
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
