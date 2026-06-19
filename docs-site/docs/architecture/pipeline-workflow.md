---
title: Pipeline and Workflow
description: End-to-end compliance pipeline from AI feature request to operational evidence.
---

# Pipeline and Workflow

```mermaid
flowchart TD
    A[AI feature request] --> B[Risk classification]
    B --> C{User-facing AI?}
    C -->|Yes| D[Disclosure and consent]
    C -->|No| E[Internal control check]
    D --> F[Runtime telemetry]
    E --> F
    F --> G[Bias snapshots]
    F --> H[Human review queue]
    G --> I{Drift detected?}
    I -->|Yes| J[Alert dispatch]
    H --> K{Failure?}
    K -->|Yes| L[Incident ticket]
    J --> L
    L --> M[Mitigation and attestation]
```

## Workflow gates

::: steps
1. Register the use case before launch.
2. Enable request-time controls for transparency, consent, security, and tenant scope.
3. Capture operational metrics and human review decisions.
4. Escalate drift or failures through alerting and incident state transitions.
5. Produce attestation evidence for audits and customer requests.
:::
