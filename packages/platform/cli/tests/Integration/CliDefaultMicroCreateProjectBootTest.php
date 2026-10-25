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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CliDefaultMicroCreateProjectBootTest extends CliIntegrationTestCase
{
    public function testShippedSkeletonBaselineSelectsMicroAndBootsWithoutGeneratedArtifacts(): void
    {
        $skeleton = \json_decode(
            (string) \file_get_contents(self::repositoryRoot() . '/packages/applications/skeleton/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $framework = \json_decode(
            (string) \file_get_contents(self::repositoryRoot() . '/packages/framework/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $cli = \json_decode(
            (string) \file_get_contents(self::packageRoot() . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertArrayHasKey('coretsia/framework', $skeleton['require']);
        self::assertArrayHasKey('coretsia/platform-cli', $framework['require']);
        self::assertArrayHasKey('coretsia/core-kernel', $framework['require']);
        self::assertArrayHasKey('coretsia/platform-redaction', $cli['require']);
        self::assertArrayHasKey('coretsia/platform-errors', $cli['require']);
        $errors = \json_decode(
            (string) \file_get_contents(self::repositoryRoot() . '/packages/platform/errors/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertArrayHasKey('coretsia/platform-redaction', $errors['require']);
        self::assertSame(
            'micro',
            (require self::repositoryRoot() . '/packages/core/kernel/config/kernel.php')['boot']['default_preset'],
        );
        $micro = require self::repositoryRoot() . '/packages/core/kernel/resources/modes/micro.php';
        self::assertContains('platform.cli', $micro['modules']);
        self::assertNotContains('platform.errors', $micro['modules']);
        self::assertNotContains('platform.errors', $micro['required']);

        $root = self::consumer();
        try {
            self::assertFileDoesNotExist($root . '/config/app.php');
            self::assertNoArtifacts($root);
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $plan = $host->get(ModulePlan::class);
            self::assertSame('console', $plan->app());
            foreach (['core.kernel', 'platform.cli', 'platform.errors', 'platform.redaction'] as $required) {
                self::assertTrue($plan->hasEnabledModule($required), $required);
            }
            $result = self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']);
            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame('', $result['stderr']);
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testHostImplementationDoesNotInvokeProjectDependencySyncOrArtifactRuntimeBoot(): void
    {
        $source = (string) \file_get_contents(
            self::repositoryRoot() . '/packages/core/kernel/src/Ops/KernelOpsHostBooter.php',
        );
        foreach (
            [
                'ProjectDependencySync',
                'ArtifactRuntimeBooter',
                'ModulePlanResolver()->resolve(',
                'compileArtifacts(',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        self::assertStringContainsString('$moduleResolutionOrchestrator->resolve($consoleBootstrapConfig)', $source);
    }
}
