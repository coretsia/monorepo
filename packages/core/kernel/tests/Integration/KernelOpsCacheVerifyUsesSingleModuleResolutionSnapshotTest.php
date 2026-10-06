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

final class KernelOpsCacheVerifyUsesSingleModuleResolutionSnapshotTest extends TestCase
{
    public function testVerifyUsesExactlyOnePreparedModuleResolutionSnapshot(): void
    {
        $facade = self::source('../../src/Ops/KernelOpsFacade.php');
        $operation = self::source('../../src/Artifacts/Operation/KernelArtifactOperation.php');
        $verifier = self::source('../../src/Artifacts/Verifier/CacheVerifier.php');
        $graphCompiler = self::source('../../src/Container/RuntimeContainerGraphCompiler.php');

        $verifyFacade = self::between($facade, 'public function verifyCache', 'public function debugModules');
        $verifyOperation = self::between($operation, 'public function verify', 'private function prepare');
        $prepare = self::between($operation, 'private function prepare', "\n}");

        self::assertStringContainsString('$this->kernelArtifactOperation->verify($input)', $verifyFacade);
        self::assertStringNotContainsString('moduleResolutionOrchestrator->resolve', $verifyFacade);

        self::assertSame(1, \substr_count($verifyOperation, '$this->prepare($input)'));
        self::assertSame(1, \substr_count($prepare, '$this->moduleResolutionOrchestrator->resolve('));
        self::assertSame(1, \substr_count($prepare, '$this->bootstrapConfigResolver->resolve('));

        self::assertStringContainsString(
            "moduleResolution: \$prepared['moduleResolution']",
            $verifyOperation,
        );
        self::assertStringContainsString(
            "\$result['effectivePreset'] = \$prepared['bootstrapConfig']->preset();",
            $verifyOperation,
        );

        self::assertStringContainsString('$modulePlan = $moduleResolution->plan();', $verifier);
        self::assertStringContainsString('moduleResolution: $moduleResolution', $verifier);
        self::assertStringContainsString('modulePlan: $modulePlan', $verifier);
        self::assertStringNotContainsString('ModuleResolutionOrchestrator', $verifier);
        self::assertStringNotContainsString('ManifestReaderInterface', $verifier);

        self::assertSame(1, \substr_count($graphCompiler, '$this->providerPlanResolver->resolve('));

        $orchestrator = self::source('../../src/Module/ModuleResolutionOrchestrator.php');

        self::assertSame(
            1,
            \substr_count($orchestrator, '$this->manifestReader->read()'),
        );
        self::assertStringNotContainsString('ManifestReaderInterface', $graphCompiler);
        self::assertStringNotContainsString('providerPlanResolver', $verifier);
    }

    private static function source(string $relative): string
    {
        $source = \file_get_contents(__DIR__ . '/' . $relative);
        self::assertIsString($source);

        return $source;
    }

    private static function between(string $source, string $start, string $end): string
    {
        $from = \strpos($source, $start);
        self::assertIsInt($from);
        $to = \strpos($source, $end, $from + \strlen($start));

        return $to === false ? \substr($source, $from) : \substr($source, $from, $to - $from);
    }
}
