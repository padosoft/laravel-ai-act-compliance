<?php

namespace Padosoft\AiActCompliance\DSAR\Services;

use Carbon\CarbonImmutable;
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
            'user_id' => (string) ($user->id ?? ''),
            'type' => $type->value,
            'status' => DsarStatus::PENDING->value,
            'sla_due_at' => CarbonImmutable::now()->addDays($slaDays),
        ]);
    }

    public function execute(DsarRequest $request, object $user): array
    {
        $request->update(['status' => DsarStatus::IN_PROGRESS->value]);

        if ($request->type === DsarType::EXPORT->value) {
            $payload = $this->exporter->export($user);
            $request->update(['status' => DsarStatus::COMPLETED->value]);
            return $payload;
        }

        if ($request->type === DsarType::DELETE->value) {
            $this->deleter->delete($user);
            $request->update(['status' => DsarStatus::COMPLETED->value]);
            return ['deleted' => true];
        }

        $request->update(['status' => DsarStatus::REJECTED->value]);

        return ['rejected' => true];
    }
}
