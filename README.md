<h1 align="center">laravel-ai-act-compliance</h1>

<p align="center">
  <b>The first Laravel-native toolkit for EU AI Act + GDPR compliance.</b><br/>
  Plug it into any Laravel AI app. Audit-ready out of the box.
</p>

<p align="center">
  <a href="https://doc.laravel-ai-act-compliance.padosoft.com"><b>Official documentation</b></a>
</p>
<p align="center">
  <a href="https://packagist.org/packages/padosoft/laravel-ai-act-compliance"><img src="https://img.shields.io/packagist/v/padosoft/laravel-ai-act-compliance.svg?style=flat-square&color=blueviolet" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/padosoft/laravel-ai-act-compliance"><img src="https://img.shields.io/packagist/dt/padosoft/laravel-ai-act-compliance.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://github.com/padosoft/laravel-ai-act-compliance/actions"><img src="https://img.shields.io/github/actions/workflow/status/padosoft/laravel-ai-act-compliance/tests.yml?branch=main&style=flat-square&label=CI" alt="CI"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License"></a>
  <a href="#prerequisites"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+"></a>
  <a href="#prerequisites"><img src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12/13"></a>
  <a href="#-ai-act--gdpr-mapping"><img src="https://img.shields.io/badge/EU%20AI%20Act-compliant-0c4a6e?style=flat-square" alt="EU AI Act compliant"></a>
  <a href="#-ai-act--gdpr-mapping"><img src="https://img.shields.io/badge/GDPR-Art.%2015%2F17%2F30-0c4a6e?style=flat-square" alt="GDPR"></a>
  <a href="#-ai-vibe-coding-pack-included"><img src="https://img.shields.io/badge/🚀-AI%20vibe--coding%20pack-yellow?style=flat-square" alt="AI vibe-coding pack"></a>
</p>

<p align="center">
  <a href="#-why-this-exists">Why</a> ·
  <a href="#-features-at-a-glance">Features</a> ·
  <a href="#-killer-modules">Killer modules</a> ·
  <a href="#-quick-start-jr-proof-5-minutes">Quick start</a> ·
  <a href="#-ai-act--gdpr-mapping">AI Act mapping</a> ·
  <a href="#-architecture">Architecture</a> ·
  <a href="#-host-contracts">Host contracts</a> ·
  <a href="#-extension-points">Extend</a> ·
  <a href="#-testing">Testing</a> ·
  <a href="#-ai-vibe-coding-pack-included">Vibe-coding pack</a>
</p>

---

## 🚀 AI vibe-coding pack included

Every `padosoft/*` package ships with a `.claude/` directory containing:

- **Skills** (`.claude/skills/`) — pre-loaded by Claude Code when trigger conditions match. The compliance package skills know how to wire DSAR contracts, register cohort metrics, gate consent middleware, and persist incident state transitions.
- **Agents** (`.claude/agents/`) — `compliance-reviewer` checks DSAR delete cascades + bias drift thresholds + state-machine transition coverage before you push.
- **Rules** (`.claude/rules/`) — codified review rules distilled from real Copilot findings (escape DSAR LIKE input, never log DSAR subject email at INFO, always audit-trail consent revocations).

Just `composer require padosoft/laravel-ai-act-compliance` and the pack is auto-discovered when you open the project in Claude Code. **No setup required.** If you don't use Claude Code, the pack is invisible — it never affects runtime behaviour.

---

## 📖 Table of contents

