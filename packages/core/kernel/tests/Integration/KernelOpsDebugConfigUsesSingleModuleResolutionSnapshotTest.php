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
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class KernelOpsDebugConfigUsesSingleModuleResolutionSnapshotTest extends TestCase
{
    public function testDebugUsesOneResolutionPlanAndSafeExplainProjection(): void
    {
        $facadeSource = self::source('../../src/Ops/KernelOpsFacade.php');
        $debug = self::between($facadeSource, 'public function debugConfig', 'public function compileConfig');
        $prepare = self::between(
            $facadeSource,
            'private function prepareConfigOperation',
            'private function successResult',
        );

        self::assertSame(1, \substr_count($debug, '$this->prepareConfigOperation('));
        self::assertSame(1, \substr_count($debug, '$this->configKernel->compile('));
        self::assertStringContainsString(
            "modulePlan: \$prepared['moduleResolution']->plan()",
            $debug,
        );
        self::assertStringContainsString('explain: true', $debug);
        self::assertSame(1, \substr_count($prepare, '$this->moduleResolutionOrchestrator->resolve('));

        $facade = new ReflectionClass(KernelOpsFacade::class)->newInstanceWithoutConstructor();
        $success = new ReflectionMethod(KernelOpsFacade::class, 'successResult');

        $result = $success->invoke(
            $facade,
            'config.debug',
            'console',
            'test',
            [
                'explain' => [
                    'zeta' => ['path' => 'config/modes/test.php'],
                    'alpha' => ['source' => 'package-default'],
                ],
            ],
        );

        self::assertInstanceOf(OpsResult::class, $result);
        self::assertSame(['alpha', 'zeta'], \array_keys($result->data()['explain']));

        $orchestrator = self::source('../../src/Module/ModuleResolutionOrchestrator.php');

        self::assertSame(
            1,
            \substr_count($orchestrator, '$this->manifestReader->read()'),
        );

        $rawConfigValue = 'raw-config-secret-value';
        $rawEnvValue = 'raw-env-secret-value';

        $safeExplain = new ConfigExplainer()->explain(
            config: [
                'kernel' => [
                    'config_secret' => $rawConfigValue,
                    'env_secret' => $rawEnvValue,
                ],
            ],
            sources: [],
        );

        $safeResult = $success->invoke(
            $facade,
            'config.debug',
            'console',
            'test',
            ['explain' => $safeExplain],
        );

        self::assertInstanceOf(OpsResult::class, $safeResult);

        $safeSurface = \var_export($safeResult->data(), true);

        self::assertStringNotContainsString($rawConfigValue, $safeSurface);
        self::assertStringNotContainsString($rawEnvValue, $safeSurface);

        $absolute = \DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\private\\raw-config.php'
            : '/private/raw-config.php';

        try {
            $success->invoke(
                $facade,
                'config.debug',
                'console',
                'test',
                ['explain' => ['path' => $absolute, 'raw' => 'raw-secret']],
            );

            self::fail('Absolute/raw debug explain data must not cross OpsResult.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
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
