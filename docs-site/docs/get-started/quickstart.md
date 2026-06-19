---
title: Quickstart
description: Install the package and wire the first compliance workflow.
---

# Quickstart

::: steps
1. Require the package.

   ```bash
   composer require padosoft/laravel-ai-act-compliance
   ```

2. Publish package assets.

   ```bash
   php artisan vendor:publish --provider="Padosoft\AiActCompliance\AiActComplianceServiceProvider"
   ```

3. Run migrations.

   ```bash
   php artisan migrate
   ```

4. Enable only the modules you need in `config/ai-act-compliance.php`.

5. Add middleware and service bindings in your Laravel app.
:::

## First disclosure

```php
Route::middleware(['web', 'ai-act.disclosure'])->group(function () {
    Route::get('/assistant', AssistantController::class);
});
```

Use the Blade directive where a user may otherwise miss that the interaction is AI-assisted.

```blade
@aiDisclosure
```

## First risk entry

```php
use Padosoft\AiActCompliance\RiskRegister\Enums\AiActRiskCategory;

app('ai-act.risks')->create([
    'name' => 'Support triage assistant',
    'risk_category' => AiActRiskCategory::Limited,
    'annex_iii_area' => null,
    'owner' => 'support-ops',
]);
```

::: callout tip "Five-minute goal"
After the quickstart, your app should have published migrations, a readable config file, one visible AI disclosure, and a persisted risk entry.
:::
