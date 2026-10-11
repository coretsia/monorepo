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

use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionKind;
use Coretsia\Foundation\Container\Exception\NotFoundException;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Redaction\Module\RedactionModule;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;

final class RedactionProviderRequiresExplicitApplicationTest extends TestCase
{
    public function testComposerAndAutoloadPresenceDoNotChangeAnUnconfiguredFoundationBuilder(): void
    {
        $builder = new ContainerBuilder(config: []);
        self::assertSame([], $builder->serviceIds());
        self::assertSame([], $builder->tagRegistry()->tagNames());

        self::assertFileExists(\dirname(__DIR__, 2) . '/composer.json');
        self::assertTrue(\class_exists(RedactionModule::class));
        self::assertTrue(\class_exists(RedactionServiceProvider::class));
        self::assertTrue(\class_exists(DefaultSensitiveDataRedactor::class));
        self::assertTrue(\interface_exists(SensitiveDataRedactorInterface::class));

        self::assertSame([], $builder->serviceIds());
        self::assertSame([], $builder->tagRegistry()->tagNames());
        self::assertFalse($builder->build()->has(SensitiveDataRedactorInterface::class));

        try {
            $builder->build()->get(SensitiveDataRedactorInterface::class);
            self::fail('Autoload presence must never register a redactor.');
        } catch (NotFoundException $exception) {
            self::assertSame(
                NotFoundException::REASON_SERVICE_NOT_FOUND,
                $exception->reason(),
            );
        }
    }

    public function testConstructingModuleAndProviderDoesNotApplyAnyDefinitions(): void
    {
        $builder = new ContainerBuilder(config: []);

        $module = new RedactionModule();

        self::assertSame([], $builder->serviceIds());
        self::assertSame([], $builder->tagRegistry()->tagNames());
        self::assertFalse($builder->build()->has(SensitiveDataRedactorInterface::class));

        $provider = new RedactionServiceProvider();

        self::assertSame([RedactionServiceProvider::class], $module->providers());
        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
        self::assertSame([], $builder->serviceIds());
        self::assertSame([], $builder->tagRegistry()->tagNames());
        self::assertFalse($builder->build()->has(SensitiveDataRedactorInterface::class));
    }

    public function testDebugEnvironmentAndConfigCannotAutoApplyAnUnregisteredProvider(): void
    {
        foreach (
            [
                [],
                ['debug' => true],
                ['app' => ['env' => 'development']],
                ['debug' => false, 'app' => ['env' => 'production']],
                ['redaction' => ['enabled' => true]],
            ] as $config
        ) {
            $builder = new ContainerBuilder(config: $config);

            self::assertSame($config, $builder->config());
            self::assertSame([], $builder->serviceIds());
            self::assertSame([], $builder->tagRegistry()->tagNames());

            $module = new RedactionModule();
            self::assertSame([RedactionServiceProvider::class], $module->providers());
            self::assertSame([], $builder->serviceIds());
            self::assertFalse($builder->build()->has(SensitiveDataRedactorInterface::class));
        }
    }

    public function testOnlyExplicitApplicationOfModuleDeclaredProviderContributesExactlyOneRedactorAlias(): void
    {
        $module = new RedactionModule();
        $providerClasses = $module->providers();
        self::assertSame([RedactionServiceProvider::class], $providerClasses);

        $definitions = new ContainerDefinitionBuilder();
        $providers = [];

        foreach ($providerClasses as $providerClass) {
            $provider = new $providerClass();
            self::assertInstanceOf(ServiceProviderInterface::class, $provider);
            self::assertInstanceOf(RedactionServiceProvider::class, $provider);

            $provider->define($definitions, new ContainerDefinitionContext([]));
            $providers[] = $provider;
        }

        $aliases = \array_values(
            \array_filter(
                $definitions->build()->toDescriptorStream(),
                static fn (array $operation): bool => ($operation['kind'] ?? null)
                    === ContainerDefinitionKind::ALIAS->value,
            ),
        );

        self::assertSame(
            [
                [
                    'alias' => SensitiveDataRedactorInterface::class,
                    'kind' => ContainerDefinitionKind::ALIAS->value,
                    'serviceId' => DefaultSensitiveDataRedactor::class,
                ],
            ],
            $aliases,
        );

        $builder = new ContainerBuilder(config: []);
        self::assertSame([], $builder->serviceIds());
        $builder->registerProviders($providers);

        $bindingIds = \array_values(
            \array_filter(
                $builder->serviceIds(),
                static fn (string $id): bool => $id !== TagRegistry::class,
            ),
        );
        $expectedIds = [
            DefaultSensitiveDataRedactor::class,
            SensitiveDataRedactorInterface::class,
            SensitiveKeyClassifier::class,
            SensitiveValueClassifier::class,
            StableRedactionHasher::class,
        ];
        \usort($expectedIds, static fn (string $left, string $right): int => \strcmp($left, $right));

        self::assertSame($expectedIds, $bindingIds);
        self::assertCount(
            1,
            \array_filter(
                $bindingIds,
                static fn (string $id): bool => $id === SensitiveDataRedactorInterface::class,
            ),
        );
        self::assertSame([], $builder->tagRegistry()->tagNames());

        $container = $builder->build();
        $redactor = $container->get(SensitiveDataRedactorInterface::class);
        self::assertInstanceOf(DefaultSensitiveDataRedactor::class, $redactor);
        self::assertSame($redactor, $container->get(DefaultSensitiveDataRedactor::class));
        self::assertSame($redactor, $container->get(SensitiveDataRedactorInterface::class));
    }
}
