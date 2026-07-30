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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Clock\SystemClock;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionKind;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Id\UuidGenerator;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class FoundationProviderSourceDefinitionsParityTest extends TestCase
{
    /**
     * @var list<class-string>
     */
    private const array SERVICE_IDS = [
        SystemClock::class,
        Stopwatch::class,
        UlidGenerator::class,
        UuidGenerator::class,
        ContextStore::class,
        CorrelationIdGenerator::class,
        CorrelationIdProvider::class,
        LoggerInterface::class,
        TracerPortInterface::class,
        MeterPortInterface::class,
        ErrorReporterPortInterface::class,
        ProfilerPortInterface::class,
        ContextPropagationInterface::class,
        PriorityResetOrchestrator::class,
        ResetOrchestrator::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    private const array ALIASES = [
        ClockInterface::class => SystemClock::class,
        IdGeneratorInterface::class => UlidGenerator::class,
        ContextAccessorInterface::class => ContextStore::class,
        CorrelationIdProviderInterface::class => CorrelationIdProvider::class,
    ];

    public function testSourceRegistrationMatchesCanonicalDefinitions(): void
    {
        $config = self::validConfig();
        $provider = new FoundationServiceProvider();

        $sourceBuilder = new ContainerBuilder(config: $config);
        $sourceBuilder->register($provider);

        $sourceContainer = $sourceBuilder->build();

        $definitions = new ContainerDefinitionBuilder();
        $provider->define(
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
            self::sourceBindingIds($sourceBuilder),
        );
        self::assertSame(
            self::SERVICE_IDS,
            self::canonicalServiceIds($operations),
        );
        self::assertSame(
            self::ALIASES,
            self::canonicalAliases($operations),
        );
        self::assertSame(
            [
                [
                    'kind' => ContainerDefinitionKind::TAG->value,
                    'meta' => [],
                    'priority' => 0,
                    'serviceId' => ContextStore::class,
                    'tag' => ReservedTags::KERNEL_RESET,
                ],
                [
                    'kind' => ContainerDefinitionKind::TAG->value,
                    'meta' => [],
                    'priority' => 0,
                    'serviceId' => ContextStore::class,
                    'tag' => ReservedTags::KERNEL_STATEFUL,
                ],
            ],
            self::canonicalTags($operations),
        );
        self::assertSame(
            self::sorted([
                ContainerInterface::class,
                TagRegistry::class,
                Stopwatch::class,
                LoggerInterface::class,
                TracerPortInterface::class,
                MeterPortInterface::class,
            ]),
            $definitionSet->requiredServiceIds(),
        );

        self::assertAliasParity($sourceContainer);
        self::assertSharedFlagParity($sourceContainer);
        self::assertTagParity($sourceBuilder->tagRegistry());
    }

    /**
     * @return list<string>
     */
    private static function sourceBindingIds(
        ContainerBuilder $builder,
    ): array {
        return self::sorted(
            \array_values(
                \array_filter(
                    $builder->serviceIds(),
                    static fn (string $id): bool => $id !== TagRegistry::class,
                ),
            ),
        );
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
                throw new \LogicException('foundation-provider-parity-service-invalid');
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
                throw new \LogicException('foundation-provider-parity-alias-invalid');
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
        foreach (self::ALIASES as $alias => $serviceId) {
            self::assertSame(
                $container->get($serviceId),
                $container->get($alias),
            );
        }
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

    private static function assertTagParity(
        TagRegistry $registry,
    ): void {
        self::assertSame(
            [
                ReservedTags::KERNEL_RESET,
                ReservedTags::KERNEL_STATEFUL,
            ],
            $registry->tagNames(),
        );

        foreach ($registry->tagNames() as $tag) {
            $services = $registry->all($tag);

            self::assertCount(1, $services);
            self::assertSame(ContextStore::class, $services[0]->id());
            self::assertSame(0, $services[0]->priority());
            self::assertSame([], $services[0]->meta());
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
        ];
    }
}
