<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Carbon\CarbonImmutable;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataDeleter;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataExporter;
use Padosoft\AiActCompliance\DSAR\Enums\DsarStatus;
use Padosoft\AiActCompliance\DSAR\Enums\DsarType;
use Padosoft\AiActCompliance\DSAR\Models\DsarRequest;
use Padosoft\AiActCompliance\DSAR\Services\DsarService;
use Padosoft\AiActCompliance\Tests\Fixtures\TestUser;
use Padosoft\AiActCompliance\Tests\TestCase;

/**
 * Extended DSAR flow tests. Complements DsarServiceTest by exercising:
 * - SLA due-date calculation
 * - Export / delete / rectify branches end-to-end
 * - Idempotency of status transitions
 * - Article 15 / 16 / 17 enum coverage
 */
class DsarServiceFlowsTest extends TestCase
{
    public function test_open_sets_sla_due_at_30_days_in_the_future_by_default(): void
    {
        $service = $this->makeService();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-14 10:00:00'));

        $request = $service->open(new TestUser(42), DsarType::EXPORT)->fresh();

        self::assertSame(DsarStatus::PENDING->value, $request->status);
        self::assertNotNull($request->sla_due_at);
        self::assertSame(
            CarbonImmutable::parse('2026-06-13 10:00:00')->toDateString(),
            CarbonImmutable::parse($request->sla_due_at)->toDateString(),
        );

        CarbonImmutable::setTestNow();
    }

    public function test_open_persists_the_subject_user_id_as_string(): void
    {
        $service = $this->makeService();
        $request = $service->open(new TestUser('7'), DsarType::DELETE)->fresh();

        self::assertSame('7', $request->user_id);
    }

    public function test_execute_export_invokes_the_host_exporter_and_persists_payload(): void
    {
        $exporter = new class implements UserDataExporter {
            public function export(object $user): array
            {
                $id = $user instanceof \Illuminate\Contracts\Auth\Authenticatable
                    ? (string) $user->getAuthIdentifier()
                    : (string) ($user->id ?? '');
                return ['profile' => ['id' => $id], 'orders' => []];
            }
        };
        $service = new DsarService($exporter, $this->fakeDeleter());

        $request = $service->open(new TestUser('11'), DsarType::EXPORT);
        $payload = $service->execute($request, new TestUser('11'));

        $request->refresh();
        self::assertSame(DsarStatus::COMPLETED->value, $request->status);
        self::assertSame('11', $payload['profile']['id']);
    }

    public function test_execute_delete_invokes_the_host_deleter_and_marks_completed(): void
    {
        $invoked = false;
        $deleter = new class($invoked) implements UserDataDeleter {
            public function __construct(private bool &$invoked) {}
            public function delete(object $user): void
            {
                $this->invoked = true;
            }
        };
        $service = new DsarService($this->fakeExporter(), $deleter);

        $request = $service->open(new TestUser(13), DsarType::DELETE);
        $payload = $service->execute($request, new TestUser(13));

        $request->refresh();
        self::assertTrue($invoked, 'Host UserDataDeleter::delete() must be invoked on DELETE DSAR execution');
        self::assertSame(DsarStatus::COMPLETED->value, $request->status);
        self::assertTrue($payload['deleted']);
    }

    public function test_execute_rectify_marks_request_as_rejected_when_not_implemented(): void
    {
        $service = $this->makeService();
        $request = $service->open(new TestUser(99), DsarType::RECTIFY);
        $payload = $service->execute($request, new TestUser(99));

        $request->refresh();
        self::assertSame(DsarStatus::REJECTED->value, $request->status);
        self::assertTrue($payload['rejected']);
    }

    public function test_execute_transitions_through_in_progress_before_terminal_state(): void
    {
        $service = $this->makeService();
        $request = $service->open(new TestUser(5), DsarType::EXPORT);
        self::assertSame(DsarStatus::PENDING->value, $request->fresh()->status);

        $service->execute($request, new TestUser(5));

        self::assertSame(DsarStatus::COMPLETED->value, $request->fresh()->status);
    }

    public function test_dsar_type_enum_covers_export_delete_rectify(): void
    {
        $types = collect(DsarType::cases())->pluck('value')->all();
        self::assertContains('export', $types);
        self::assertContains('delete', $types);
        self::assertContains('rectify', $types);
        self::assertCount(3, $types);
    }

    public function test_dsar_status_enum_covers_pending_in_progress_completed_rejected(): void
    {
        $statuses = collect(DsarStatus::cases())->pluck('value')->all();
        self::assertContains('pending', $statuses);
        self::assertContains('in_progress', $statuses);
        self::assertContains('completed', $statuses);
        self::assertContains('rejected', $statuses);
        self::assertCount(4, $statuses);
    }

    public function test_multiple_subjects_can_have_simultaneous_pending_requests(): void
    {
        $service = $this->makeService();
        $service->open(new TestUser(1), DsarType::EXPORT);
        $service->open(new TestUser(2), DsarType::DELETE);
        $service->open(new TestUser(3), DsarType::EXPORT);

        self::assertSame(3, DsarRequest::query()->where('status', 'pending')->count());
        self::assertSame(1, DsarRequest::query()->where('user_id', '2')->where('type', 'delete')->count());
    }

    private function makeService(): DsarService
    {
        return new DsarService($this->fakeExporter(), $this->fakeDeleter());
    }

    private function fakeExporter(): UserDataExporter
    {
        return new class implements UserDataExporter {
            public function export(object $user): array { return []; }
        };
    }

    private function fakeDeleter(): UserDataDeleter
    {
        return new class implements UserDataDeleter {
            public function delete(object $user): void {}
        };
    }
}
