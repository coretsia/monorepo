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

final class KernelOpsValidateConfigUsesSingleModuleResolutionSnapshotTest extends TestCase
{
    public function testValidateUsesOneResolutionAndPassesItsPlanToConfigKernelWithoutRepeatedDiscovery(): void
    {
        $facade = self::source('../../src/Ops/KernelOpsFacade.php');
        $validate = self::between($facade, 'public function validateConfig', 'public function debugConfig');
        $prepare = self::between($facade, 'private function prepareConfigOperation', 'private function successResult');
        $orchestrator = self::source('../../src/Module/ModuleResolutionOrchestrator.php');

        self::assertSame(1, \substr_count($validate, '$this->prepareConfigOperation('));
        self::assertSame(1, \substr_count($validate, '$this->configKernel->compile('));
        self::assertStringContainsString(
            "modulePlan: \$prepared['moduleResolution']->plan()",
            $validate,
        );

        self::assertSame(1, \substr_count($prepare, '$this->moduleResolutionOrchestrator->resolve('));
        self::assertSame(1, \substr_count($prepare, '$this->configSourceLocationBuilder->build('));
        self::assertSame(
            1,
            \substr_count($orchestrator, '$this->manifestReader->read()'),
        );

        foreach (
            [
                'ManifestReaderInterface',
                'ComposerManifestReader',
                'ContainerProviderPlanResolver',
                'new ModuleResolution',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $validate);
            self::assertStringNotContainsString($forbidden, $prepare);
        }
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
