<?php

declare(strict_types=1);

/*
 * Coretsia Framework (Monorepo)
 *
 * Project: Coretsia Framework (Monorepo)
 * Authors: Vladyslav Mudrichenko and contributors
 * Copyright (c) 2026 Vladyslav Mudrichenko
 *
 * SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
 * SPDX-License-Identifier: Apache-2.0
 *
 * For contributors list, see git history.
 * See LICENSE and NOTICE in the project root for full license information.
 */

namespace Coretsia\Platform\Redaction\Tests\Contract;

use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;

final class RedactionProviderSourceDefinitionsParityTest extends TestCase
{
    public function testSourceAndDeclarativeApplicationsProduceIdenticalBindings(): void
    {
        $sourceBuilder = new ContainerBuilder();
        $provider = new RedactionServiceProvider();
        $sourceBuilder->register($provider);

        $definitions = new ContainerDefinitionBuilder();
        $provider->define($definitions, new ContainerDefinitionContext([]));
        $declarativeBuilder = new ContainerBuilder();
        $declarativeBuilder->applyDefinitions($definitions->build());

        $expectedIds = [
            DefaultSensitiveDataRedactor::class,
            SensitiveDataRedactorInterface::class,
            SensitiveKeyClassifier::class,
            SensitiveValueClassifier::class,
            StableRedactionHasher::class,
        ];
        \usort($expectedIds, static fn (string $left, string $right): int => \strcmp($left, $right));

        foreach ([$sourceBuilder, $declarativeBuilder] as $builder) {
            $bindingIds = \array_values(
                \array_filter(
                    $builder->serviceIds(),
                    static fn (string $id): bool => $id !== TagRegistry::class,
                ),
            );

            self::assertSame($expectedIds, $bindingIds);
            self::assertSame([], $builder->tagRegistry()->tagNames());

            $container = $builder->build();
            $redactor = $container->get(SensitiveDataRedactorInterface::class);
            $implementation = $container->get(DefaultSensitiveDataRedactor::class);

            self::assertInstanceOf(DefaultSensitiveDataRedactor::class, $redactor);
            self::assertSame($implementation, $redactor);
            self::assertSame($redactor, $container->get(SensitiveDataRedactorInterface::class));
            self::assertSame($implementation, $container->get(DefaultSensitiveDataRedactor::class));

            foreach (
                [
                    SensitiveKeyClassifier::class,
                    SensitiveValueClassifier::class,
                    StableRedactionHasher::class,
                ] as $class
            ) {
                self::assertInstanceOf($class, $container->get($class));
                self::assertSame($container->get($class), $container->get($class));
            }
        }

        // Source registration also seeds Foundation-owned TagRegistry; it is not a package binding.
        self::assertContains(TagRegistry::class, $sourceBuilder->serviceIds());
        self::assertNotContains(TagRegistry::class, $declarativeBuilder->serviceIds());
    }

    public function testUnappliedProviderContributesNoRedactorBinding(): void
    {
        $builder = new ContainerBuilder();
        self::assertSame([], $builder->serviceIds());
        self::assertFalse($builder->build()->has(SensitiveDataRedactorInterface::class));
    }
}
