<?php

namespace Padosoft\AiActCompliance\MultiTenancy\Services;

use Illuminate\Contracts\Config\Repository;

/**
 * Layers per-tenant overrides on top of the host's
 * `ai-act-compliance.*` config block.
 *
 * Read pattern:
 *
 *   $resolver->resolve('bias.disparity_threshold', 0.05)
 *
 * Resolution order:
 *
 *   1. Tenant override (config_overrides_json[<dotted-key>])
 *   2. Host config (ai-act-compliance.<dotted-key>)
 *   3. Caller-supplied default
 *
 * Per-tenant overrides MUST be opt-in: a tenant without an override
 * key inherits the host default exactly — no behavioural divergence
 * across tenants until an override is explicitly persisted.
 */
class TenantConfigResolver
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly Repository $config,
    ) {}

    public function resolve(string $dottedKey, mixed $default = null): mixed
    {
        $hostValue = $this->config->get('ai-act-compliance.'.$dottedKey, $default);

        $tenant = $this->context->current();
        if ($tenant === null) {
            return $hostValue;
        }

        $overrides = $tenant->config_overrides_json ?? [];
        if (! is_array($overrides) || $overrides === []) {
            return $hostValue;
        }

        // Operators commonly write overrides as FLAT dotted keys
        // (`{"bias.disparity_threshold": 0.02}`); data_get would
        // descend into a nested map but miss the literal key. Check
        // the exact key first so flat is the natural form, then fall
        // through to data_get for nested.
        if (array_key_exists($dottedKey, $overrides)) {
            return $overrides[$dottedKey];
        }

        return data_get($overrides, $dottedKey, $hostValue);
    }
}
