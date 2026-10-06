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
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class KernelOpsCompileInvalidConfigDoesNotPublishTest extends TestCase
{
    public function testCompileValidationFailureIsConvertedOnceAndMappedWithoutDuplicateValidation(): void
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
        $handledReason = new ReflectionMethod(KernelOpsFacade::class, 'handledReason');

        self::assertSame('config-validation-failed', $exception->reason());
        self::assertSame('config-validation-failed', $handledReason->invoke(null, $exception));

        $operation = self::source('../../src/Artifacts/Operation/KernelArtifactOperation.php');
        $compiler = self::source('../../src/Artifacts/Compiler/ArtifactCompiler.php');
        $facade = self::source('../../src/Ops/KernelOpsFacade.php');

        self::assertSame(1, \substr_count($operation, '$this->artifactCompiler->compile('));
        self::assertStringNotContainsString('ConfigValidator', $operation);

        self::assertSame(
            1,
            \substr_count($compiler, '$this->configKernel->compile('),
        );
        self::assertMatchesRegularExpression(
            '/if \(\$compiledConfig\[\'validation\']->isFailure\(\)\) \{\s*' .
            'throw ConfigInvalidException::fromValidationResult\(/s',
            $compiler,
        );

        $validationCheck = \strpos(
            $compiler,
            "if (\$compiledConfig['validation']->isFailure())",
        );
        $conversion = \strpos(
            $compiler,
            'ConfigInvalidException::fromValidationResult(',
        );
        $publication = \strpos($compiler, 'publish(');

        self::assertIsInt($validationCheck);
        self::assertIsInt($conversion);
        self::assertIsInt($publication);
        self::assertTrue($validationCheck < $conversion);
        self::assertTrue($conversion < $publication);

        $compileFacadeStart = \strpos($facade, 'public function compileConfig');
        $hashStart = \strpos($facade, 'public function hashConfig');
        self::assertIsInt($compileFacadeStart);
        self::assertIsInt($hashStart);
        $compileFacade = \substr($facade, $compileFacadeStart, $hashStart - $compileFacadeStart);

        self::assertStringContainsString('$this->kernelArtifactOperation->compile($input)', $compileFacade);
        self::assertStringNotContainsString('ConfigValidator', $compileFacade);
        self::assertStringNotContainsString('$this->configKernel->compile(', $compileFacade);
    }

    private static function source(string $relative): string
    {
        $source = \file_get_contents(__DIR__ . '/' . $relative);
        self::assertIsString($source);

        return $source;
    }
}
