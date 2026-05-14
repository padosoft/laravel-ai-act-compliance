<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;

class SettingsController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'enabled' => config('ai-act-compliance.enabled', true),
                'routes' => config('ai-act-compliance.routes', []),
                'disclosure' => config('ai-act-compliance.disclosure', []),
                'dsar' => config('ai-act-compliance.dsar', []),
                'consent' => config('ai-act-compliance.consent', []),
            ],
        ]);
    }
}
