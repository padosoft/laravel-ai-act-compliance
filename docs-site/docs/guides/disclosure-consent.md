---
title: Disclosure and Consent
description: Make AI involvement visible and record user consent decisions.
---

# Disclosure and Consent

Disclosure covers AI transparency. Consent records capture user choices, revocations, and policy provenance.

## Disclosure middleware

Use `ai-act.disclosure` on routes that render or return AI-assisted outputs. Use `@aiDisclosure` in Blade templates when the disclosure must be visually placed near the feature.

## Consent middleware

Use `ai-act.consent` to require an active consent record before a route proceeds.

```php
Route::middleware(['auth', 'ai-act.consent'])->post('/assistant/message', StoreMessageController::class);
```

## Consent service

```php
app('ai-act.consent')->grant($user, [
    'purpose' => 'ai_assistant',
    'policy_version' => '2026-06',
]);

app('ai-act.consent')->revoke($user, [
    'purpose' => 'ai_assistant',
]);
```

::: callout warning "Revocation semantics"
Revocation should stop future processing for the purpose. It does not automatically delete historical records that must be retained for legal obligations.
:::
