---
title: CLI Reference
description: Artisan command reference.
---

# CLI Reference

## `ai-act:regulatory-poll`

Poll configured regulatory feeds and persist newly detected amendments.

```bash
php artisan ai-act:regulatory-poll
```

## Package test command

The Composer test script runs PHPUnit.

```bash
composer test
```

::: callout info "Scheduling"
Schedule regulatory polling in Laravel's scheduler after feeds and tenant policy are configured.
:::
