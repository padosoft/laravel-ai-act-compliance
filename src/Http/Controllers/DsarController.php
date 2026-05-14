<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\AiActCompliance\DSAR\Enums\DsarType;
use Padosoft\AiActCompliance\DSAR\Models\DsarRequest;
use Padosoft\AiActCompliance\DSAR\Services\DsarService;

class DsarController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => DsarRequest::query()->latest()->paginate(25)]);
    }

    public function store(Request $request, DsarService $service): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'string'],
            'type' => ['required', 'in:export,delete,rectify'],
        ]);

        $user = (object) ['id' => $data['user_id']];
        $row = $service->open($user, DsarType::from($data['type']));

        return response()->json(['data' => $row], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => DsarRequest::query()->findOrFail($id)]);
    }

    public function execute(int $id, DsarService $service): JsonResponse
    {
        $row = DsarRequest::query()->findOrFail($id);
        $user = (object) ['id' => $row->user_id];
        $result = $service->execute($row, $user);

        return response()->json(['data' => $row->fresh(), 'result' => $result]);
    }
}
