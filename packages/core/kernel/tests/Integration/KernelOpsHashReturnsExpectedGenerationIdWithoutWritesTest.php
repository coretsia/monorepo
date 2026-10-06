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
use Coretsia\Kernel\Ops\KernelOpsFacade;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class KernelOpsHashReturnsExpectedGenerationIdWithoutWritesTest extends TestCase
{
    public function testHashResultContainsOnlyGenerationIdAndHashFlowHasNoPublicationDependency(): void
    {
        $facade = new ReflectionClass(KernelOpsFacade::class)->newInstanceWithoutConstructor();
        $success = new ReflectionMethod(KernelOpsFacade::class, 'successResult');
        $result = $success->invoke(
            $facade,
            'config.hash',
            'api',
            'test',
            ['generation_id' => 'generation-expected-001'],
        );

        self::assertInstanceOf(OpsResult::class, $result);
        self::assertSame(
            ['generation_id' => 'generation-expected-001'],
            $result->data(),
        );

        $source = self::methodSource();

        self::assertStringContainsString("'generation_id' => \$generationId", $source);

        foreach (
            [
                'ArtifactWriter',
                'ArtifactGenerationPublisher',
                'write(',
                'publish(',
                'currentGeneration',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    private static function methodSource(): string
    {
        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsFacade.php');
        self::assertIsString($source);
        $from = \strpos($source, 'public function hashConfig');
        $to = \strpos($source, 'public function verifyCache');
        self::assertIsInt($from);
        self::assertIsInt($to);

        return \substr($source, $from, $to - $from);
    }
}
