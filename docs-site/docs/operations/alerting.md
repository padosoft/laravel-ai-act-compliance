---
title: Alerting
description: Route drift and compliance alerts to operational channels.
---

# Alerting

Alerting routes compliance events to Slack, Discord, and email fallback channels with throttling and circuit breaking.

## Enable

```php
config(['ai-act-compliance.alerting.enabled' => true]);
```

## Route model

Store webhook URLs encrypted and keep severity escalation policy explicit.

::: tabs
### Slack

Use for primary operational notification.

### Discord

Use for engineering or community operations where appropriate.

### Email fallback

Use as the reliable backup when webhook delivery fails.
:::

::: callout warning "Severity bypass"
High-severity alerts may need to bypass throttles. Test that behavior before production.
:::
