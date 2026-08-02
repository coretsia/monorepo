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

namespace Coretsia\Platform\Worker\Tests\Contract;

use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class WorkerPoolSpecConfigContractTest extends TestCase
{
    public function testSpecOwnsAllValidatedRuntimeFieldsWithoutFallbackAliases(): void
    {
        $method = new ReflectionMethod(WorkerPoolSpec::class, 'fromConfig');
        self::assertTrue($method->isStatic());

        foreach (
            [
                'lockPath',
                'startTimeoutMs',
                'stopTimeoutMs',
                'forceKillTimeoutMs',
                'endpointIdentifier',
            ] as $getter
        ) {
            self::assertTrue(
                \method_exists(WorkerPoolSpec::class, $getter),
                $getter,
            );
        }

        $source = \file_get_contents(
            \dirname(__DIR__, 2) . '/src/Runtime/WorkerPoolSpec.php',
        );

        self::assertIsString($source);
        self::assertStringContainsString(
            'assertRuntimeArtifactPathsDoNotOverlap',
            $source,
        );
        self::assertStringContainsString(
            "'127.0.0.1'",
            \file_get_contents(
                \dirname(__DIR__, 2) . '/config/rules.php',
            ) ?: '',
        );
        $codeOnly = \preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $source) ?? $source;

        self::assertStringNotContainsString('getenv(', $codeOnly);
        self::assertStringContainsString("'skeleton/'", $codeOnly);
    }
}
