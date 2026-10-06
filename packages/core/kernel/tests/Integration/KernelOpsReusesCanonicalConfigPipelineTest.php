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

use PHPUnit\Framework\TestCase;

final class KernelOpsReusesCanonicalConfigPipelineTest extends TestCase
{
    public function testFacadeUsesOneCanonicalPreparationAndDelegatesPhaseBToConfigKernel(): void
    {
        $source = self::source();

        $prepareStart = \strpos($source, 'private function prepareConfigOperation');
        $successStart = \strpos($source, 'private function successResult');
        self::assertIsInt($prepareStart);
        self::assertIsInt($successStart);

        $prepare = \substr($source, $prepareStart, $successStart - $prepareStart);

        self::assertSame(1, \substr_count($prepare, '$this->bootstrapConfigResolver->resolve('));
        self::assertSame(1, \substr_count($prepare, '$this->moduleResolutionOrchestrator->resolve('));
        self::assertSame(1, \substr_count($prepare, '$this->configSourceLocationBuilder->build('));
        self::assertStringNotContainsString('ComposerPackageInstallPathResolver', $prepare);
        self::assertStringNotContainsString('require ', $prepare);
        self::assertStringNotContainsString('include ', $prepare);

        foreach (['validateConfig', 'debugConfig', 'hashConfig'] as $method) {
            $methodSource = self::publicMethodSource($source, $method);

            self::assertSame(1, \substr_count($methodSource, '$this->prepareConfigOperation('));
            self::assertSame(1, \substr_count($methodSource, '$this->configKernel->compile('));
            self::assertStringNotContainsString('ConfigMerger', $methodSource);
            self::assertStringNotContainsString('ConfigValidator', $methodSource);
            self::assertStringNotContainsString('ConfigExplainer', $methodSource);
        }
    }

    private static function publicMethodSource(string $source, string $method): string
    {
        $from = \strpos($source, 'public function ' . $method);
        self::assertIsInt($from);
        $next = \strpos($source, "\n    public function ", $from + 10);

        return $next === false
            ? \substr($source, $from)
            : \substr($source, $from, $next - $from);
    }

    private static function source(): string
    {
        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsFacade.php');
        self::assertIsString($source);

        return $source;
    }
}
