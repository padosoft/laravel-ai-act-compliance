<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\AiActCompliance\BiasMonitoring\Services\BiasMonitorService;

class BiasController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => \Padosoft\AiActCompliance\BiasMonitoring\Models\BiasSnapshot::query()->latest()->paginate(25)]);
    }

    public function capture(Request $request, BiasMonitorService $service): JsonResponse
    {
        $data = $request->validate([
            'context' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $service->capture($data['context'] ?? [])], 201);
    }
}
