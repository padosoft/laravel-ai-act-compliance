---
title: Configuration
description: Configuration areas and module switches.
---

# Configuration

Configuration is published to `config/ai-act-compliance.php`.

## Areas

| Area | Typical settings |
| --- | --- |
| Disclosure | Enabled routes, banner text, Blade rendering |
| Consent | Required purposes, middleware behavior |
| DSAR | SLA days, exporter/deleter bindings, queue |
| Bias monitoring | Metrics, thresholds, windows |
| Alerting | Routes, throttles, circuit breaker |
| Regulatory feed | Feed URLs, clause detector patterns |
| Multi-tenancy | Tenant resolution, overrides, inactive handling |

::: callout warning "Default safe"
Treat disabled modules as intentional. Enable only after routes, permissions, queues, and owners are ready.
:::
