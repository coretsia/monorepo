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

final class KernelOpsCompileUsesSingleModuleResolutionSnapshotTest extends TestCase
{
    public function testCompileUsesExactlyOnePreparedModuleResolutionSnapshot(): void
    {
        $facade = self::source('../../src/Ops/KernelOpsFacade.php');
        $operation = self::source('../../src/Artifacts/Operation/KernelArtifactOperation.php');
        $compiler = self::source('../../src/Artifacts/Compiler/ArtifactCompiler.php');
        $graphCompiler = self::source('../../src/Container/RuntimeContainerGraphCompiler.php');

        $compileFacade = self::between($facade, 'public function compileConfig', 'public function hashConfig');
        $compileOperation = self::between($operation, 'public function compile', 'public function verify');
        $prepare = self::between($operation, 'private function prepare', "\n}");

        self::assertStringContainsString('$this->kernelArtifactOperation->compile($input)', $compileFacade);
        self::assertStringNotContainsString('moduleResolutionOrchestrator->resolve', $compileFacade);

        self::assertSame(1, \substr_count($compileOperation, '$this->prepare($input)'));
        self::assertSame(1, \substr_count($prepare, '$this->moduleResolutionOrchestrator->resolve('));
        self::assertSame(1, \substr_count($prepare, '$this->bootstrapConfigResolver->resolve('));

        self::assertStringContainsString(
            "moduleResolution: \$prepared['moduleResolution']",
            $compileOperation,
        );
        self::assertStringContainsString(
            "\$result['effectivePreset'] = \$prepared['bootstrapConfig']->preset();",
            $compileOperation,
        );

        self::assertStringContainsString('$modulePlan = $moduleResolution->plan();', $compiler);
        self::assertStringContainsString('moduleResolution: $moduleResolution', $compiler);
        self::assertStringContainsString('modulePlan: $modulePlan', $compiler);
        self::assertStringNotContainsString('ModuleResolutionOrchestrator', $compiler);
        self::assertStringNotContainsString('ManifestReaderInterface', $compiler);

        self::assertSame(1, \substr_count($graphCompiler, '$this->providerPlanResolver->resolve('));
        self::assertStringNotContainsString('ManifestReaderInterface', $graphCompiler);

        $orchestrator = self::source('../../src/Module/ModuleResolutionOrchestrator.php');

        self::assertSame(
            1,
            \substr_count($orchestrator, '$this->manifestReader->read()'),
        );
        self::assertStringNotContainsString('providerPlanResolver', $compiler);
        self::assertStringNotContainsString('ManifestReaderInterface', $graphCompiler);
    }

    private static function source(string $relative): string
    {
        $path = \str_starts_with($relative, '../../')
            ? __DIR__ . '/' . $relative
            : __DIR__ . '/' . $relative;
        $source = \file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    private static function between(string $source, string $start, string $end): string
    {
        $from = \strpos($source, $start);
        self::assertIsInt($from);
        $to = \strpos($source, $end, $from + \strlen($start));

        return $to === false
            ? \substr($source, $from)
            : \substr($source, $from, $to - $from);
    }
}
