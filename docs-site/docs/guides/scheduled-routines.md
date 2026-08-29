---
title: "Scheduled Routines"
description: "Unattended automation as native AI Act evidence: the standing mandate becomes an Art. 14 oversight record, the routine enters the Art. 6 risk register, every pause-and-ask is tracked to its answer."
---

# Scheduled routines — the oversight that runs at 3am

[`laravel-routines`](https://github.com/padosoft/laravel-routines) runs automations **when nobody
is there**. That is the whole difficulty: Art. 14 asks for effective human oversight of an AI
system, and an unattended routine is, by definition, a system nobody is watching at the moment it
acts.

The package answers that with a **standing mandate** — a human decides, in advance and bound to
the digest of an approved payload, what the routine may do on its own; anything outside it makes
the routine stop and ask. This bridge (opt-in) turns those facts into compliance records with no
manual bookkeeping:

| Routine event | Compliance record |
| --- | --- |
| Mandate granted | `HumanReview` in state `approved` — subject `routine_mandate`, reviewer = the routine owner, notes carrying action classes, target, **payload digest**, budget ceiling, expiry and the consent evidence (confirmation id + AAL) |
| Mandate granted (same event) | `RiskRegisterEntry` — name `Unattended routine: NAME [rt_…]`, `article_refs` `["AI Act Art. 6", "AI Act Art. 14"]`, description with trigger, schedule, timezone and authorised action classes |
| Run paused, awaiting a human | `HumanReview` in state `pending` — subject `routine_run`, carrying the action class that fell outside the mandate and the question as the owner will read it |
| Human answered | That review becomes `approved` or `rejected`, with **who** answered and the reason |
| Routine suspended (budget exhausted, target gone, or a [rebel-ai-guard](https://github.com/padosoft/laravel-rebel-ai-guard) anomaly) | Entry status → `mitigating`, reason appended |

## Turning it on

```php
// config/ai-act-compliance.php
'routines' => ['enabled' => true, 'default_risk_category' => 'limited'],
```

Or `AI_ACT_ROUTINES_ENABLED=true`. Like every bridge here it is **off by default**: it writes
records, and an application should say yes before another package starts writing.

`default_risk_category` (`low|limited|high|unacceptable`) is a default, not a verdict — whether an
unattended routine is high-risk depends on the **domain** it acts in, which only the host
application knows. Re-classify individual entries in the register.

## Why the mandate is the Art. 14 evidence

Interactive consent is a person approving **one** action, with the action in front of them.
A standing mandate is a person approving a **class** of actions in advance, for a system that will
act while they sleep. That is a larger decision, not a smaller one — which is exactly why it has to
be recorded rather than assumed.

The record carries the **payload digest**, and that is the part that makes it verifiable later: the
consent was given for *that* configuration. Change the payload and the mandate stops covering it —
the routine will ask again rather than keep acting on a consent that no longer describes what it
does. A reviewer reading the record can reconstruct what the person actually said yes to.

An empty mandate is recorded in words, not as a blank field: *"Action classes: none — the mandate
authorises nothing (fail-closed)."* A reviewer who sees an empty list reads a missing value; a
reviewer who reads that sentence knows the system is behaving correctly.

## The pause is oversight happening, not oversight failing

When a routine meets something its mandate does not cover, it **stops and asks**. The bridge opens
a `pending` review at that instant, because at that instant the decision genuinely is outstanding.

This is also the one oversight item that can rot. A pending review nobody answers is a routine
frozen forever, and — because the routine is behaving exactly as designed — it produces no error
anywhere. The tracker's *outstanding oversight* count is where that becomes visible, and
`rebel-ai-guard` watches the same condition from the other side as
[`routine_approval_starvation`](https://github.com/padosoft/laravel-rebel-ai-guard).

Reviews are keyed by **run**, not by routine: the same routine pauses many times, and each pause is
its own question with its own answer.

## Approved ≠ succeeded

The bridge listens to `RoutineResolved` — the human's answer — and not to the run finishing. On
approval the work resumes and ends later, sometimes much later, sometimes failing. *"The human said
yes"* and *"the work succeeded"* are different facts about different moments, and an oversight
ledger that conflates them cannot answer the question it exists for.

So an approved record says *"the run resumed from where it stopped"*, and nothing about the
outcome. The outcome lives in the routine's own ledger.

## What the bridge does not do

It **records decisions, it never makes them**. Enforcement — the mandate check, the pause, the
budget ceiling, the delegated identity — lives in `laravel-routines`. This package is the
compliance ledger downstream of it. If the bridge is off, the routine behaves identically; you
simply have no AI Act record of what it did.
