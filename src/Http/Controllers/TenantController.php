<?php

namespace Padosoft\AiActCompliance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Padosoft\AiActCompliance\MultiTenancy\Enums\SubscriptionTier;
use Padosoft\AiActCompliance\MultiTenancy\Enums\TenantStatus;
use Padosoft\AiActCompliance\MultiTenancy\Models\Tenant;
use Padosoft\AiActCompliance\MultiTenancy\Services\CrossTenantOverviewService;

class TenantController
{
    public function index(CrossTenantOverviewService $overview): JsonResponse
    {
        return response()->json(['data' => $overview->compile()]);
    }

    public function store(Request $request): JsonResponse
    {
        $tierValues = array_map(static fn (SubscriptionTier $t) => $t->value, SubscriptionTier::cases());
        $statusValues = array_map(static fn (TenantStatus $s) => $s->value, TenantStatus::cases());

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'unique:tenants,slug'],
            'name' => ['required', 'string', 'max:200'],
            'subscription_tier' => ['nullable', 'in:'.implode(',', $tierValues)],
            'status' => ['nullable', 'in:'.implode(',', $statusValues)],
            'dpo_email' => ['nullable', 'email', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'config_overrides_json' => ['nullable', 'array'],
        ]);
        $data['subscription_tier'] = $data['subscription_tier'] ?? SubscriptionTier::Team->value;
        $data['status'] = $data['status'] ?? TenantStatus::Active->value;

        return response()->json(['data' => Tenant::query()->create($data)], status: 201);
    }

    public function show(string $slug, CrossTenantOverviewService $overview): JsonResponse
    {
        $tenant = Tenant::query()->bySlug($slug)->firstOrFail();

        return response()->json([
            'data' => [
                'tenant' => $tenant,
                'kpis' => $overview->kpisForTenant($slug),
            ],
        ]);
    }

    public function update(string $slug, Request $request): JsonResponse
    {
        $tenant = Tenant::query()->bySlug($slug)->firstOrFail();
        $tierValues = array_map(static fn (SubscriptionTier $t) => $t->value, SubscriptionTier::cases());
        $statusValues = array_map(static fn (TenantStatus $s) => $s->value, TenantStatus::cases());

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'subscription_tier' => ['sometimes', 'in:'.implode(',', $tierValues)],
            'status' => ['sometimes', 'in:'.implode(',', $statusValues)],
            'dpo_email' => ['sometimes', 'nullable', 'email', 'max:200'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:200'],
            'config_overrides_json' => ['sometimes', 'nullable', 'array'],
        ]);

        // Auto-stamp suspended_at / archived_at on the FIRST transition
        // into those statuses; preserves the original audit timestamp on
        // any subsequent bounce-back.
        if (isset($data['status'])) {
            $next = $data['status'];
            if ($next === TenantStatus::Suspended->value && $tenant->suspended_at === null) {
                $data['suspended_at'] = Carbon::now();
            }
            if ($next === TenantStatus::Archived->value && $tenant->archived_at === null) {
                $data['archived_at'] = Carbon::now();
            }
        }
        $tenant->update($data);

        return response()->json(['data' => $tenant->fresh()]);
    }
}
