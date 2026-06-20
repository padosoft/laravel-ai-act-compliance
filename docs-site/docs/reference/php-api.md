---
title: PHP API
description: Service bindings, contracts, models, and enums.
---

# PHP API

## Service bindings

| Binding | Purpose |
| --- | --- |
| `ai-act.risks` | Risk register workflow |
| `ai-act.consent` | Consent grant and revoke workflow |
| `ai-act.bias` | Metric registry and snapshot workflow |
| `ai-act.incidents` | Incident workflow |

## Contracts

| Contract | Host responsibility |
| --- | --- |
| `UserDataExporter` | Return user data for access requests |
| `UserDataDeleter` | Delete or anonymize scoped user data |
| `CohortParityMetric` | Compute cohort metric result |
| `CohortDimensionResolver` | Resolve cohort dimensions |
| `RegulatoryFeedDriver` | Fetch feed entries |

## Important enums

| Enum | Domain |
| --- | --- |
| `AiActRiskCategory` | Risk register |
| `DsarStatus`, `DsarType` | DSAR |
| `IncidentStatus`, `IncidentSeverity` | Incidents |
| `HumanReviewState` | Human review |
| `FriaStatus` | FRIA |
| `TenantStatus`, `SubscriptionTier` | Multi-tenancy |
