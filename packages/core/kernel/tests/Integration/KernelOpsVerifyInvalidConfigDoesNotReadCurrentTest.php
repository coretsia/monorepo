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

final class KernelOpsVerifyInvalidConfigDoesNotReadCurrentTest extends TestCase
{
    public function testVerifyValidationFailureIsConvertedByOperationOwnerAndSafelyMapped(): void
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

        self::assertSame('config-validation-failed', $handledReason->invoke(null, $exception));

        $operation = self::source('../../src/Artifacts/Operation/KernelArtifactOperation.php');
        $verifier = self::source('../../src/Artifacts/Verifier/CacheVerifier.php');
        $facade = self::source('../../src/Ops/KernelOpsFacade.php');

        self::assertSame(1, \substr_count($operation, '$this->cacheVerifier->verify('));
        self::assertStringNotContainsString('ConfigValidator', $operation);

        $verifyMethodStart = \strpos($verifier, 'public function verify');
        $expectedGenerationStart = \strpos($verifier, 'private function expectedGeneration');
        self::assertIsInt($verifyMethodStart);
        self::assertIsInt($expectedGenerationStart);
        $verifyMethod = \substr(
            $verifier,
            $verifyMethodStart,
            $expectedGenerationStart - $verifyMethodStart,
        );

        self::assertSame(
            1,
            \substr_count($verifyMethod, '$this->configKernel->compile('),
        );
        self::assertMatchesRegularExpression(
            '/if \(\$compiledConfig\[\'validation\']->isFailure\(\)\) \{\s*' .
            'throw ConfigInvalidException::fromValidationResult\(/s',
            $verifyMethod,
        );

        $validationBranch = \strpos($verifyMethod, 'if ($compiledConfig[\'validation\']->isFailure())');
        $conversion = \strpos($verifyMethod, 'ConfigInvalidException::fromValidationResult(');
        $currentRead = \strpos($verifyMethod, '$currentGeneration = $this->generationLocator->locate(');

        self::assertIsInt($validationBranch);
        self::assertIsInt($conversion);
        self::assertIsInt($currentRead);
        self::assertTrue($validationBranch < $conversion);
        self::assertTrue($conversion < $currentRead);

        $verifyStart = \strpos($facade, 'public function verifyCache');
        $debugStart = \strpos($facade, 'public function debugModules');
        self::assertIsInt($verifyStart);
        self::assertIsInt($debugStart);
        $verifyFacade = \substr($facade, $verifyStart, $debugStart - $verifyStart);

        self::assertStringContainsString('$this->kernelArtifactOperation->verify($input)', $verifyFacade);
        self::assertStringNotContainsString('$this->configKernel->compile(', $verifyFacade);
        self::assertStringNotContainsString('ConfigValidator', $verifyFacade);
    }

    private static function source(string $relative): string
    {
        $source = \file_get_contents(__DIR__ . '/' . $relative);
        self::assertIsString($source);

        return $source;
    }
}