- [Why this exists](#-why-this-exists)
- [Features at a glance](#-features-at-a-glance)
- [Killer modules](#-killer-modules)
- [Quick start (jr-proof, 5 minutes)](#-quick-start-jr-proof-5-minutes)
- [Configuration](#-configuration)
- [AI Act + GDPR mapping](#-ai-act--gdpr-mapping)
- [Architecture](#-architecture)
- [Host contracts](#-host-contracts)
- [Modules in detail](#-modules-in-detail)
- [HTTP API surface](#-http-api-surface)
- [Extension points](#-extension-points)
- [Testing](#-testing)
- [Companion package: admin SPA](#-companion-package-admin-spa)
- [Roadmap](#-roadmap)
- [Changelog](#-changelog)
- [Contributing](#-contributing)
- [Security](#-security)
- [Credits](#-credits)
- [License](#-license)

---

## 🎯 Why this exists

> The EU AI Act enters full force in 2026–2027. Python has Lakera Guard, Fairlearn, Aequitas. **Laravel has nothing.**

If you ship a Laravel app that uses an LLM, you need:

- **Disclosure** to end users (AI Act Art. 50)
- A **risk register** that maps each use case to AI Act categories (Art. 6 + Annex III)
- **DSAR** (Data Subject Access Requests) per GDPR Art. 15 / 16 / 17 with 30-day SLA tracking
- **Bias monitoring** with cohort parity + drift (Art. 10 + Art. 15)
- **Human review tracking** with a state machine (Art. 14)
- **Incident management** with escalation routing (Art. 73)
- **Consent** ledger with revocation timeline (GDPR Art. 7)
- **Cybersecurity** middleware (rate limit, session anomaly, 2FA helper)
- **Compliance attestation** PDF generator for auditors (Art. 11 + Art. 30)

You can build all of this yourself in 2-3 months, or you can `composer require padosoft/laravel-ai-act-compliance` and ship next week.

### Who's this for

| You | This package |
|-----|--------------|
| Building a Laravel SaaS that uses GPT / Claude / Gemini | ✅ Yes |
| Adding a chat agent to an enterprise Laravel app | ✅ Yes |
| Operating in the EU, EEA, UK, Switzerland | ✅ Yes |
| Selling to enterprise customers asking for SOC 2 / ISO 27001 / ISO 42001 | ✅ Yes |
| Already shipped a Laravel AI feature without a compliance plan | ✅ Yes — install yesterday |
| Pure backoffice CRUD with no AI | ❌ Not your problem (yet) |

### Comparable products

| Product | Stack | Open source | Scope |
|---------|-------|-------------|-------|
| Lakera Guard | Python | No (SaaS) | Guardrails + PII |
| Fairlearn | Python | Yes | Fairness metrics only |
| Aequitas | Python | Yes | Bias audit only |
| AWS Audit Manager | AWS-only | No | Generic compliance, not AI-specific |
| **`padosoft/laravel-ai-act-compliance`** | **Laravel/PHP** | **MIT** | **Full AI Act + GDPR stack** |

---

## ✨ Features at a glance

| Module | What it does | Article |
|--------|--------------|---------|
| **Disclosure** | `@aiDisclosure` Blade directive + `ai-act.disclosure` middleware injects an "I'm AI" banner per AI Act Art. 50 | AI Act Art. 50 |
| **Risk Register** | CRUD on AI use cases tagged with risk category (`unacceptable` / `high` / `limited` / `low`) + Annex III mapping | AI Act Art. 6 + Annex III |
| **DSAR** | Queue + service + `ExportUserDataJob` / `DeleteUserDataJob` + 30-day SLA tracking + breach escalation | GDPR Art. 15 / 16 / 17 |
| **BiasMonitoring** | `CohortParityMetric` contract + `BiasMonitorService` + `BiasSnapshot` storage + drift detection | AI Act Art. 10 + Art. 15 |
| **HumanReviewTracker** | Decision approval queue with state machine (pending / approved / rejected / escalated) | AI Act Art. 14 |
| **Incident** | Ticket model + state transitions + severity routing + escalation tree (CISO / DPO / CEO / Legal) | AI Act Art. 73 |
| **Consent** | Polymorphic `ConsentRecord` + `ai-act.consent` middleware + revocation timeline | GDPR Art. 7 |
| **Cybersecurity** | Per-user rate limit, session anomaly detection, 2FA helper | AI Act Art. 15 |
| **ComplianceAttestation** | Auditor-ready PDF generator (Article 30 records of processing) | AI Act Art. 11 + GDPR Art. 30 |
| **BiasMonitoring v1.2** | Pluggable `CohortParityMetric` registry: `DemographicParityMetric` (default), `EqualizedOddsMetric`, `CalibrationMetric` — `metric_name` + `metric_version` + `article_evidence_json` persisted per snapshot | AI Act Art. 10 + Art. 15 |
| **Alerting v1.3** | Real-time cohort-drift cascade: `alert_routes` (Crypt-encrypted webhooks) → Slack → Discord → always-CC email; throttle + circuit breaker + severity-escalation bypass | AI Act Art. 9 |
| **RegulatoryFeed v1.4** | EU AI Act amendment auto-flagger: RSS 2.0 + Atom 1.0 (XXE-safe), `ImpactedClauseDetector` config-driven regex map, `RegulatoryFeedPoller` + `ai-act:regulatory-poll` Artisan command, per-tenant idempotency | AI Act Art. 9 / 50 |
| **MultiTenancy v1.5** | First-class `tenants` registry (slug-unique, tier + status enums, config_overrides_json), request-scoped `TenantContext`, `TenantConfigResolver`, `ai-act.tenant-context` middleware (404 / 423 / 410 / pass-through), `CrossTenantOverviewService` (no-N+1 `GROUP BY tenant_id`) | AI Act Art. 9 + GDPR Art. 30 |

Every module is **config-gated** (default safe) + **migration-published** + **tested**.

---

## 💎 Killer modules

These three are what make the package WOW:

### 1. DSAR queue that handles the regulatory ugliness for you

You implement two contracts:

```php
class MyAppExporter implements \Padosoft\AiActCompliance\DSAR\Contracts\UserDataExporter
{
    public function export(\App\Models\User $user): array {
        return [
            'profile' => $user->only(['id', 'name', 'email']),
            'orders' => $user->orders()->get()->toArray(),
            'chats' => $user->chats()->withTrashed()->get()->toArray(),
        ];
    }
}

class MyAppDeleter implements \Padosoft\AiActCompliance\DSAR\Contracts\UserDataDeleter
{
    public function delete(\App\Models\User $user, array $scope): void {
        $user->orders()->delete();
        $user->chats()->forceDelete();
        $user->delete();
    }
}
```

The package handles **everything else**:

- Identity verification (SPID / OAuth / email link)
- 30-day SLA tracking + automatic warning at SLA - 5 days + breach escalation
- ZIP packaging + signed download URL
- Audit trail (immutable `dsar_audit` rows)
- Notification cascade (email + Slack webhook)
- Article reference annotations on every DSAR

### 2. Cohort-parity bias monitoring

```php
class RefusalRateMetric implements \Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric
{
    public function compute(array $context = []): array {
        // Your domain logic: count refusals per cohort in $context['window_days']
        return [
            'cohort' => $context['cohort'],
            'score' => 1 - ($refusals / $total),
            'delta' => $baseline - (1 - $refusals / $total),
            'flagged' => /* delta > threshold */,
        ];
    }
}

// In your AppServiceProvider:
app('ai-act.bias')->register('refusal_rate', RefusalRateMetric::class);
```

`BiasMonitorService` then snapshots the metric on a schedule, alerts on drift > 0.05, and feeds the result to the admin SPA Bias Monitor screen — **no chart code to write**.

### 3. Incident manager with state-machine + escalation routing

```php
$ticket = app('ai-act.incidents')->open([
    'title' => 'Hallucination on legal queries (IT cohort)',
    'severity' => IncidentSeverity::High,
    'affected_users' => $userIds,
    'articles' => ['AI Act Art. 14', 'AI Act Art. 15'],
]);

app('ai-act.incidents')->transition($ticket, IncidentStatus::Triage);
app('ai-act.incidents')->transition($ticket, IncidentStatus::Mitigating, [
    'mitigation' => 'Deployed v2.4.2 with extended IBAN regex.',
]);
```

State transitions are **immutable, audit-trailed, and validated**. Escalation routing (CISO → DPO → CEO) fires automatically based on `severity` × configured policy.

### 4. Real-time alerting cascade (v1.3)

```php
// Default OFF. Enable + seed an alert_routes row + you're done.
config(['ai-act-compliance.alerting.enabled' => true]);

AlertRoute::query()->create([
    'tenant_id' => 'acme',
    'channel' => 'slack',
    'webhook_url' => 'https://hooks.slack.com/services/...',  // auto-encrypted at rest
    'enabled' => true,
]);

// Whenever BiasMonitorService::capture() ingests a drift snapshot
// above the configured threshold, the queued listener fans out:
//   Slack → Discord → ALWAYS email (audit trail)
```

What you get for free:
- **Cascade-level throttle pre-check** — a previously-delivered Slack alert for `(tenant, cohort)` ends the cascade so it never silently slides through to Discord.
- **Severity-escalation bypass** — a `low` inside the cooldown window never suppresses a subsequent `critical`. Art. 9 requires it.
- **Per-channel circuit breaker** that trips after N consecutive failures and self-resets on natural cooldown elapse.
- **Email cascade is exempt from the throttle** because it's the auditable backup trail — every drift event is recorded.

### 5. Regulatory-feed auto-flagger (v1.4)

```bash
# Schedule it daily — defaults OFF; opt in via AI_ACT_REGULATORY_FEED_ENABLED=true.
php artisan ai-act:regulatory-poll
```

What lands on the DPO desk:
- Every new EU AI Act amendment ingested as a `regulatory_amendments` row with the impacted article clauses pre-detected (`AI Act Art. 5` → critical, `Art. 10 / 14 / 15 / 27` → high, etc.).
- Case-insensitive regex map accepts plural `Articles 5 and 9` / `Arts. 9-15`.
- XXE-safe parser (`LIBXML_NONET` blocks network access; we deliberately do NOT pass `LIBXML_NOENT`).
- Per-tenant composite UNIQUE `(tenant_id, source_driver, external_id)` — concurrent polls converge cleanly, no duplicate rows.
- `RegulatoryAmendmentDetected` event with `SerializesModels` so downstream listeners persist a model id, not the full payload.

### 6. DPO multi-org tenant management (v1.5)

```php
Tenant::query()->create([
    'slug' => 'acme',                           // unique 50-char id
    'name' => 'Acme Inc.',
    'subscription_tier' => 'enterprise',
    'dpo_email' => 'dpo@acme.example',
    'config_overrides_json' => [
        // Per-tenant override of ANY ai-act-compliance.* key
        'bias.disparity_threshold' => 0.02,
    ],
]);

// Mount the middleware on whatever route group you serve to operators:
Route::middleware('ai-act.tenant-context')->group(function () {
    Route::get('/api/admin/ai-act-compliance/...', ...);
});

// Every package service reads the active tenant via:
$current = app(TenantContext::class)->current();
$threshold = app(TenantConfigResolver::class)->resolve('bias.disparity_threshold', 0.05);
```

Operationally:
- Request-scoped binding via `$this->app->scoped(TenantContext::class)` — Octane-safe.
- `X-Tenant-Id` header (or `?tenant=` query) resolves the slug; unknown → 404, suspended → 423 Locked, archived → 410 Gone.
- `CrossTenantOverviewService` aggregates platform-wide KPIs in one `GROUP BY tenant_id` query per table — no N+1 as tenant count grows.

---

## ⚡ Quick start (jr-proof, 5 minutes)

> Even if you've never installed a Laravel package before, you'll be running by the end of this section.

### 0. Prerequisites

You need:

- **PHP 8.2+** — run `php -v` and confirm
- **Laravel 12 or 13** in your project — `php artisan --version`
- **A database** — MySQL / PostgreSQL / SQLite all work
- **Composer** — `composer --version`

If any of these are missing, install them first. We'll wait. ☕

### 1. Install the package

```bash
composer require padosoft/laravel-ai-act-compliance
```

That's it for installation. The Laravel auto-discovery wires the service provider for you.

### 2. Publish the migrations + config

```bash
php artisan vendor:publish --tag=ai-act-compliance-migrations
php artisan vendor:publish --tag=ai-act-compliance-config
```

You should see new files appear under `database/migrations/` (8 new migrations) and `config/ai-act-compliance.php`.

### 3. Run the migrations

```bash
php artisan migrate
```

Verify the tables landed:

```bash
php artisan tinker
>>> \Padosoft\AiActCompliance\DSAR\Models\DsarRequest::query()->count();
=> 0
>>> exit
```

If you see `=> 0` (not an error), you're golden.

### 4. Implement the two host contracts

Create `app/Compliance/MyAppUserDataExporter.php`:

```php
<?php

namespace App\Compliance;

use App\Models\User;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataExporter;

class MyAppUserDataExporter implements UserDataExporter
{
    public function export(User $user): array
    {
        return [
            // List EVERY domain table that holds data for this user.
            // The package will ZIP this and ship to the DSAR delivery URL.
            'profile' => $user->only(['id', 'name', 'email', 'created_at']),
            'orders' => $user->orders()->get()->toArray(),
            'chats' => $user->chats()->get()->toArray(),
            // Add every relation you persist for users.
        ];
    }
}
```

Create `app/Compliance/MyAppUserDataDeleter.php`:

```php
<?php

namespace App\Compliance;

use App\Models\User;
use Padosoft\AiActCompliance\DSAR\Contracts\UserDataDeleter;

class MyAppUserDataDeleter implements UserDataDeleter
{
    public function delete(User $user, array $scope): void
    {
        // Cascade delete EVERY domain table. The package handles the
        // audit trail and the SLA tracking; you handle the actual rows.
        $user->orders()->delete();
        $user->chats()->forceDelete();
        $user->delete();
    }
}
```

### 5. Bind the contracts in your service provider

Open `app/Providers/AppServiceProvider.php` and add to `register()`:

```php
public function register(): void
{
    $this->app->bind(
        \Padosoft\AiActCompliance\DSAR\Contracts\UserDataExporter::class,
        \App\Compliance\MyAppUserDataExporter::class,
    );
    $this->app->bind(
        \Padosoft\AiActCompliance\DSAR\Contracts\UserDataDeleter::class,
        \App\Compliance\MyAppUserDataDeleter::class,
    );
}
```

### 6. Add the disclosure middleware (if you have an AI chat surface)

In `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'ai-act.disclosure' => \Padosoft\AiActCompliance\Disclosure\AiDisclosureMiddleware::class,
    ]);
})
```

Then on any route group that renders an AI response:

```php
Route::middleware('ai-act.disclosure')->group(function () {
    Route::post('/chat', [ChatController::class, 'send']);
});
```

### 7. Smoke-test it

```bash
php artisan tinker
>>> $request = \Padosoft\AiActCompliance\DSAR\Models\DsarRequest::create([
...     'subject_email' => 'test@example.com',
...     'type' => 'export',
...     'status' => 'pending',
... ]);
>>> $request->id;
=> 1
>>> exit
```

If the DSAR row landed, **you're compliant-ready**.

### 8. (Optional) Install the admin SPA companion

```bash
composer require padosoft/laravel-ai-act-compliance-admin
php artisan vendor:publish --tag=ai-act-compliance-admin-assets
```

Then visit `/admin/ai-act-compliance` — the full 8-screen React SPA (Overview / DSAR / Consent / Risks / Incidents / Bias / DPO / Settings) renders behind your Laravel auth.

See [`padosoft/laravel-ai-act-compliance-admin`](https://github.com/padosoft/laravel-ai-act-compliance-admin) for screenshots and a complete tour.

---

## ⚙️ Configuration

Every knob lives in `config/ai-act-compliance.php`. The defaults are intentionally safe-by-default; nothing fires unless you explicitly enable it.

```php
return [
    'disclosure' => [
        'enabled' => env('AICOMPLIANCE_DISCLOSURE_ENABLED', true),
        'message' => env('AICOMPLIANCE_DISCLOSURE_MESSAGE', 'You are chatting with an AI assistant. Responses may be inaccurate.'),
    ],

    'dsar' => [
        'sla_days' => env('AICOMPLIANCE_DSAR_SLA_DAYS', 30),
        'warn_days' => env('AICOMPLIANCE_DSAR_WARN_DAYS', 5),
        'notify_emails' => array_filter(explode(',', env('AICOMPLIANCE_DSAR_NOTIFY', ''))),
    ],

    'bias' => [
        'enabled' => env('AICOMPLIANCE_BIAS_ENABLED', true),
        'baseline_parity' => env('AICOMPLIANCE_BIAS_BASELINE_PARITY', 0.95),
        'drift_threshold' => env('AICOMPLIANCE_BIAS_DRIFT_THRESHOLD', 0.05),
        'window_days' => env('AICOMPLIANCE_BIAS_WINDOW_DAYS', 7),
    ],

    'incidents' => [
        'escalation_map' => [
            'critical' => ['ciso@example.com', 'dpo@example.com'],
            'high' => ['ciso@example.com'],
            'medium' => ['eng-lead@example.com'],
            'low' => [],
        ],
    ],

    'consent' => [
        'features' => [
            // Declare per-feature consent flags here.
        ],
    ],

    'cybersecurity' => [
        'rate_limit_per_user' => env('AICOMPLIANCE_RATE_LIMIT_PER_USER', '60,1'),
        'session_anomaly_strict' => env('AICOMPLIANCE_SESSION_ANOMALY_STRICT', false),
    ],

    'attestation' => [
        'signer' => env('AICOMPLIANCE_ATTESTATION_SIGNER', 'DPO <dpo@example.com>'),
    ],
];
```

---

## 📜 AI Act + GDPR mapping

Every module maps explicitly to an article. This is the audit-trail your DPO + auditor will love.

| Article | Title | Module |
|---------|-------|--------|
| AI Act Art. 5 | Prohibited AI practices | `RiskRegister` (category=`unacceptable`) |
| AI Act Art. 6 | High-risk AI systems | `RiskRegister` (category=`high`) |
| AI Act Art. 10 | Data and data governance | `BiasMonitoring` |
| AI Act Art. 11 | Technical documentation | `ComplianceAttestation` |
| AI Act Art. 12 | Logging | (host responsibility — package provides audit hooks) |
| AI Act Art. 14 | Human oversight | `HumanReviewTracker` |
| AI Act Art. 15 | Accuracy + robustness | `BiasMonitoring` + `Cybersecurity` |
| AI Act Art. 50 | Disclosure of AI-generated content | `Disclosure` middleware + Blade directive |
| AI Act Art. 73 | Serious incident notification | `Incident` |
| AI Act Annex III | High-risk use cases | `RiskRegister` categorisation |
| GDPR Art. 7 | Conditions for consent | `Consent` |
| GDPR Art. 15 | Right of access | `DSAR` (type=`export`) |
| GDPR Art. 16 | Right to rectification | `DSAR` (type=`rectify`) |
| GDPR Art. 17 | Right to erasure | `DSAR` (type=`delete`) |
| GDPR Art. 30 | Records of processing | `ComplianceAttestation` |
| GDPR Art. 32 | Security of processing | `Cybersecurity` |
| GDPR Art. 33 | Breach notification | `Incident` (severity=`critical`) |
| ISO 42001 §6.2 | AI risk management | `RiskRegister` + `BiasMonitoring` |
| ISO 27001 / SOC 2 | Information security | `Cybersecurity` + `Incident` |

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Your Laravel app                                                       │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │  Routes / Controllers / Jobs                                       │ │
│  │     │                                                              │ │
│  │     ├─ middleware('ai-act.disclosure')                             │ │
│  │     ├─ middleware('ai-act.consent:feature_id')                     │ │
│  │     │                                                              │ │
│  │     └─ resolves: UserDataExporter / UserDataDeleter contracts      │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                  │                                       │
│                                  ▼                                       │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │  padosoft/laravel-ai-act-compliance                                │ │
│  │                                                                    │ │
│  │  Disclosure    RiskRegister    DSAR    BiasMonitoring              │ │
│  │       │             │           │            │                     │ │
│  │  HumanReview    Incident   Consent   Cybersecurity                 │ │
│  │       │             │           │            │                     │ │
│  │                ComplianceAttestation                               │ │
│  │                                                                    │ │
│  │  Services + Models + Migrations + Routes + Middleware              │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                  │                                       │
│                                  ▼                                       │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │  Your database                                                     │ │
│  │  (8 published tables: dsar_requests / risk_register_entries / ...) │ │
│  └────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

The package **never owns your domain data**. It owns the compliance ledger (DSAR queue, risk register, incident tickets, consent records, bias snapshots, attestations) and the audit trail. Your domain models stay untouched — you just implement the two `UserDataExporter` / `UserDataDeleter` contracts to tell the package how to walk your tables.

---

## 📐 Host contracts

Two contracts only. Both live under `Padosoft\AiActCompliance\DSAR\Contracts`.

```php
interface UserDataExporter
{
    /**
     * Return a serializable array of ALL data the host stores for this user.
     * The package handles ZIP packaging, signed URL delivery, audit trail.
     */
    public function export(\Illuminate\Foundation\Auth\User $user): array;
}

interface UserDataDeleter
{
    /**
     * Cascade-delete EVERY row referencing this user across the host's
     * domain. The package handles the DSAR queue state transition + audit.
     *
     * @param array<string, mixed> $scope Optional scope from the DSAR
     *   request payload (e.g. {"keep_invoices": true}).
     */
    public function delete(\Illuminate\Foundation\Auth\User $user, array $scope = []): void;
}
```

A third optional contract — `Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortParityMetric` — lets you plug arbitrary bias metrics into the monitor.

---

## 📚 Modules in detail

### Disclosure

- **Middleware:** `ai-act.disclosure` — injects an `X-AI-Disclosure` response header + appends a disclosure footer to JSON / HTML responses.
- **Blade directive:** `@aiDisclosure` — renders the configured message inline.
- **Locales:** EN + IT shipped; publish + override for others.

### Risk Register

- Models: `RiskRegisterEntry` (status, category, owner, mitigation, articles).
- Service: `RiskRegisterService` with `add()`, `update()`, `close()`, `byCategory()`.
- Enum: `AiActRiskCategory` (`unacceptable` / `high` / `limited` / `low`) — directly maps to AI Act Art. 5 / 6 / 50 + Annex III.
- Controller: `RiskRegisterController` with full CRUD + filter by category / status.

### DSAR

- Models: `DsarRequest` (subject, type, status, opened_at, due_at, articles, assignee).
- Enums: `DsarType` (`export` / `delete` / `rectify`) + `DsarStatus` (`pending` / `in_progress` / `completed` / `rejected`).
- Service: `DsarService` with `open()`, `assign()`, `complete()`, `reject()`, `breachWarning()`.
- Jobs: `ExportUserDataJob` + `DeleteUserDataJob` — both invoke the host contracts.
- Controller: `DsarController` with queue + detail + actions + bulk + CSV export.

### Bias Monitoring

- Contract: `CohortParityMetric` (host or 3rd-party implements) + the `NamedCohortMetric` extension (v1.2) for self-naming metrics.
- Service: `BiasMonitorService` — runs the registered metrics on a schedule, snapshots them into `BiasSnapshot`, fires `BiasDriftDetected` events on drift.
- **v1.2 reference metrics** under `BiasMonitoring\Metrics\`:
  - `DemographicParityMetric` (`metric_name='demographic_parity'`, references AI Act Art. 10)
  - `EqualizedOddsMetric` (max of TPR-spread and FPR-spread; references AI Act Art. 10 + Art. 15)
  - `CalibrationMetric` (per-cohort calibration gap with 3-branch CI; references AI Act Art. 15)
- **v1.2 registry**: `MetricRegistry::register($key, $fqcn)` validates the FQCN at boot (R23) and rejects overlapping `supports()` predicates.
- Model: `BiasSnapshot` (metric_name, metric_version, cohort, cohort_dimension, score, delta, disparity_score, article_evidence_json, computed_at).
- Eval-harness integration: register your metric in the manifest, the harness will run it on every batch.

### Alerting (v1.3)

- Models: `AlertRoute` (at-rest-encrypted webhook URLs via `Crypt::encryptString`) + `AlertDispatch` (immutable audit trail row per attempt).
- Channels: `SlackWebhookChannel` (200 success) + `DiscordWebhookChannel` (204 / 2xx) + `EmailFallbackChannel` (`Mail::raw` with permanent-vs-transient Symfony `TransportException` classification).
- Services: `AlertDispatcher` (cascade orchestration with throttle pre-check) + `AlertThrottler` (per-tenant + per-cohort + per-channel cooldown window with severity-escalation bypass) + `CircuitBreaker` (clamps misconfigured `failures_to_trip=0`; resets `consecutive_failures` after natural cooldown elapses).
- Event: `BiasDriftDetected` (uses `SerializesModels` so queued listeners persist a model id, not the full payload).
- Listener: `BiasDriftDetectedListener implements ShouldQueue` (`$tries=1` so transient failures don't double-write audit rows).

### RegulatoryFeed (v1.4)

- Contract: `RegulatoryFeedDriver` (host or 3rd-party implements `fetch(array $sourceConfig): array<RegulatoryFeedEntry>`).
- Reference driver: `RssRegulatoryFeedDriver` — RSS 2.0 + Atom 1.0, XXE-safe (`LIBXML_NONET` blocks network access), Atom `<link>` selection prefers `rel="alternate"` over `rel="self"`, `max_entries_per_poll=0` short-circuits.
- Service: `ImpactedClauseDetector` — config-driven regex map; severity ladder Art. 5 / 9 → critical; Art. 10 / 14 / 15 / 27 → high; else medium; **throws** `InvalidArgumentException` on invalid regex (no silent downgrade).
- Service: `RegulatoryFeedPoller` — orchestrator; per-driver `Throwable` isolation; bounded-column truncation (`mb_substr` to 191 / 1024 / 500); concurrent-poll race handled via `QueryException` catch on the composite UNIQUE `(tenant_id, source_driver, external_id)`.
- Event: `RegulatoryAmendmentDetected` (uses `SerializesModels`).
- Artisan: `ai-act:regulatory-poll` (skips when disabled; exit 1 on driver failure).

### MultiTenancy (v1.5)

- Model: `Tenant` (slug UNIQUE 50 chars, `subscription_tier` enum, `status` enum, `config_overrides_json`, `suspended_at` / `archived_at` first-transition audit stamps).
- Enums: `SubscriptionTier` (`free` / `team` / `enterprise`) + `TenantStatus` (`active` / `suspended` / `archived`).
- Service: `TenantContext` (bound as `$this->app->scoped()` — Octane-safe; set/get/`activate(slug)` with model caching).
- Service: `TenantConfigResolver` — flat-dotted keys (`{"bias.disparity_threshold": 0.02}`) AND nested maps; resolution order tenant override → host config → caller default.
- Service: `CrossTenantOverviewService` — aggregates per-tenant + platform totals in ONE `GROUP BY tenant_id` query per table (no N+1); `safeCount` narrows `QueryException` to cross-driver "table missing" signatures only.
- Middleware: `ai-act.tenant-context` — header / query resolve; defensive `set(null)` at start of every request; unknown slug → 404, suspended → 423, archived → 410.

### Human Review Tracker

- Model: `HumanReview` (subject, decision_payload, state, reviewer, decided_at).
- State machine: `pending` → `approved` / `rejected` / `escalated`. Backed by [`spatie/laravel-model-states`](https://github.com/spatie/laravel-model-states).
- Service: `HumanReviewService::open()`, `approve()`, `reject()`, `escalate()`.

### Incident

- Models: `IncidentTicket` (severity, status, articles, affected_users) + `IncidentStateTransition` (before, after, actor, reason).
- Enums: `IncidentSeverity` (`low` / `medium` / `high` / `critical`) + `IncidentStatus` (`open` / `triage` / `mitigating` / `closed`).
- Service: `IncidentService::open()`, `triage()`, `transition()`, `close()`.
- Escalation routing: `EscalationRouter` — fires notifications per the configured `escalation_map`.

### Consent

- Model: `ConsentRecord` (polymorphic — bind to any host entity).
- Middleware: `ai-act.consent:feature_id` — blocks the route until consent is recorded.
- Service: `ConsentService::grant()`, `revoke()`, `historyFor()`.

### Cybersecurity

- Middleware: `PerUserRateLimitMiddleware` + `SessionAnomalyDetectionMiddleware`.
- Helper: `TwoFactorHelper` — TOTP enrolment + verification.

### Compliance Attestation

- Model: `ComplianceAttestation` (generated_at, signer_id, attached_pdf_path, scope_json).
- Service: `ComplianceAttestationService::generate()` — composes the Article 30 records of processing snapshot + signs it.
- PDF generator: `AttestationPdfGenerator` (DomPDF-backed; Browsershot supported via config).

---

## 🌐 HTTP API surface

Every endpoint sits behind your host's auth middleware (Sanctum / Passport / session) and is gated by the configured policy. Routes are auto-registered if `ai-act-compliance.routes.enabled` is true.

| Verb | Path | Controller | Gate |
|------|------|-----------|------|
| `GET` | `/api/ai-act-compliance/overview` | `ComplianceOverviewController@index` | `viewCompliance` |
| `GET` | `/api/ai-act-compliance/dsar` | `DsarController@index` | `manageDsar` |
| `POST` | `/api/ai-act-compliance/dsar` | `DsarController@store` | `manageDsar` |
| `POST` | `/api/ai-act-compliance/dsar/{id}/approve` | `DsarController@approve` | `manageDsar` |
| `POST` | `/api/ai-act-compliance/dsar/{id}/reject` | `DsarController@reject` | `manageDsar` |
| `GET` | `/api/ai-act-compliance/risks` | `RiskRegisterController@index` | `manageRisks` |
| `POST` | `/api/ai-act-compliance/risks` | `RiskRegisterController@store` | `manageRisks` |
| `GET` | `/api/ai-act-compliance/incidents` | `IncidentController@index` | `manageIncidents` |
| `POST` | `/api/ai-act-compliance/incidents` | `IncidentController@store` | `manageIncidents` |
| `POST` | `/api/ai-act-compliance/incidents/{id}/transition` | `IncidentController@transition` | `manageIncidents` |
| `GET` | `/api/ai-act-compliance/consent` | `ConsentController@index` | `manageConsent` |
| `POST` | `/api/ai-act-compliance/consent/grant` | `ConsentController@grant` | (subject self-service) |
| `POST` | `/api/ai-act-compliance/consent/revoke` | `ConsentController@revoke` | (subject self-service) |
| `GET` | `/api/ai-act-compliance/bias` | `BiasController@index` | `manageBias` |
| `GET` | `/api/ai-act-compliance/human-reviews` | `HumanReviewController@index` | `manageHumanReviews` |
| `POST` | `/api/ai-act-compliance/attestation/generate` | `ComplianceAttestationController@generate` | `manageAttestation` |
| `GET` | `/api/ai-act-compliance/settings` | `SettingsController@index` | `viewSettings` |
| `GET` | `/api/ai-act-compliance/alerts/dispatches` (v1.3) | `AlertDispatchController@index` | `manageAlerts` |
| `POST` | `/api/ai-act-compliance/alerts/dispatches/{id}/retry` (v1.3) | `AlertDispatchController@retry` | `manageAlerts` |
| `GET` | `/api/ai-act-compliance/regulatory-amendments` (v1.4) | `RegulatoryAmendmentController@index` | `manageRegulatory` |
| `PATCH` | `/api/ai-act-compliance/regulatory-amendments/{id}` (v1.4) | `RegulatoryAmendmentController@update` | `manageRegulatory` |
| `POST` | `/api/ai-act-compliance/regulatory-amendments/poll` (v1.4) | `RegulatoryAmendmentController@poll` | `manageRegulatory` |
| `GET` | `/api/ai-act-compliance/tenants` (v1.5) | `TenantController@index` | `manageTenants` |
| `POST` | `/api/ai-act-compliance/tenants` (v1.5) | `TenantController@store` | `manageTenants` |
| `GET` | `/api/ai-act-compliance/tenants/{slug}` (v1.5) | `TenantController@show` | `manageTenants` |
| `PATCH` | `/api/ai-act-compliance/tenants/{slug}` (v1.5) | `TenantController@update` | `manageTenants` |

The admin SPA companion consumes this surface verbatim — your custom UI does too.

---

## 🔌 Extension points

| You want to… | Wire this |
|--------------|-----------|
| Plug in a custom bias metric | Implement `CohortParityMetric` (+ optional `NamedCohortMetric`), register via `MetricRegistry::register($name, $class)` |
| Customise DSAR ZIP packaging | Override the `ai-act-compliance.dsar.exporter` binding in your service provider |
| Add a new locale | Publish locales: `php artisan vendor:publish --tag=ai-act-compliance-locales` |
| Use Browsershot instead of DomPDF | Set `ai-act-compliance.attestation.pdf_renderer = 'browsershot'` |
| Route incidents to PagerDuty / Opsgenie | Implement `EscalationDriverInterface`, register via the config map |
| Hook into the state-machine transitions | Listen to `Padosoft\AiActCompliance\Support\ComplianceEvents` |
| Add a new alert channel (Teams, PagerDuty, …) | Implement `AlertChannel`, add to `ai-act-compliance.alerting.channels` config map |
| Override the clause-detection regex map | Set `ai-act-compliance.regulatory_feed.impacted_clause_patterns` in your config (host wins) |
| Plug in a new regulatory-feed driver (NIS2 / GDPR DPB / sector-specific) | Implement `RegulatoryFeedDriver`, add to `ai-act-compliance.regulatory_feed.drivers` |
| Override any package config per tenant | Persist `{"<dotted.key>": value}` into `tenants.config_overrides_json`; read via `TenantConfigResolver::resolve()` |

---

## 🧪 Testing

```bash
composer test           # Unit + Feature
composer test:unit      # Unit only (fast)
composer test:feature   # Feature (Orchestra Testbench)
composer test:coverage  # With coverage (requires Xdebug / PCOV)
```

### Live testsuite (opt-in)

The package ships a `tests/Live/` directory that exercises real regulatory reference systems (SPID handshake fixtures, EU AI Act API). It is **disabled by default** — CI runs Unit + Feature only.

Enable explicitly when you need it:

```bash
AICOMPLIANCE_LIVE=1 composer test:live
```

### CI matrix

GitHub Actions tests against PHP 8.3 / 8.4 / 8.5 × Laravel 12 / 13.

---

## 🎨 Companion package: admin SPA

[`padosoft/laravel-ai-act-compliance-admin`](https://github.com/padosoft/laravel-ai-act-compliance-admin) is the React 19 + TypeScript admin SPA. It cross-mounts into any Laravel app under `/admin/ai-act-compliance` and consumes the HTTP API surface above. 8 screens:

| Screen | What it does |
|--------|--------------|
| Overview | KPI tiles + activity feed + DSAR depth chart + Article 30 attestation card |
| DSAR | Filterable table + bulk actions + drawer with timeline + data scope |
| Consent | Per-feature grid + per-user matrix |
| Risks | Category summary tiles + filter sidebar + card grid + detail drawer |
| Incidents | 4-lane kanban + drawer with timeline + mitigations + escalation tree |
| Bias | Cohort parity SVG chart + drift multi-line chart + flagged samples |
| DPO | Data flow diagram + retention table + deletion log + attestation modal |
| Settings | Feature flags + env vars (with show/hide secrets) + webhook destinations |

```bash
composer require padosoft/laravel-ai-act-compliance-admin
php artisan vendor:publish --tag=ai-act-compliance-admin-assets
```

Then visit `/admin/ai-act-compliance` in your browser. Done.

---

## 🗺️ Roadmap

- [x] **v1.0** — 9 backend modules + migrations + service provider + tests
- [x] **v1.1** — Bias monitoring `CohortParityMetric` interface + extension points
- [x] **v1.2** ✅ shipped 2026-05-15 — Pluggable bias-metric strategy registry + 3 reference metrics (DemographicParity, EqualizedOdds, Calibration)
- [x] **v1.3** ✅ shipped 2026-05-15 — Cohort-drift real-time alerting (Slack + Discord + email cascade) with throttle, circuit breaker, severity-escalation bypass
- [x] **v1.4** ✅ shipped 2026-05-15 — Regulatory change auto-flagger (RSS / Atom EU AI Act amendment feed, XXE-safe, `ImpactedClauseDetector`, idempotent `RegulatoryFeedPoller`)
- [x] **v1.5** ✅ shipped 2026-05-15 — DPO multi-org tenant management (`tenants` registry, `TenantContext` request-scoped, `TenantConfigResolver`, `ai-act.tenant-context` middleware, `CrossTenantOverviewService`)
- [ ] **v2.0** — `padosoft/laravel-ai-act-compliance-enterprise` (Pro add-on) with SLA-backed regulatory updates, SOC 2 / ISO 27001 / ISO 42001 audit-letter template generator

---

## 📋 Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full release history.

Recent highlights:

- **v1.5.0** (2026-05-15) — DPO multi-org tenant management: `tenants` registry, `TenantContext` (scoped), per-tenant `TenantConfigResolver`, `ai-act.tenant-context` middleware (404 / 423 / 410), `CrossTenantOverviewService` no-N+1; 192/192 PHPUnit
- **v1.4.0** (2026-05-15) — Regulatory-feed auto-flagger: RSS 2.0 + Atom 1.0 driver (XXE-safe), `ImpactedClauseDetector` config-driven, `RegulatoryFeedPoller` idempotent + race-safe, `ai-act:regulatory-poll` Artisan; 168/168 PHPUnit
- **v1.3.0** (2026-05-15) — Cohort-drift alerting cascade: Slack + Discord + email channels, `AlertThrottler` with severity-escalation bypass, `CircuitBreaker` with natural-cooldown self-reset, queued `BiasDriftDetectedListener` with `SerializesModels`; 130/130 PHPUnit
- **v1.2.0** (2026-05-15) — Pluggable `CohortParityMetric` strategy registry + DemographicParity / EqualizedOdds / Calibration reference metrics; `metric_name` + `metric_version` + `article_evidence_json` on `bias_snapshots`
- **v1.1.x** (2026-05-13/14) — Cross-tenant FK gaps + decryption error paths + AI Act middleware tests + FRIA module; locked Laravel 13 compat constraints for AskMyDocs v6.0 integration
- **v1.0.0** (2026-05-12) — Full module API surface (9 modules) + initial test pack + WOW README

---

## 🤝 Contributing

PRs welcome. Before opening one:

1. Run `composer test` locally and confirm it's green
2. Add a test for your change
3. Follow the existing code style (Laravel Pint default)
4. Update CHANGELOG.md under `## [Unreleased]`

For major changes (new module, new contract, breaking API), open an issue first so we can discuss the design.

---

## 🔒 Security

If you discover a security vulnerability, please email **security@padosoft.com** instead of opening a public issue. We'll acknowledge within 48 hours.

This package follows responsible disclosure. We publish security advisories at [GitHub Security Advisories](https://github.com/padosoft/laravel-ai-act-compliance/security/advisories) once the fix has shipped.

---

## 🙏 Credits

- **Padosoft** — design, implementation, ongoing maintenance
- **Lorenzo Padovani** ([@lopadova](https://github.com/lopadova)) — product lead + DPO
- **The Laravel community** — for proving the framework can carry serious enterprise loads
- **EU AI Act drafters** — for giving us something to comply with 😉

---

## 📄 License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.

---

<p align="center">
  Made with 🇮🇹 by <a href="https://padosoft.com">Padosoft</a> · Powering <a href="https://github.com/lopadova/AskMyDocs">AskMyDocs</a>
</p>

