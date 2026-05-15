<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentSeverity;
use Padosoft\AiActCompliance\RegulatoryFeed\Enums\RegulatoryAmendmentStatus;
use Padosoft\AiActCompliance\RegulatoryFeed\Models\RegulatoryAmendment;
use Padosoft\AiActCompliance\RegulatoryFeed\Services\RegulatoryFeedPoller;

class RegulatoryAmendmentController
{
    public function index(Request $request): JsonResponse
    {
        $query = RegulatoryAmendment::query();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }
        $query->latest('ingested_at');

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => RegulatoryAmendment::query()->findOrFail($id)]);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $statusValues = array_map(static fn (RegulatoryAmendmentStatus $s) => $s->value, RegulatoryAmendmentStatus::cases());
        $severityValues = array_map(static fn (RegulatoryAmendmentSeverity $s) => $s->value, RegulatoryAmendmentSeverity::cases());

        $data = $request->validate([
            'status' => ['sometimes', 'in:'.implode(',', $statusValues)],
            'severity' => ['sometimes', 'in:'.implode(',', $severityValues)],
            'triaged_by' => ['sometimes', 'nullable', 'string', 'max:100'],
            'triage_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $row = RegulatoryAmendment::query()->findOrFail($id);
        // Auto-stamp triaged_at when status transitions from pending
        // to anything else — operators don't have to remember to send
        // both fields.
        if (
            isset($data['status'])
            && $row->status === RegulatoryAmendmentStatus::Pending->value
            && $data['status'] !== RegulatoryAmendmentStatus::Pending->value
        ) {
            $data['triaged_at'] = Carbon::now();
        }
        $row->update($data);

        return response()->json(['data' => $row->fresh()]);
    }

    public function poll(RegulatoryFeedPoller $poller): JsonResponse
    {
        if (! config('ai-act-compliance.regulatory_feed.enabled', false)) {
            return response()->json(
                ['error' => 'regulatory_feed.enabled=false'],
                status: 409,
            );
        }
        $result = $poller->poll();

        return response()->json(['data' => $result]);
    }
}
