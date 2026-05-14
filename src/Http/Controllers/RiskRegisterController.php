<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\AiActCompliance\RiskRegister\Models\RiskRegisterEntry;
use Padosoft\AiActCompliance\RiskRegister\Services\RiskRegisterService;

class RiskRegisterController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => RiskRegisterEntry::query()->latest()->paginate(25)]);
    }

    public function store(Request $request, RiskRegisterService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'category' => ['required', 'in:low,limited,high,unacceptable'],
            'status' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'string'],
            'article_refs' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $service->create($data)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => RiskRegisterEntry::query()->findOrFail($id)]);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $row = RiskRegisterEntry::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'category' => ['sometimes', 'in:low,limited,high,unacceptable'],
            'status' => ['sometimes', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'owner_id' => ['sometimes', 'nullable', 'string'],
            'article_refs' => ['sometimes', 'nullable', 'array'],
        ]);
        $row->update($data);

        return response()->json(['data' => $row->fresh()]);
    }
}
