<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\AiActCompliance\ComplianceAttestation\Models\ComplianceAttestation;
use Padosoft\AiActCompliance\ComplianceAttestation\Services\ComplianceAttestationService;

class ComplianceAttestationController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ComplianceAttestation::query()->latest()->paginate(25)]);
    }

    public function store(Request $request, ComplianceAttestationService $service): JsonResponse
    {
        $data = $request->validate([
            'attestation_type' => ['required', 'string'],
            'status' => ['nullable', 'string'],
            'generated_by' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $service->create($data)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => ComplianceAttestation::query()->findOrFail($id)]);
    }
}
