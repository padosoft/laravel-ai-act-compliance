---
title: ADR
description: Architecture decision records for the package.
---

# ADR

::: collapsible "ADR-001: Laravel-native package boundaries"
Decision: expose middleware, services, controllers, migrations, and contracts through normal Laravel package conventions.

Consequence: Laravel teams can adopt modules incrementally without a sidecar service.
:::

::: collapsible "ADR-002: Config-gated modules"
Decision: modules are safe by default and enabled through configuration.

Consequence: adopters can migrate one compliance workflow at a time and avoid surprise runtime behavior.
:::

::: collapsible "ADR-003: Host contracts for sensitive domain data"
Decision: DSAR and metric workflows call host-provided contracts.

Consequence: data ownership remains with the application, while package services keep state, timing, and evidence consistent.
:::

::: collapsible "ADR-004: Immutable transitions for incidents"
Decision: incident state changes are appended as transition records.

Consequence: operational history is audit-friendly and avoids silent rewrites.
:::

::: collapsible "ADR-005: Multi-tenant context is request-scoped"
Decision: tenant context is resolved per request and consumed by services.

Consequence: APIs and dashboards can isolate compliance records without global mutable state.
:::
