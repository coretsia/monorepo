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

use Coretsia\Contracts\Kernel\Ops\OpsResult;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class KernelOpsDebugConfigPreservesSafeExplainPathsTest extends TestCase
{
    public function testSafeLogicalPathsAndDeterministicExplainShapeArePreserved(): void
    {
        $facade = new ReflectionClass(KernelOpsFacade::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(KernelOpsFacade::class, 'successResult');

        $result = $method->invoke(
            $facade,
            'config.debug',
            'console',
            'test',
            [
                'explain' => [
                    'zeta' => [
                        'sources' => [
                            'config/app.php',
                            'config/modes/test.php',
                        ],
                    ],
                    'alpha' => [
                        'path' => 'packages/core/kernel/config/kernel.php',
                        'logical' => 'kernel.boot.default_env',
                    ],
                ],
            ],
        );

        self::assertInstanceOf(OpsResult::class, $result);
        self::assertSame(
            ['alpha', 'zeta'],
            \array_keys($result->data()['explain']),
        );
        self::assertSame(
            ['config/app.php', 'config/modes/test.php'],
            $result->data()['explain']['zeta']['sources'],
        );
        self::assertSame(
            'packages/core/kernel/config/kernel.php',
            $result->data()['explain']['alpha']['path'],
        );
        self::assertSame(
            ['logical', 'path'],
            \array_keys($result->data()['explain']['alpha']),
        );

        $canonicalExplain = new ConfigExplainer()->explain(
            config: [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'prod',
                    ],
                ],
            ],
            sources: [],
        );

        $canonicalResult = $method->invoke(
            $facade,
            'config.debug',
            'console',
            'test',
            ['explain' => $canonicalExplain],
        );

        self::assertInstanceOf(OpsResult::class, $canonicalResult);
        self::assertSame(
            JsonLikeNormalizer::normalize(
                $canonicalExplain,
                'kernel_ops_test_explain',
            ),
            $canonicalResult->data()['explain'],
        );

        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsFacade.php');

        self::assertIsString($source);
        $assignment = \strpos(
            $source,
            '$explain = $compiled[\'explain\'] ?? null;',
        );
        self::assertIsInt($assignment);

        $projection = \strpos(
            $source,
            '\'explain\' => $explain,',
            $assignment,
        );
        self::assertIsInt($projection);
        self::assertTrue($assignment < $projection);
    }

    public function testAbsoluteExplainPathIsRejectedAtOpsResultBoundary(): void
    {
        $facade = new ReflectionClass(KernelOpsFacade::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(KernelOpsFacade::class, 'successResult');
        $absolute = \DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\private\\config.php'
            : '/private/config.php';

        $this->expectException(\InvalidArgumentException::class);

        $method->invoke(
            $facade,
            'config.debug',
            'console',
            'test',
            [
                'explain' => [
                    'source' => $absolute,
                ],
            ],
        );
    }
}
