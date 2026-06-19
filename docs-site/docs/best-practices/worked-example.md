---
title: Worked Example
description: A complete support assistant compliance setup.
---

# Worked Example

Scenario: a Laravel SaaS adds an AI support triage assistant that classifies tickets, drafts replies, and escalates uncertain cases to humans.

## Implementation path

::: steps
1. Add a limited-risk register entry named `Support triage assistant`.
2. Add `ai-act.disclosure` middleware to support assistant routes.
3. Require consent before using user history in draft generation.
4. Register a `refusal_rate` and `escalation_rate` cohort metric.
5. Send low-confidence outputs to the human review tracker.
6. Open an incident when drift or hallucination crosses severity policy.
7. Generate an attestation for enterprise customer review.
:::

## Minimal code map

```php
app('ai-act.risks')->create([
    'name' => 'Support triage assistant',
    'risk_category' => 'limited',
    'owner' => 'support-ops',
]);

app('ai-act.bias')->register('escalation_rate', EscalationRateMetric::class);
```

::: callout info "Why this works"
The AI feature is not documented once at launch and forgotten. It remains connected to runtime controls, monitoring, review, and incident evidence.
:::
