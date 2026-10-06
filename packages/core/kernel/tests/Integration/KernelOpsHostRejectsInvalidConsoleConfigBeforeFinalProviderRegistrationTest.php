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

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValidationViolation;
use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use PHPUnit\Framework\TestCase;

final class KernelOpsHostRejectsInvalidConsoleConfigBeforeFinalProviderRegistrationTest extends TestCase
{
    public function testFailedConsoleValidationPrecedesProviderPlanningAndFinalContainerBuild(): void
    {
        $validation = ConfigValidationResult::failure([
            new ConfigValidationViolation(
                root: 'kernel',
                path: 'fixture',
                reason: 'invalid',
                expected: 'map',
                actualType: 'string',
            ),
        ]);
        $exception = ConfigInvalidException::fromValidationResult($validation);

        self::assertSame(ConfigInvalidException::REASON_VALIDATION_FAILED, $exception->reason());
        self::assertSame(1, \count($exception->violations()));

        $source = self::source();

        self::assertSame(1, \substr_count($source, '$configKernel->compile('));
        self::assertStringNotContainsString('ConfigValidator', $source);
        self::assertMatchesRegularExpression(
            '/\$compiledConfig = \$configKernel->compile\(.*?\);\s*' .
            '\$validation = \$compiledConfig\[\'validation\'] \?\? null;.*?' .
            'if \(\$validation->isFailure\(\)\) \{\s*' .
            'throw ConfigInvalidException::fromValidationResult\(\$validation\);\s*}/s',
            $source,
        );

        $compile = \strpos($source, '$compiledConfig = $configKernel->compile(');
        $validationCheck = \strpos($source, 'if ($validation->isFailure())');
        $conversion = \strpos($source, 'ConfigInvalidException::fromValidationResult($validation)');
        $providerPlan = \strpos($source, '$providerPlan = $providerPlanResolver->resolve(');
        $sourceProviders = \strpos($source, '$sourceProviders = self::sourceProviders($providerPlan)');
        $finalBuilder = \strpos($source, '$finalBuilder = new ContainerBuilder(');
        $finalBuild = \strpos($source, '$container = $finalBuilder->build()');

        foreach (
            [
                $compile,
                $validationCheck,
                $conversion,
                $providerPlan,
                $sourceProviders,
                $finalBuilder,
                $finalBuild,
            ] as $position
        ) {
            self::assertIsInt($position);
        }

        self::assertTrue($compile < $validationCheck);
        self::assertTrue($validationCheck < $conversion);
        self::assertTrue($conversion < $providerPlan);
        self::assertTrue($providerPlan < $sourceProviders);
        self::assertTrue($sourceProviders < $finalBuilder);
        self::assertTrue($finalBuilder < $finalBuild);
    }

    public function testInvalidConsolePhaseBConfigActuallyFailsHostBoot(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('kernel-ops-host-invalid-console-phase-b');

        try {
            ArtifactPipelineTestSupport::writePhpReturn(
                $root . '/config/kernel.php',
                [
                    'boot' => [
                        'default_debug' => 'invalid-not-bool',
                    ],
                ],
            );

            try {
                new KernelOpsHostBooter()->boot(
                    new KernelOpsHostInput($root),
                );

                self::fail('Expected invalid console Phase-B config to fail host boot.');
            } catch (KernelOpsFailedException $exception) {
                self::assertSame(
                    KernelOpsFailedException::REASON_HOST_BOOT_FAILED,
                    $exception->reason(),
                );
                self::assertNull($exception->getPrevious());
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    private static function source(): string
    {
        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsHostBooter.php');
        self::assertIsString($source);

        return $source;
    }
}
