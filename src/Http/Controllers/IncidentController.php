<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\AiActCompliance\Incident\Models\IncidentStateTransition;
use Padosoft\AiActCompliance\Incident\Models\IncidentTicket;
use Padosoft\AiActCompliance\Incident\Services\IncidentService;

class IncidentController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => IncidentTicket::query()->latest()->paginate(25)]);
    }

    public function store(Request $request, IncidentService $service): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'description' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'string'],
        ]);

        return response()->json(['data' => $service->open($data)], 201);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = IncidentTicket::query()->findOrFail($id);

        return response()->json([
            'data' => $ticket,
            'transitions' => IncidentStateTransition::query()->where('incident_ticket_id', $ticket->id)->latest()->get(),
        ]);
    }

    public function transition(int $id, Request $request): JsonResponse
    {
        $ticket = IncidentTicket::query()->findOrFail($id);
        $data = $request->validate([
            'to_status' => ['required', 'in:open,triage,mitigating,closed'],
            'actor_id' => ['nullable', 'string'],
        ]);

        $from = $ticket->status;
        $ticket->update(['status' => $data['to_status']]);

        IncidentStateTransition::query()->create([
            'incident_ticket_id' => $ticket->id,
            'from_status' => $from,
            'to_status' => $data['to_status'],
            'actor_id' => $data['actor_id'] ?? null,
            'transitioned_at' => now(),
        ]);

        return response()->json(['data' => $ticket->fresh()]);
    }
}
