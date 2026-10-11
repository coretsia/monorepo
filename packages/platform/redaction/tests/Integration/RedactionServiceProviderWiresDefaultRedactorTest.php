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

namespace Coretsia\Platform\Redaction\Tests\Integration;

use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;

final class RedactionServiceProviderWiresDefaultRedactorTest extends TestCase
{
    public function testCanonicalSourceContainerResolvesOneSharedRedactorWithExactInjectedDependencies(): void
    {
        $builder = new ContainerBuilder(config: []);
        $builder->register(new RedactionServiceProvider());

        self::assertSame([], $builder->config());
        self::assertSame([], $builder->tagRegistry()->tagNames());

        $expectedIds = [
            DefaultSensitiveDataRedactor::class,
            SensitiveDataRedactorInterface::class,
            SensitiveKeyClassifier::class,
            SensitiveValueClassifier::class,
            StableRedactionHasher::class,
        ];
        \usort($expectedIds, static fn (string $left, string $right): int => \strcmp($left, $right));

        $actualIds = \array_values(
            \array_filter(
                $builder->serviceIds(),
                static fn (string $id): bool => $id !== TagRegistry::class,
            ),
        );

        self::assertSame($expectedIds, $actualIds);
        self::assertContains(TagRegistry::class, $builder->serviceIds());

        $container = $builder->build();
        $redactor = $container->get(SensitiveDataRedactorInterface::class);

        self::assertInstanceOf(DefaultSensitiveDataRedactor::class, $redactor);
        self::assertSame($redactor, $container->get(DefaultSensitiveDataRedactor::class));
        self::assertSame($redactor, $container->get(SensitiveDataRedactorInterface::class));
        self::assertSame($redactor, $container->get(DefaultSensitiveDataRedactor::class));

        $expectedConstructor = [
            'keyClassifier' => SensitiveKeyClassifier::class,
            'valueClassifier' => SensitiveValueClassifier::class,
            'hasher' => StableRedactionHasher::class,
        ];
        $reflection = new \ReflectionClass($redactor);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();
        self::assertCount(3, $parameters);
        $parameterNames = \array_keys($expectedConstructor);
        self::assertSame(
            $parameterNames,
            \array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                $parameters,
            ),
        );

        foreach ($parameters as $index => $parameter) {
            $propertyName = $parameterNames[$index];
            $class = $expectedConstructor[$propertyName];
            $type = $parameter->getType();

            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame($class, $type->getName());
            self::assertFalse($type->allowsNull());

            $property = $reflection->getProperty($propertyName);
            self::assertTrue($property->isPrivate());
            self::assertTrue($property->isReadOnly());

            $dependency = $container->get($class);
            self::assertInstanceOf($class, $dependency);
            self::assertSame($dependency, $property->getValue($redactor));
            self::assertSame($dependency, $container->get($class));
        }

        self::assertCount(3, $reflection->getProperties());
    }

    public function testResolvedRedactorWorksWithExplicitContextAndNoAmbientRuntimeServices(): void
    {
        $builder = new ContainerBuilder(config: []);
        $builder->register(new RedactionServiceProvider());
        $container = $builder->build();

        self::assertSame([], $builder->config());
        self::assertSame([], $builder->tagRegistry()->tagNames());

        $redactor = $container->get(SensitiveDataRedactorInterface::class);
        self::assertInstanceOf(DefaultSensitiveDataRedactor::class, $redactor);

        // The caller supplies the immutable operation context directly; it is not a container service.
        $context = new RedactionContext('redaction.integration', RedactionMode::Placeholder);
        self::assertTrue(new \ReflectionClass(RedactionContext::class)->isReadOnly());
        self::assertNotContains(RedactionContext::class, $container->serviceIds());

        $summary = $redactor->redactValue('synthetic-private-string', RedactionKind::Secret, $context);
        self::assertSame(
            [
                'hash' => null,
                'kind' => 'secret',
                'length' => null,
                'mode' => 'placeholder',
                'redacted' => true,
                'schemaVersion' => 1,
            ],
            $summary->toArray(),
        );

        self::assertSame(
            [
                'password' => $summary->toArray(),
                'status' => 'ok',
            ],
            $redactor->redactJsonLike(
                ['status' => 'ok', 'password' => 'synthetic-private-string'],
                $context,
            ),
        );

        self::assertSame($redactor, $container->get(SensitiveDataRedactorInterface::class));
        self::assertSame($redactor, $container->get(DefaultSensitiveDataRedactor::class));
    }
}
