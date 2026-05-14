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
    ],
];
