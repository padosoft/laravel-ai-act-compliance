<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\DSAR\Enums\DsarStatus;
use Padosoft\AiActCompliance\DSAR\Enums\DsarType;
use Padosoft\AiActCompliance\HumanReviewTracker\Enums\HumanReviewState;
use Padosoft\AiActCompliance\Incident\Enums\IncidentSeverity;
use Padosoft\AiActCompliance\Incident\Enums\IncidentStatus;
use Padosoft\AiActCompliance\RiskRegister\Enums\AiActRiskCategory;
use Padosoft\AiActCompliance\Support\ComplianceEvents;
use Padosoft\AiActCompliance\Tests\TestCase;

class PackageMetadataTest extends TestCase
{
    public function test_package_events_are_stable(): void
    {
        self::assertSame('ai-act.dsar.created', ComplianceEvents::DSAR_CREATED);
        self::assertSame('ai-act.risk.created', ComplianceEvents::RISK_CREATED);
        self::assertSame('ai-act.incident.created', ComplianceEvents::INCIDENT_CREATED);
    }

    public function test_package_enums_expose_expected_values(): void
    {
        self::assertSame(['pending', 'in_progress', 'completed', 'rejected'], array_column(DsarStatus::cases(), 'value'));
        self::assertSame(['export', 'delete', 'rectify'], array_column(DsarType::cases(), 'value'));
        self::assertSame(['low', 'limited', 'high', 'unacceptable'], array_column(AiActRiskCategory::cases(), 'value'));
        self::assertSame(['open', 'triage', 'mitigating', 'closed'], array_column(IncidentStatus::cases(), 'value'));
        self::assertSame(['low', 'medium', 'high', 'critical'], array_column(IncidentSeverity::cases(), 'value'));
        self::assertSame(['pending', 'approved', 'rejected', 'escalated'], array_column(HumanReviewState::cases(), 'value'));
    }
}
