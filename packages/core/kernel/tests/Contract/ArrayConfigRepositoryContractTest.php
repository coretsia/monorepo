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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use PHPUnit\Framework\TestCase;

final class ArrayConfigRepositoryContractTest extends TestCase
{
    public function testRepositoryReadsNestedLogicalConfigPaths(): void
    {
        $repository = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'http_driver' => 'http.roadrunner',
                ],
            ],
            'worker' => [
                'task_type' => 'queue',
            ],
        ], []);

        self::assertTrue($repository->has('kernel.runtime.http_driver'));
        self::assertSame('http.roadrunner', $repository->get('kernel.runtime.http_driver'));
        self::assertTrue($repository->has('worker.task_type'));
        self::assertSame('queue', $repository->get('worker.task_type'));
    }

    public function testRepositoryDoesNotInventMissingValues(): void
    {
        $repository = new ArrayConfigRepository([
            'kernel' => [],
        ], []);

        self::assertFalse($repository->has('kernel.runtime.http_driver'));
        self::assertNull($repository->get('kernel.runtime.http_driver'));
    }

    public function testRepositoryRejectsInvalidKeyPathsDeterministically(): void
    {
        $repository = new ArrayConfigRepository([], []);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('array-config-key-path-empty');

        $repository->has('');
    }

    public function testRepositoryReturnsFullConfigTree(): void
    {
        $config = [
            'kernel' => [
                'runtime' => [
                    'frankenphp' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ];

        $repository = new ArrayConfigRepository($config, []);

        self::assertSame($config, $repository->all());
        self::assertNull($repository->sourceOf('kernel.runtime.frankenphp.enabled'));
        self::assertNull($repository->sourceOf('kernel.runtime.http_driver'));
        self::assertSame([], $repository->explain());
    }

    public function testRepositoryRequiresExplicitSourceMetadata(): void
    {
        $constructor = new \ReflectionMethod(ArrayConfigRepository::class, '__construct');
        $parameters = $constructor->getParameters();

        self::assertCount(2, $parameters);
        self::assertSame(2, $constructor->getNumberOfRequiredParameters());
        self::assertSame('sources', $parameters[1]->getName());
        self::assertFalse($parameters[1]->isDefaultValueAvailable());

        $type = $parameters[1]->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame('array', $type->getName());
        self::assertFalse($type->allowsNull());
    }

    public function testRepositorySelectsCanonicalEffectiveSourcesForExistingPaths(): void
    {
        $config = [
            'kernel' => [
                'runtime' => [
                    'driver' => 'http.roadrunner',
                    'enabled' => null,
                    'timeout' => 30,
                ],
                'runtime_extra' => [
                    'enabled' => true,
                ],
            ],
        ];

        $defaults = new ConfigValueSource(
            type: ConfigSourceType::PackageDefault,
            root: 'kernel',
            sourceId: 'core/kernel/config/defaults/kernel',
            keyPath: 'kernel',
            precedence: 10,
        );
        $older = new ConfigValueSource(
            type: ConfigSourceType::AppConfig,
            root: 'kernel',
            sourceId: 'application-config/older',
            keyPath: 'kernel.runtime.timeout',
            precedence: 400,
            meta: ['sourceOrder' => 1],
        );
        $parent = new ConfigValueSource(
            type: ConfigSourceType::AppConfig,
            root: 'kernel',
            sourceId: 'application-config/parent',
            keyPath: 'kernel.runtime',
            precedence: 400,
            meta: ['sourceOrder' => 2],
        );
        $specific = new ConfigValueSource(
            type: ConfigSourceType::AppConfig,
            root: 'kernel',
            sourceId: 'application-config/specific',
            keyPath: 'kernel.runtime.enabled',
            precedence: 400,
            meta: ['sourceOrder' => 2],
        );
        $env = new ConfigValueSource(
            type: ConfigSourceType::Env,
            root: 'kernel',
            sourceId: 'env-overlay/ruleset/KERNEL_RUNTIME_DRIVER',
            keyPath: 'kernel.runtime.driver',
            precedence: 500,
            redacted: true,
            meta: ['sourceOrder' => 0],
        );

        $sources = [$env, $older, $specific, $defaults, $parent];

        foreach ([$sources, \array_reverse($sources)] as $sourceList) {
            $repository = new ArrayConfigRepository($config, $sourceList);

            self::assertSame($env, $repository->sourceOf('kernel.runtime.driver'));
            self::assertSame($specific, $repository->sourceOf('kernel.runtime.enabled'));
            self::assertSame($parent, $repository->sourceOf('kernel.runtime.timeout'));
            self::assertSame($defaults, $repository->sourceOf('kernel.runtime_extra.enabled'));
            self::assertTrue($repository->has('kernel.runtime.enabled'));
            self::assertNull($repository->get('kernel.runtime.enabled'));
            self::assertNull($repository->sourceOf('kernel.runtime.missing'));
            self::assertSame([$defaults, $parent, $specific, $older, $env], $repository->explain());
        }
    }
}
