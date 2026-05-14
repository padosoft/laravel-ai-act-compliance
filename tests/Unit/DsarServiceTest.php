<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Carbon\CarbonImmutable;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataDeleter;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataExporter;
use Padosoft\AiActCompliance\DSAR\Enums\DsarStatus;
use Padosoft\AiActCompliance\DSAR\Enums\DsarType;
use Padosoft\AiActCompliance\DSAR\Services\DsarService;
use Padosoft\AiActCompliance\Tests\TestCase;

class DsarServiceTest extends TestCase
{
    public function test_open_and_execute_export_requests_persist_the_export_payload(): void
    {
        $this->app->instance(UserDataExporter::class, new class implements UserDataExporter
        {
            public function export(object $user): array
            {
                return ['id' => $user->id, 'email' => 'user@example.test'];
            }
        });

        $this->app->instance(UserDataDeleter::class, new class implements UserDataDeleter
        {
            public function delete(object $user): void
            {
            }
        });

        $service = $this->app->make(DsarService::class);
        $user = (object) ['id' => 'user-1'];

        $request = $service->open($user, DsarType::EXPORT)->fresh();
        $payload = $service->execute($request, $user);

        self::assertSame(['id' => 'user-1', 'email' => 'user@example.test'], $payload);
        self::assertInstanceOf(CarbonImmutable::class, $request->fresh()->sla_due_at);
        self::assertSame(DsarStatus::COMPLETED->value, $request->fresh()->status);
        self::assertSame($payload, $request->fresh()->result_payload);
    }

    public function test_execute_delete_requests_marks_the_request_completed(): void
    {
        $deleter = new class implements UserDataDeleter
        {
            public array $deletedUsers = [];

            public function delete(object $user): void
            {
                $this->deletedUsers[] = $user->id;
            }
        };

        $this->app->instance(UserDataExporter::class, new class implements UserDataExporter
        {
            public function export(object $user): array
            {
                return [];
            }
        });
        $this->app->instance(UserDataDeleter::class, $deleter);

        $service = $this->app->make(DsarService::class);
        $user = (object) ['id' => 'user-2'];
        $request = $service->open($user, DsarType::DELETE);

        $payload = $service->execute($request, $user);

        self::assertSame(['deleted' => true], $payload);
        self::assertSame(['user-2'], $deleter->deletedUsers);
        self::assertSame(DsarStatus::COMPLETED->value, $request->fresh()->status);
        self::assertSame($payload, $request->fresh()->result_payload);
    }

    public function test_unsupported_dsar_types_are_rejected_and_recorded(): void
    {
        $this->app->instance(UserDataExporter::class, new class implements UserDataExporter
        {
            public function export(object $user): array
            {
                return [];
            }
        });
        $this->app->instance(UserDataDeleter::class, new class implements UserDataDeleter
        {
            public function delete(object $user): void
            {
            }
        });

        $service = $this->app->make(DsarService::class);
        $user = (object) ['id' => 'user-3'];
        $request = $service->open($user, DsarType::RECTIFY);

        $payload = $service->execute($request, $user);

        self::assertSame(['rejected' => true], $payload);
        self::assertSame(DsarStatus::REJECTED->value, $request->fresh()->status);
        self::assertSame($payload, $request->fresh()->result_payload);
    }
}
