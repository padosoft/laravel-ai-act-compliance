---
title: Gotchas and Limits
description: Known limitations and sharp edges.
---

# Gotchas and Limits

::: callout warning "Package boundary"
This package provides compliance primitives. It does not certify your system, replace legal advice, or automatically make an AI system compliant.
:::

## Common gotchas

| Gotcha | Mitigation |
| --- | --- |
| DSAR exporter misses soft-deleted records | Include trashed records where legally required |
| Consent revocation treated as deletion | Keep revocation and erasure workflows separate |
| Bias metrics lack baseline | Define baseline cohort and review window before launch |
| Incident transitions edited manually | Use service transitions and append notes |
| Tenant context missing in jobs | Pass tenant identifiers into queued work explicitly |

## Limits

::: collapsible "Legal interpretation"
Risk category, Annex III scope, and incident reporting thresholds still need expert review.
:::

::: collapsible "Model observability"
The package records metrics you provide. It cannot observe opaque third-party model behavior unless your application emits telemetry.
:::

::: collapsible "Data discovery"
DSAR exports and deletions depend on host contracts. Hidden stores remain the host application's responsibility.
:::
