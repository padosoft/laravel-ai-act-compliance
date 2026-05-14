<?php

namespace Padosoft\AiActCompliance\DSAR\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataDeleter;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataExporter;
use Padosoft\AiActCompliance\DSAR\Enums\DsarStatus;
use Padosoft\AiActCompliance\DSAR\Enums\DsarType;
use Padosoft\AiActCompliance\DSAR\Models\DsarRequest;

class DsarService
{
    public function __construct(
        private readonly UserDataExporter $exporter,
        private readonly UserDataDeleter $deleter,
    ) {}

    public function open(object $user, DsarType $type): DsarRequest
    {
        $slaDays = (int) config('ai-act-compliance.dsar.default_sla_days', 30);

        return DsarRequest::query()->create([
            'user_id' => $this->resolveUserId($user),
            'type' => $type->value,
            'status' => DsarStatus::PENDING->value,
            'sla_due_at' => CarbonImmutable::now()->addDays($slaDays),
        ]);
    }

    /**
     * Resolve the subject's stable string identifier. Prefers Laravel's
     * Authenticatable contract (so host User models work out of the box)
     * and falls back to a public `id` property for plain DTOs / value
     * objects.
     */
    private function resolveUserId(object $user): string
    {
        if ($user instanceof Authenticatable) {
            return (string) $user->getAuthIdentifier();
        }
        return (string) ($user->id ?? '');
    }

    public function execute(DsarRequest $request, object $user): array
    {
        $request->update(['status' => DsarStatus::IN_PROGRESS->value]);

        if ($request->type === DsarType::EXPORT->value) {
            $payload = $this->exporter->export($user);
            $request->update([
                'status' => DsarStatus::COMPLETED->value,
                'result_payload' => $payload,
            ]);

            return $payload;
        }

        if ($request->type === DsarType::DELETE->value) {
            $this->deleter->delete($user);
            $payload = ['deleted' => true];
            $request->update([
                'status' => DsarStatus::COMPLETED->value,
                'result_payload' => $payload,
            ]);

            return $payload;
        }

        $payload = ['rejected' => true];
        $request->update([
            'status' => DsarStatus::REJECTED->value,
            'result_payload' => $payload,
        ]);

        return $payload;
    }
}
