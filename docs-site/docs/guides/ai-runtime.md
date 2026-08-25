---
title: "AI Runtime Bridge"
description: "Turning laravel/ai run events into Art. 14 oversight records and Art. 15 incidents."
---

# AI Runtime Bridge

The [IAM delegation bridge](/guides/iam-delegation) records that a human approved
what an agent **may** do. This records what happened when it actually did it.

::: callout info
Needs `laravel/ai` **^0.11** and `ai-act-compliance.ai_runtime.enabled`. Both
gates are checked, so an application without the SDK never loads a line of it.
:::

## Art. 14 — the per-action confirmation

A delegation grant is oversight at the level of *authority*. An auditor asking
about a specific action — money moved, a record changed, a message sent — wants
the confirmation for **that** action.

`laravel/ai` emits one when a tool is gated on approval:

| Event | What this package records |
|---|---|
| `ToolApprovalRequested` | A `HumanReview` in state **`pending`**, subject `ai_tool_approval`, keyed by the tool call id, with the tool name, the run, the reason the agent gave and (opt-out) the arguments |
| `ToolApprovalResolved` | The same review closed as **`approved`** or **`rejected`**, from `ToolResult::$denied` |

The record is created `pending` deliberately. Unlike a grant — whose consent has
already happened by the time the event fires — an approval request is a question
with **no answer yet**; recording it as approved would document a decision nobody
had made.

And it is always closed. A pending review that is never resolved is worse than no
review at all: the tracker shows a decision permanently outstanding for an action
that was settled minutes later, and every "outstanding oversight" count is wrong
from that moment on.

::: callout tip
"The human said no" and "the tool failed" are different facts about the same
action, and the record says which. A denied call never ran.
:::

## Art. 15 — failures that describe themselves

| Event | Incident |
|---|---|
| `AgentFailed` | **medium** — the run terminated without producing a response. Carries the run id, the model and the exception class |
| `ToolFailed` | **low**, or **medium** past `slow_tool_ms` — carries the tool, the run, the exception, and how long it ran first |

`AgentFailed` fires only when the failure is **terminal**: a failover that still
has a provider left to try never reaches it, so a blip the SDK recovered from
does not open a ticket.

The duration is why the severity can differ. A tool that threw after nine seconds
looks like an upstream timeout — the kind of failure that repeats and spreads —
while one that threw in ten milliseconds is a rejection. Same exception class,
different incident.

## Privacy

Tool arguments and exception messages are the most likely place for personal data
to appear in these records, so both are **opt-out** and both are length-bounded:

```php
'ai_runtime' => [
    'enabled' => env('AI_ACT_AI_RUNTIME_ENABLED', false),

    'capture_tool_arguments' => env('AI_ACT_AI_RUNTIME_TOOL_ARGS', true),
    'tool_argument_limit' => env('AI_ACT_AI_RUNTIME_TOOL_ARG_LIMIT', 500),
    'capture_error_messages' => env('AI_ACT_AI_RUNTIME_ERROR_MESSAGES', true),
    'error_message_limit' => env('AI_ACT_AI_RUNTIME_ERROR_LIMIT', 500),

    'slow_tool_ms' => env('AI_ACT_AI_RUNTIME_SLOW_TOOL_MS', 5000),
],
```

With capture off the record still names the tool, the run and the exception
class — enough to investigate, without the payload.

## Reading it back

The records land in the existing Human Oversight tracker and Incident Manager, so
nothing new has to be installed to query them. But the fields that matter here —
which run, which tool, refused or crashed — are written as **prose**, because
`HumanReview` is shared with every other oversight source and has no column for
"tool call". Prose is right for an auditor and wrong for a table.

[`laravel-ai-act-compliance-admin`](https://github.com/padosoft/laravel-ai-act-compliance-admin)
≥ 1.2 reads those sentences back into columns:

- an **Outcome** column that says *denied — the tool did not run* or *approved —
  the tool ran*, rather than leaving `rejected` to mean either that or a stale
  record;
- a **Run** column that pivots the whole trail to a single invocation;
- the call behind the decision in the drawer — agent, tool, tool-call id, run,
  conversation — with the model's stated reason labelled as a **claim**, since it
  is text an untrusted component wrote about its own request.

The run id is the same `invocation_id`
[`laravel-ai-finops`](https://github.com/padosoft/laravel-ai-finops) records on
its run events and
[`laravel-iam-agents`](https://github.com/padosoft/laravel-iam-agents) stamps on
the delegation audit. One id joins the human decision, the spend and the
delegation across three panels.
