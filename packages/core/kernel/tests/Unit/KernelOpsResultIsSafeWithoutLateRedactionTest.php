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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValidationViolation;
use Coretsia\Contracts\Kernel\Ops\OpsResult;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class KernelOpsResultIsSafeWithoutLateRedactionTest extends TestCase
{
    public function testEverySuccessfulOperationShapeIsSafeBeforeAnyTransportFormatting(): void
    {
        $rawSecret = 'raw-super-secret-token-value';
        $absolutePath = self::absolutePathFixture();

        $validationData = self::validationData(
            compiled: [
                'config' => [
                    'secret' => $rawSecret,
                    'source_path' => $absolutePath,
                ],
                'validationSubjects' => [
                    'unvalidated' => [
                        $rawSecret,
                        $absolutePath,
                    ],
                    'validated' => [
                        $absolutePath,
                    ],
                ],
            ],
            validation: ConfigValidationResult::success(),
        );

        $debugLowerLevel = [
            'config' => [
                'secret' => $rawSecret,
                'path' => $absolutePath,
            ],
            'explain' => [
                'kernel' => [
                    'source' => 'package-default',
                ],
            ],
            'raw_path' => $absolutePath,
            'raw_secret' => $rawSecret,
        ];

        $compileData = self::compileData(
            [
                'generationId' => 'generation-safe-001',
                'rawSecret' => $rawSecret,
                'rawPath' => $absolutePath,
                'artifacts' => [
                    [
                        'identity' => 'module-manifest@1',
                        'basename' => 'module-manifest.php',
                        'path' => $absolutePath,
                        'raw' => $rawSecret,
                    ],
                    [
                        'identity' => 'config@1',
                        'basename' => 'config.php',
                        'path' => $absolutePath,
                        'raw' => $rawSecret,
                    ],
                    [
                        'identity' => 'container@1',
                        'basename' => 'container.php',
                        'path' => $absolutePath,
                        'raw' => $rawSecret,
                    ],
                    [
                        'identity' => 'artifact-generation@1',
                        'basename' => 'generation-manifest.php',
                        'path' => $absolutePath,
                        'raw' => $rawSecret,
                    ],
                ],
            ],
        );

        $hashLowerLevel = [
            'generationId' => 'generation-safe-002',
            'fingerprintInput' => [
                'secret' => $rawSecret,
                'path' => $absolutePath,
            ],
        ];

        $verifyData = self::verifyData(
            [
                'expectedGenerationId' => 'generation-safe-expected',
                'currentGenerationId' => 'generation-safe-current',
                'outcome' => 'dirty',
                'rawSecret' => $rawSecret,
                'rawPath' => $absolutePath,
                'artifacts' => [
                    self::verifyArtifact(
                        name: 'module-manifest',
                        basename: 'module-manifest.php',
                        rawSecret: $rawSecret,
                        absolutePath: $absolutePath,
                    ),
                    self::verifyArtifact(
                        name: 'config',
                        basename: 'config.php',
                        rawSecret: $rawSecret,
                        absolutePath: $absolutePath,
                    ),
                    self::verifyArtifact(
                        name: 'container',
                        basename: 'container.php',
                        rawSecret: $rawSecret,
                        absolutePath: $absolutePath,
                    ),
                    self::verifyArtifact(
                        name: 'artifact-generation',
                        basename: 'generation-manifest.php',
                        rawSecret: $rawSecret,
                        absolutePath: $absolutePath,
                    ),
                ],
            ],
        );

        $modulesLowerLevel = [
            'rawManifest' => [
                'secret' => $rawSecret,
                'path' => $absolutePath,
            ],
            'enabled' => [
                'core.foundation',
                'core.kernel',
            ],
            'excluded' => [],
            'topological_order' => [
                'core.foundation',
                'core.kernel',
            ],
        ];

        $results = [
            self::successResult(
                operation: 'config.validate',
                data: $validationData,
            ),
            self::successResult(
                operation: 'config.debug',
                data: [
                    'explain' => $debugLowerLevel['explain'],
                ],
            ),
            self::successResult(
                operation: 'config.compile',
                data: $compileData,
            ),
            self::successResult(
                operation: 'config.hash',
                data: [
                    'generation_id' => $hashLowerLevel['generationId'],
                ],
            ),
            self::successResult(
                operation: 'cache.verify',
                data: $verifyData,
            ),
            self::successResult(
                operation: 'modules.debug',
                data: [
                    'enabled' => $modulesLowerLevel['enabled'],
                    'excluded' => $modulesLowerLevel['excluded'],
                    'topological_order' => $modulesLowerLevel['topological_order'],
                ],
            ),
        ];

        self::assertSame(
            [
                'config.validate',
                'config.debug',
                'config.compile',
                'config.hash',
                'cache.verify',
                'modules.debug',
            ],
            \array_map(
                static fn (OpsResult $result): string => $result->operation(),
                $results,
            ),
        );

        foreach ($results as $result) {
            self::assertSame('success', $result->outcome());
            self::assertResultDoesNotLeak(
                result: $result,
                forbiddenNeedles: [
                    $rawSecret,
                    $absolutePath,
                ],
            );
        }
    }

    public function testEveryHandledErrorOperationShapeIsSafeBeforeAnyTransportFormatting(): void
    {
        $rawSecret = 'raw-handled-error-secret-value';
        $absolutePath = self::absolutePathFixture();

        $failureValidation = ConfigValidationResult::failure(
            [
                new ConfigValidationViolation(
                    root: 'kernel',
                    path: 'config',
                    reason: 'invalid',
                    expected: 'map',
                    actualType: 'string',
                ),
            ],
        );

        $validationData = self::validationData(
            compiled: [
                'rawSecret' => $rawSecret,
                'rawPath' => $absolutePath,
                'validationSubjects' => [
                    'unvalidated' => [
                        $rawSecret,
                    ],
                    'validated' => [
                        $absolutePath,
                    ],
                ],
            ],
            validation: $failureValidation,
        );

        $operations = [
            'config.validate',
            'config.debug',
            'config.compile',
            'config.hash',
            'cache.verify',
            'modules.debug',
        ];

        $results = [];

        foreach ($operations as $operation) {
            $results[] = self::handledResult(
                operation: $operation,
                data: $operation === 'config.validate'
                    ? $validationData
                    : [],
            );
        }

        foreach ($results as $index => $result) {
            self::assertSame($operations[$index], $result->operation());
            self::assertSame('handled_error', $result->outcome());
            self::assertSame('fixture-handled', $result->reason());
            self::assertResultDoesNotLeak(
                result: $result,
                forbiddenNeedles: [
                    $rawSecret,
                    $absolutePath,
                ],
            );
        }
    }

    public function testFacadeHasNoLateRedactionOrCliOutputDependency(): void
    {
        $path = \realpath(__DIR__ . '/../../src/Ops/KernelOpsFacade.php');

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);
        self::assertStringNotContainsString('SensitiveDataRedactorInterface', $source);
        self::assertStringNotContainsString('Coretsia\\Platform\\', $source);
        self::assertStringNotContainsString('Coretsia\\Contracts\\Cli\\', $source);
        self::assertStringNotContainsString('Symfony\\Component\\Console\\', $source);
        self::assertStringNotContainsString('OutputInterface', $source);
        self::assertDoesNotMatchRegularExpression(
            '/\b[A-Za-z_][A-Za-z0-9_]*Formatter[A-Za-z0-9_]*\b/',
            $source,
        );
        self::assertDoesNotMatchRegularExpression(
            '/(?:->get|::service)\s*\([^;]*SensitiveDataRedactorInterface/is',
            $source,
        );
    }

    /**
     * @param array<string,mixed> $compiled
     *
     * @return array<string,mixed>
     */
    private static function validationData(
        array $compiled,
        ConfigValidationResult $validation,
    ): array {
        $method = new ReflectionMethod(KernelOpsFacade::class, 'validationData');
        $data = $method->invoke(null, $compiled, $validation);

        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    private static function compileData(array $result): array
    {
        $method = new ReflectionMethod(KernelOpsFacade::class, 'compileData');
        $data = $method->invoke(null, $result);

        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    private static function verifyData(array $result): array
    {
        $method = new ReflectionMethod(KernelOpsFacade::class, 'verifyData');
        $data = $method->invoke(null, $result);

        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function successResult(
        string $operation,
        array $data,
    ): OpsResult {
        $facade = self::facadeWithoutConstructor();
        $method = new ReflectionMethod(KernelOpsFacade::class, 'successResult');
        $result = $method->invoke(
            $facade,
            $operation,
            'console',
            'test',
            $data,
        );

        self::assertInstanceOf(OpsResult::class, $result);

        return $result;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function handledResult(
        string $operation,
        array $data,
    ): OpsResult {
        $facade = self::facadeWithoutConstructor();
        $method = new ReflectionMethod(KernelOpsFacade::class, 'handledResult');
        $result = $method->invoke(
            $facade,
            $operation,
            'console',
            'test',
            'fixture-handled',
            $data,
        );

        self::assertInstanceOf(OpsResult::class, $result);

        return $result;
    }

    private static function facadeWithoutConstructor(): KernelOpsFacade
    {
        $facade = (new ReflectionClass(KernelOpsFacade::class))
            ->newInstanceWithoutConstructor();

        self::assertInstanceOf(KernelOpsFacade::class, $facade);

        return $facade;
    }

    /**
     * @return array<string,mixed>
     */
    private static function verifyArtifact(
        string $name,
        string $basename,
        string $rawSecret,
        string $absolutePath,
    ): array {
        return [
            'name' => $name,
            'basename' => $basename,
            'status' => 'dirty',
            'reason' => 'changed',
            'expectedBytes' => 100,
            'existingBytes' => 90,
            'path' => $absolutePath,
            'raw' => $rawSecret,
            'explain' => [
                'path' => $absolutePath,
                'value' => $rawSecret,
            ],
        ];
    }

    /**
     * @param list<string> $forbiddenNeedles
     */
    private static function assertResultDoesNotLeak(
        OpsResult $result,
        array $forbiddenNeedles,
    ): void {
        $surface = \var_export(
            [
                'operation' => $result->operation(),
                'appTarget' => $result->appTarget(),
                'preset' => $result->preset(),
                'outcome' => $result->outcome(),
                'reason' => $result->reason(),
                'data' => $result->data(),
            ],
            true,
        );

        foreach ($forbiddenNeedles as $needle) {
            self::assertStringNotContainsString($needle, $surface);
        }
    }

    private static function absolutePathFixture(): string
    {
        return \DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\private\\kernel-ops\\secret-config.php'
            : '/private/kernel-ops/secret-config.php';
    }
}
