<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\AiActCompliance\Consent\Models\ConsentRecord;
use Padosoft\AiActCompliance\Consent\Services\ConsentService;

class ConsentController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ConsentRecord::query()->latest()->paginate(25)]);
    }

    public function grant(Request $request, ConsentService $service): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'string'],
            'feature' => ['required', 'string'],
        ]);

        return response()->json(['data' => $service->grant($data['user_id'], $data['feature'])]);
    }

    public function revoke(Request $request, ConsentService $service): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'string'],
            'feature' => ['required', 'string'],
        ]);

        return response()->json(['data' => $service->revoke($data['user_id'], $data['feature'])]);
    }
}
