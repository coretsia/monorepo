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

final class KernelOpsHashUsesSingleModuleResolutionSnapshotTest extends TestCase
{
    public function testHashUsesOnePreparedModuleResolutionAndFingerprintCalculatorDoesNotDiscoverModules(): void
    {
        $facade = self::source('../../src/Ops/KernelOpsFacade.php');
        $calculator = self::source('../../src/Artifacts/Fingerprint/FingerprintCalculator.php');
        $orchestrator = self::source('../../src/Module/ModuleResolutionOrchestrator.php');

        $hash = self::between($facade, 'public function hashConfig', 'public function verifyCache');

        self::assertSame(1, \substr_count($hash, '$this->prepareConfigOperation('));
        self::assertStringContainsString(
            "moduleResolution: \$prepared['moduleResolution']",
            $hash,
        );
        self::assertSame(
            1,
            \substr_count($orchestrator, '$this->manifestReader->read()'),
        );
        self::assertStringContainsString(
            "modulePlan: \$prepared['moduleResolution']->plan()",
            $hash,
        );
        self::assertSame(1, \substr_count($hash, '$this->runtimeContainerGraphCompiler->compile('));
        self::assertSame(1, \substr_count($hash, '$this->configFingerprintInputBuilder->build('));
        self::assertSame(1, \substr_count($hash, '$this->fingerprintCalculator->calculate('));

        $prepare = self::between($facade, 'private function prepareConfigOperation', 'private function successResult');
        self::assertSame(1, \substr_count($prepare, '$this->moduleResolutionOrchestrator->resolve('));

        self::assertStringNotContainsString('ModuleResolutionOrchestrator', $calculator);
        self::assertStringNotContainsString('ManifestReaderInterface', $calculator);
        self::assertStringNotContainsString('ComposerManifestReader', $calculator);
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
