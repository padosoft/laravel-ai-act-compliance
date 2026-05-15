<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\BiasMonitoring\Contracts\CohortDimensionResolver;
use Padosoft\AiActCompliance\BiasMonitoring\Services\DimensionRegistry;
use Padosoft\AiActCompliance\Tests\TestCase;

class DimensionRegistryTest extends TestCase
{
    public function test_register_makes_the_dimension_resolvable(): void
    {
        $registry = new DimensionRegistry();
        $registry->register(new FixtureCreditBandResolver());

        self::assertTrue($registry->has('credit_band'));
        self::assertSame(['credit_band'], $registry->keys());
    }

    public function test_get_returns_the_registered_resolver(): void
    {
        $registry = new DimensionRegistry();
        $resolver = new FixtureCreditBandResolver();
        $registry->register($resolver);

        self::assertSame($resolver, $registry->get('credit_band'));
    }

    public function test_get_returns_null_for_unknown_dimension(): void
    {
        $registry = new DimensionRegistry();

        self::assertNull($registry->get('not_registered'));
    }

    public function test_resolver_can_return_null_to_skip_a_subject(): void
    {
        $registry = new DimensionRegistry();
        $registry->register(new FixtureCreditBandResolver());

        $resolver = $registry->get('credit_band');
        self::assertNotNull($resolver);
        self::assertSame('A', $resolver->resolveCohortFor(['credit_score' => 800]));
        self::assertNull($resolver->resolveCohortFor(['credit_score' => null]));
    }

    public function test_registering_a_resolver_with_the_same_key_overwrites_the_previous(): void
    {
        $registry = new DimensionRegistry();
        $registry->register(new FixtureCreditBandResolver());
        $registry->register(new FixtureCreditBandResolverV2());

        $resolver = $registry->get('credit_band');
        self::assertNotNull($resolver);
        self::assertSame('PRIME', $resolver->resolveCohortFor(['credit_score' => 800]));
    }
}

class FixtureCreditBandResolver implements CohortDimensionResolver
{
    public function dimensionKey(): string
    {
        return 'credit_band';
    }

    public function resolveCohortFor(mixed $subject): ?string
    {
        if (! is_array($subject) || ! isset($subject['credit_score'])) {
            return null;
        }
        $score = (int) $subject['credit_score'];

        return $score >= 720 ? 'A' : 'B';
    }
}

class FixtureCreditBandResolverV2 implements CohortDimensionResolver
{
    public function dimensionKey(): string
    {
        return 'credit_band';
    }

    public function resolveCohortFor(mixed $subject): ?string
    {
        if (! is_array($subject) || ! isset($subject['credit_score'])) {
            return null;
        }

        return ((int) $subject['credit_score']) >= 720 ? 'PRIME' : 'SUB';
    }
}
