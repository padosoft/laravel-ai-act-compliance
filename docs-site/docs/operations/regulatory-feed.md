---
title: Regulatory Feed
description: Poll regulatory sources and map amendments to impacted clauses.
---

# Regulatory Feed

Regulatory feed support reads RSS 2.0 and Atom 1.0 sources, detects impacted clauses, and stores amendment records per tenant.

## Artisan command

```bash
php artisan ai-act:regulatory-poll
```

## Flow

```mermaid
flowchart LR
    Source[RSS or Atom source] --> Driver[RssRegulatoryFeedDriver]
    Driver --> Poller[RegulatoryFeedPoller]
    Poller --> Detector[ImpactedClauseDetector]
    Detector --> Store[regulatory_amendments]
    Store --> Event[RegulatoryAmendmentDetected]
```

::: callout warning "Feed hygiene"
Keep feeds allowlisted and parse XML with XXE-safe settings.
:::
