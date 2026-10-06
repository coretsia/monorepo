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
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class KernelOpsHashInvalidConfigDoesNotBuildGraphTest extends TestCase
{
    public function testFailedValidationReturnsHandledErrorBeforeGraphOrFingerprintWork(): void
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

        $facade = new ReflectionClass(KernelOpsFacade::class)->newInstanceWithoutConstructor();
        $handled = new ReflectionMethod(KernelOpsFacade::class, 'handledResult');
        $result = $handled->invoke(
            $facade,
            'config.hash',
            'api',
            'test',
            ConfigInvalidException::REASON_VALIDATION_FAILED,
            [],
        );

        self::assertSame('handled_error', $result->outcome());
        self::assertSame('config-validation-failed', $result->reason());
        self::assertTrue($validation->isFailure());

        $source = self::methodSource();

        self::assertSame(1, \substr_count($source, '$this->configKernel->compile('));
        self::assertStringNotContainsString('ConfigValidator', $source);
        self::assertStringNotContainsString('ConfigInvalidException::fromValidationResult', $source);
        self::assertMatchesRegularExpression(
            '/if \(\$validation->isFailure\(\)\) \{\s*' .
            'return \$this->handledResult\(/s',
            $source,
        );

        $validationBranch = \strpos($source, 'if ($validation->isFailure())');
        $graph = \strpos($source, '$this->runtimeContainerGraphCompiler->compile(');
        $input = \strpos($source, '$this->configFingerprintInputBuilder->build(');
        $fingerprint = \strpos($source, '$this->fingerprintCalculator->calculate(');

        foreach ([$validationBranch, $graph, $input, $fingerprint] as $position) {
            self::assertIsInt($position);
        }

        self::assertTrue($validationBranch < $graph);
        self::assertTrue($graph < $input);
        self::assertTrue($input < $fingerprint);
    }

    public function testPublicHashStopsOnTargetValidationFailure(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('kernel-ops-hash-invalid-target-config');

        try {
            ArtifactPipelineTestSupport::writePhpReturn(
                $root . '/apps/api/config/kernel.php',
                [
                    'boot' => [
                        'default_debug' => 'invalid-not-bool',
                    ],
                ],
            );

            $container = new KernelOpsHostBooter()->boot(
                new KernelOpsHostInput($root),
            );

            $ops = $container->get(KernelOpsInterface::class);

            self::assertInstanceOf(
                KernelOpsInterface::class,
                $ops,
            );

            $result = $ops->hashConfig(
                new KernelOpsRequest('api'),
            );

            self::assertSame(
                'handled_error',
                $result->outcome(),
            );
            self::assertSame(
                'config-validation-failed',
                $result->reason(),
            );
            self::assertSame(
                'api',
                $result->appTarget(),
            );
            self::assertSame(
                [],
                $result->data(),
            );

            self::assertDirectoryDoesNotExist(
                $root . '/var/cache/api',
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
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
