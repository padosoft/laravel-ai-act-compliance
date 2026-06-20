---
title: Overview
description: AI Act compliance bundle for Laravel AI applications.
---

# laravel-ai-act-compliance

`padosoft/laravel-ai-act-compliance` is a Laravel package for building audit-ready controls around AI features: disclosure, consent, risk registers, DSAR workflows, bias monitoring, human review, incident response, alerting, regulatory feeds, and multi-tenant compliance overview.

::: callout info "Project facts"
| Fact | Value |
| --- | --- |
| Package | `padosoft/laravel-ai-act-compliance` |
| Author | Padosoft |
| License | MIT |
| Primary runtime | PHP 8.2 with Laravel 11, 12, or 13 |
:::

::: grids
  ::: grid
    ::: card "Install and publish" icon:package
    Add the package, publish config and migrations, then run the migrations.
    :::
  :::
  ::: grid
    ::: card "Register host contracts" icon:plug
    Bind DSAR exporters, deleters, cohort dimensions, and custom metrics.
    :::
  :::
  ::: grid
    ::: card "Operate evidence" icon:file-check
    Use APIs, middleware, and services to keep regulator-facing records complete.
    :::
  :::
:::

## Core modules

| Module | Purpose | Evidence |
| --- | --- | --- |
| Disclosure | AI-use notice through Blade and middleware | AI Act Art. 50 |
| Consent | Polymorphic consent ledger with revocation | GDPR Art. 7 |
| Risk register | AI use-case classification and Annex III mapping | AI Act Art. 6 |
| DSAR | Access and deletion request tracking | GDPR Art. 15 and 17 |
| Bias monitoring | Cohort metric snapshots and drift events | AI Act Art. 10 and 15 |
| Human review | Decision review state machine | AI Act Art. 14 |
| Incident | Tickets, transitions, severity, escalation | AI Act Art. 73 |
| Regulatory feed | Amendment polling and clause impact detection | AI Act Art. 9 and 50 |
| Multi-tenancy | Tenant registry, context, overrides, overview | GDPR Art. 30 |

## Mental model

The package records compliance state as durable Laravel models and exposes focused services for application workflows. Your application remains responsible for domain truth, identity verification, and final legal assessment.

$$
Evidence = event + actor + subject + timestamp + article + decision
$$

::: callout warning "Not legal advice"
The package helps collect and route evidence. It does not replace legal review, DPIA or FRIA judgment, or supervisory authority guidance.
:::
