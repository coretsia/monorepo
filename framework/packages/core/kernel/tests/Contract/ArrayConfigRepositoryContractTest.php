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

use Coretsia\Kernel\Config\ArrayConfigRepository;
use PHPUnit\Framework\TestCase;

final class ArrayConfigRepositoryContractTest extends TestCase
{
    public function testRepositoryReadsNestedLogicalConfigPaths(): void
    {
        $repository = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'roadrunner' => [
                        'enabled' => true,
                    ],
                ],
            ],
            'worker' => [
                'enabled' => false,
            ],
        ]);

        self::assertTrue($repository->has('kernel.runtime.roadrunner.enabled'));
        self::assertTrue($repository->get('kernel.runtime.roadrunner.enabled'));
        self::assertTrue($repository->has('worker.enabled'));
        self::assertFalse($repository->get('worker.enabled'));
    }

    public function testRepositoryDoesNotInventMissingValues(): void
    {
        $repository = new ArrayConfigRepository([
            'kernel' => [],
        ]);

        self::assertFalse($repository->has('kernel.runtime.roadrunner.enabled'));
        self::assertNull($repository->get('kernel.runtime.roadrunner.enabled'));
    }

    public function testRepositoryRejectsInvalidKeyPathsDeterministically(): void
    {
        $repository = new ArrayConfigRepository([]);

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

        $repository = new ArrayConfigRepository($config);

        self::assertSame($config, $repository->all());
        self::assertNull($repository->sourceOf('kernel.runtime.frankenphp.enabled'));
        self::assertSame([], $repository->explain());
    }
}
