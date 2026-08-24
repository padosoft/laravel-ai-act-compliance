---
title: "IAM Delegated Agents"
description: "Delegated AI-agent access as native AI Act evidence: grants become Art. 14 human-oversight records, agents enter the Art. 6 risk register — automatically."
---

# IAM delegated agents — Art. 14 & Art. 6, automatically

[`laravel-iam-agents`](https://github.com/padosoft/laravel-iam-agents) lets a human delegate part
of their authority to an AI agent through a **consented, revocable, budgeted delegation grant**.
This bridge (opt-in) turns those IAM facts into AI Act evidence without any manual bookkeeping:

| IAM event | Compliance record |
| --- | --- |
| Delegation grant created | `HumanReview` in state `approved` — subject `iam_delegation_grant`, reviewer = the delegating user, notes carrying agent, scopes, purpose, expiry, budget and the **consent evidence** (confirmation id + achieved AAL) |
| Delegation grant revoked | The same review transitions to `rejected`, with who revoked and when appended — one row per grant, its full story |
| Agent approved (the human gate) | `RiskRegisterEntry` — name `AI agent: NAME [agt_…]`, `article_refs` `["AI Act Art. 6", "AI Act Art. 14"]`, description with operator, scopes ceiling and approving admin |
| Agent suspended (admin or [rebel-ai-guard](https://github.com/padosoft/laravel-rebel-ai-guard) anomaly) | Entry status → `mitigating`, reason appended |
| Agent retired (terminal) | Entry status → `closed` |

## Why this satisfies Art. 14 (and how)

Art. 14 asks for *effective human oversight* of AI systems. In the delegated-access model the
oversight is **structural**: an agent can only act inside the strict intersection of what the
user allows and what the agent's approved ceiling allows, and every delegation is an explicit,
parameter-bound step-up consent. The bridge records exactly that decision — the review lands
directly in `approved` because the human decision **already happened** (the bound consent). This
is recording evidence, not asking twice.

A revocation is oversight too, and must be as visible as the grant: the record transitions to
`rejected` instead of vanishing.

## Enabling

```php
// config/ai-act-compliance.php
'iam_delegation' => [
    'enabled' => true,               // default false — explicit opt-in
    'default_risk_category' => 'limited', // low|limited|high|unacceptable
],
```

Double-gated: the listeners register only when the toggle is on **and** `laravel-iam-agents` is
installed (`class_exists` guard — without the IAM suite the whole block is a no-op, and the
package keeps its zero-hard-dependencies posture).

`default_risk_category` is deliberately yours to set: whether a delegated agent is *high-risk*
under Art. 6 depends on the **domain** it acts in (recruitment? credit? support tickets?) —
only the host application knows. Unknown values fall back to `limited`.

## What the records look like

```text
HumanReview
  subject_type: iam_delegation_grant
  subject_id:   dgr_01J9…
  state:        approved
  reviewer_id:  user:42
  review_notes: Delegated access granted to AI agent "Support Copilot" (agt_01J8…).
                Scopes: orders:read, orders:write.
                Purpose: Order assistance.
                Expires: 2026-09-01T00:00:00+00:00.
                Budget: {"amount":25,"calls":100,"currency":"EUR"}.
                Consent evidence: confirmation stepup_abc123 (AAL aal2).
```

The `[agentId]` key inside the risk-entry name is the stable join the lifecycle listener uses on
suspend/retire — and the string you can search from the admin SPA.

## Boundaries

- **Opt-in, always**: default config records nothing.
- **The bridge never decides** — it records decisions IAM already enforced. Suspending an agent,
  revoking a grant, refusing an exchange all happen (and are audited) in the IAM suite; this
  package is the compliance ledger of those facts.
- Lifecycle events for agents approved **before** the bridge was enabled are ignored (no partial
  tails without heads); a revocation without a prior record still creates the evidence — late
  evidence beats no evidence.
