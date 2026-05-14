<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\HumanReviewTracker\Models\HumanReview;
use Padosoft\AiActCompliance\HumanReviewTracker\Services\HumanReviewService;

class HumanReviewController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => HumanReview::query()->latest()->paginate(25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['nullable', 'string'],
            'subject_id' => ['nullable', 'string'],
            'review_notes' => ['nullable', 'string'],
            'reviewer_id' => ['nullable', 'string'],
        ]);

        $row = HumanReview::query()->create($data + ['state' => HumanReviewState::PENDING->value]);

        return response()->json(['data' => $row], 201);
    }

    public function transition(int $id, Request $request, HumanReviewService $service): JsonResponse
    {
        $row = HumanReview::query()->findOrFail($id);
        $data = $request->validate([
            'state' => ['required', 'in:pending,approved,rejected,escalated'],
        ]);

        return response()->json(['data' => $service->transition($row, HumanReviewState::from($data['state']))]);
    }
}
