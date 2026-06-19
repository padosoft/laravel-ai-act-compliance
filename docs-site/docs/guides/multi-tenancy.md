---
title: Multi-Tenancy
description: Use tenant context, tenant overrides, and cross-tenant reporting.
---

# Multi-Tenancy

The package includes a tenant registry, request-scoped context, config override resolver, and cross-tenant overview service.

## Tenant context

Use `ai-act.tenant-context` on API routes where tenant scoping must be inferred from the request.

```php
Route::middleware(['api', 'ai-act.tenant-context'])->prefix('ai-act')->group(function () {
    require __DIR__.'/api.php';
});
```

## Status handling

| Tenant status | Expected behavior |
| --- | --- |
| Active | Request proceeds |
| Locked | Return locked response |
| Deleted | Return gone response |
| Missing | Return not found response |

::: callout tip "No N+1 overview"
Use `CrossTenantOverviewService` for aggregate dashboards; it is designed around grouped queries by `tenant_id`.
:::
