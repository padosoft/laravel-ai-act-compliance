---
title: Risk Register
description: Classify AI use cases and keep Article 6 evidence current.
---

# Risk Register

The risk register maps each AI use case to a category and owner. Treat it as the operational source for AI Act scope decisions.

::: steps
1. Name the AI use case in business language.
2. Assign an owner who can approve changes.
3. Classify risk as unacceptable, high, limited, or low.
4. Attach Annex III context for high-risk systems.
5. Review after model, data, user population, or jurisdiction changes.
:::

## Suggested fields

| Field | Why it matters |
| --- | --- |
| `name` | Human-readable audit anchor |
| `risk_category` | AI Act classification |
| `annex_iii_area` | High-risk mapping |
| `owner` | Accountable role |
| `mitigations` | Control summary |

::: collapsible "Classification notes"
Unacceptable risk should block release. High risk needs governance, monitoring, logs, human oversight, and technical documentation. Limited risk often centers on transparency obligations.
:::
