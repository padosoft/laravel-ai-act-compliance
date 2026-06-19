---
title: Security and Privacy
description: Protect personal data and operational secrets in compliance workflows.
---

# Security and Privacy

Compliance systems often contain sensitive data because they aggregate incidents, identities, consent, and rights requests.

## Practices

::: grids
  ::: grid
    ::: card "Minimize"
    Store identifiers and evidence needed for compliance, not full prompt or user data dumps by default.
    :::
  :::
  ::: grid
    ::: card "Encrypt"
    Use Laravel encryption for webhook secrets and sensitive configuration.
    :::
  :::
  ::: grid
    ::: card "Authorize"
    Restrict compliance APIs to DPO, security, legal, and accountable owners.
    :::
  :::
:::

## DSAR safety

::: callout danger "Do not log subjects casually"
Avoid logging requester email addresses, identity artifacts, exported data, or deletion scopes at info level.
:::

## Tenant safety

Always test tenant context middleware for missing, locked, deleted, and active tenants.
