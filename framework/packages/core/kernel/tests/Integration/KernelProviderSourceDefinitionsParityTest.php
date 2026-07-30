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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionKind;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Tests\Fixtures\KernelRuntimeDefinitionProviderFixture;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class KernelProviderSourceDefinitionsParityTest extends TestCase
{
    /**
     * @var list<class-string>
     */
    private const array SERVICE_IDS = [
        RuntimeEntrypointGuard::class,
        HookInvoker::class,
        KernelRuntime::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    private const array ALIASES = [
        KernelRuntimeInterface::class => KernelRuntime::class,
    ];

    public function testSourceRegistrationMatchesCanonicalRuntimeDefinitions(): void
    {
        $config = self::validConfig();

        $sourceBuilder = new ContainerBuilder(config: $config);
        $sourceBuilder->register(
            new FoundationServiceProvider(),
            new KernelRuntimeDefinitionProviderFixture(),
        );

        $sourceContainer = $sourceBuilder->build();

        $definitions = new ContainerDefinitionBuilder();
        new KernelServiceProvider()->define(
            $definitions,
            new ContainerDefinitionContext($config),
        );

        $definitionSet = $definitions->build();
        $operations = $definitionSet->toDescriptorStream();

        self::assertSame(
            self::sorted([
                ...self::SERVICE_IDS,
                ...\array_keys(self::ALIASES),
            ]),
            self::sourceKernelBindingIds(
                builder: $sourceBuilder,
                config: $config,
            ),
        );
        self::assertSame(
            self::SERVICE_IDS,
            self::canonicalServiceIds($operations),
        );
        self::assertSame(
            self::ALIASES,
            self::canonicalAliases($operations),
        );
        self::assertSame([], self::canonicalTags($operations));
        self::assertSame(
            self::sorted([
                ContainerInterface::class,
                TagRegistry::class,
                ContextAccessorInterface::class,
                ResetOrchestrator::class,
                Stopwatch::class,
                IdGeneratorInterface::class,
                CorrelationIdProviderInterface::class,
                CorrelationIdGenerator::class,
                HookInvoker::class,
                LoggerInterface::class,
                TracerPortInterface::class,
                MeterPortInterface::class,
            ]),
            $definitionSet->requiredServiceIds(),
        );

        self::assertAliasParity($sourceContainer);
        self::assertSharedFlagParity($sourceContainer);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private static function sourceKernelBindingIds(
        ContainerBuilder $builder,
        array $config,
    ): array {
        $foundationDefinitions = new ContainerDefinitionBuilder();

        new FoundationServiceProvider()->define(
            $foundationDefinitions,
            new ContainerDefinitionContext($config),
        );

        return self::sorted(
            \array_values(
                \array_diff(
                    $builder->serviceIds(),
                    self::bindingIds(
                        $foundationDefinitions
                            ->build()
                            ->toDescriptorStream(),
                    ),
                    [
                        TagRegistry::class,
                    ],
                ),
            ),
        );
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return list<string>
     */
    private static function bindingIds(array $operations): array
    {
        return self::sorted([
            ...self::canonicalServiceIds($operations),
            ...\array_keys(self::canonicalAliases($operations)),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return list<string>
     */
    private static function canonicalServiceIds(
        array $operations,
    ): array {
        $ids = [];

        foreach ($operations as $operation) {
            $kind = $operation['kind'] ?? null;

            if (!\is_string($kind) || !self::isServiceKind($kind)) {
                continue;
            }

            $id = $operation['id'] ?? null;
            $shared = $operation['shared'] ?? null;

            if (!\is_string($id) || !\is_bool($shared)) {
                throw new \LogicException('kernel-provider-parity-service-invalid');
            }

            self::assertTrue($shared);

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return array<string, string>
     */
    private static function canonicalAliases(
        array $operations,
    ): array {
        $aliases = [];

        foreach ($operations as $operation) {
            if (
                ($operation['kind'] ?? null)
                !== ContainerDefinitionKind::ALIAS->value
            ) {
                continue;
            }

            $alias = $operation['alias'] ?? null;
            $serviceId = $operation['serviceId'] ?? null;

            if (!\is_string($alias) || !\is_string($serviceId)) {
                throw new \LogicException('kernel-provider-parity-alias-invalid');
            }

            $aliases[$alias] = $serviceId;
        }

        return $aliases;
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return list<array<string, mixed>>
     */
    private static function canonicalTags(
        array $operations,
    ): array {
        return \array_values(
            \array_filter(
                $operations,
                static fn (array $operation): bool => ($operation['kind'] ?? null)
                    === ContainerDefinitionKind::TAG->value,
            ),
        );
    }

    private static function assertAliasParity(
        Container $container,
    ): void {
        self::assertSame(
            $container->get(KernelRuntime::class),
            $container->get(KernelRuntimeInterface::class),
        );
    }

    private static function assertSharedFlagParity(
        Container $container,
    ): void {
        foreach (self::SERVICE_IDS as $serviceId) {
            self::assertSame(
                $container->get($serviceId),
                $container->get($serviceId),
            );
        }
    }

    private static function isServiceKind(string $kind): bool
    {
        return $kind
            === ContainerDefinitionKind::SERVICE_CLASS->value
            || $kind
            === ContainerDefinitionKind::SERVICE_FACTORY_CLASS_METHOD->value
            || $kind
            === ContainerDefinitionKind::SERVICE_FACTORY_SERVICE_METHOD->value;
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        \usort(
            $values,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => false,
                    'allow_reflection_for_concrete' => false,
                ],
                'ids' => [
                    'default' => 'ulid',
                ],
                'reset' => [
                    'tag' => ReservedTags::KERNEL_RESET,
                    'priority' => [
                        'enabled' => false,
                    ],
                ],
            ],
            'kernel' => [
                'uow' => [
                    'attributes' => [
                        'max_depth' => 10,
                        'max_keys' => 200,
                    ],
                ],
            ],
        ];
    }
}
