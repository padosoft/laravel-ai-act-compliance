<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ComplianceOverviewController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'ai-act-compliance',
        ]);
    }
}
