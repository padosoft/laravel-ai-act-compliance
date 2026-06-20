---
title: Operations Runbook
description: Day-two operation of compliance workflows.
---

# Operations Runbook

## Daily

Review open DSAR requests, high-severity incidents, failed alert dispatches, and regulatory amendments.

## Weekly

Review bias snapshots, drift patterns, tenant overrides, and overdue human reviews.

## Release checklist

::: steps
1. Update risk register entries for changed AI behavior.
2. Confirm disclosures still match the user experience.
3. Run DSAR exporter/deleter tests against changed models.
4. Verify metric thresholds and alert routes.
5. Record mitigation or attestation notes for material changes.
:::

::: callout tip "Operational owner"
Assign an owner for every enabled module. Unowned controls decay quickly.
:::
