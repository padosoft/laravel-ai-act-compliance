---
title: Installation
description: Runtime requirements and setup choices.
---

# Installation

## Requirements

| Requirement | Supported |
| --- | --- |
| PHP | `^8.2` |
| Laravel components | `^11.0`, `^12.0`, `^13.0` |
| Extensions | `ext-libxml`, `ext-simplexml` |
| Database | Any Laravel-supported database compatible with package migrations |

## Composer metadata

The package auto-discovers `Padosoft\AiActCompliance\AiActComplianceServiceProvider` through Laravel package discovery.

```json
{
  "name": "padosoft/laravel-ai-act-compliance",
  "description": "AI Act compliance bundle for Laravel AI applications",
  "license": "MIT"
}
```

## Publish strategy

::: tabs
### New application

Publish config and migrations immediately, then commit local configuration choices.

### Existing application

Review migrations in a branch and map existing consent, DSAR, incident, and audit tables before running them in production.

### Multi-tenant application

Enable tenant context middleware early so downstream records receive `tenant_id` consistently.
:::

::: callout warning "Production setup"
Configure queues, mail, webhooks, and scheduler before treating DSAR, incident, alerting, or regulatory polling flows as operational.
:::
