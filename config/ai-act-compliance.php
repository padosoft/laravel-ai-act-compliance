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
