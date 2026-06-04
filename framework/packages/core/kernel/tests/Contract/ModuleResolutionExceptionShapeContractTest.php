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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleCycleDetectedException;
use Coretsia\Kernel\Module\Exception\ModuleDiscoverySourceUnsupportedException;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\Exception\ModuleResolutionException;
use PHPUnit\Framework\TestCase;

final class ModuleResolutionExceptionShapeContractTest extends TestCase
{
    public function testConcreteResolutionExceptionsExposeStableSafeShape(): void
    {
        $coreFoundation = self::moduleId('core.foundation');
        $coreKernel = self::moduleId('core.kernel');
        $platformHttp = self::moduleId('platform.http');

        $cases = [
            [
                'exception' => ModePresetNotFoundException::forPreset(
                    'micro',
                    new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODE_PRESET_NOT_FOUND,
                'reason' => ModePresetNotFoundException::REASON_PRESET_NOT_FOUND,
                'context' => [
                    'preset' => 'micro',
                ],
            ],
            [
                'exception' => ModePresetNotFoundException::invalidPresetName(
                    new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODE_PRESET_NOT_FOUND,
                'reason' => ModePresetNotFoundException::REASON_PRESET_NAME_INVALID,
                'context' => [
                    'preset' => 'invalid',
                ],
            ],
            [
                'exception' => ModePresetInvalidException::forPreset(
                    'micro',
                    ModePresetInvalidException::REASON_SCHEMA_VERSION_INVALID,
                    new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODE_PRESET_INVALID,
                'reason' => ModePresetInvalidException::REASON_SCHEMA_VERSION_INVALID,
                'context' => [
                    'preset' => 'micro',
                ],
            ],
            [
                'exception' => ModuleManifestInvalidException::duplicateModuleId(
                    $coreKernel,
                    new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODULE_MANIFEST_INVALID,
                'reason' => ModuleManifestInvalidException::REASON_MODULE_ID_DUPLICATE,
                'context' => [
                    'moduleId' => 'core.kernel',
                ],
            ],
            [
                'exception' => ModuleDiscoverySourceUnsupportedException::forSource(
                    source: 'filesystem',
                    allowedSources: [
                        'composer',
                    ],
                    previous: new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED,
                'reason' => ModuleDiscoverySourceUnsupportedException::REASON_DISCOVERY_SOURCE_UNSUPPORTED,
                'context' => [
                    'allowedSources' => [
                        'composer',
                    ],
                    'source' => 'filesystem',
                ],
            ],
            [
                'exception' => ModuleCycleDetectedException::forModules(
                    [
                        $coreKernel,
                        $coreFoundation,
                    ],
                    new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODULE_CYCLE_DETECTED,
                'reason' => ModuleCycleDetectedException::REASON_CYCLE_DETECTED,
                'context' => [
                    'moduleIds' => [
                        'core.foundation',
                        'core.kernel',
                    ],
                ],
            ],
            [
                'exception' => ModuleConflictException::between(
                    firstModuleId: $coreKernel,
                    secondModuleId: $coreFoundation,
                    previous: new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODULE_CONFLICT,
                'reason' => ModuleConflictException::REASON_MODULE_CONFLICT,
                'context' => [
                    'higherModuleId' => 'core.kernel',
                    'lowerModuleId' => 'core.foundation',
                ],
            ],
            [
                'exception' => ModuleConflictException::requiredModuleDisabled(
                    moduleId: $coreKernel,
                    disabledModuleId: $coreFoundation,
                    previous: new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODULE_CONFLICT,
                'reason' => ModuleConflictException::REASON_REQUIRED_MODULE_DISABLED,
                'context' => [
                    'disabledModuleId' => 'core.foundation',
                    'moduleId' => 'core.kernel',
                ],
            ],
            [
                'exception' => ModuleRequiredMissingException::presetRequiredModuleMissing(
                    presetName: 'micro',
                    missingModuleId: $platformHttp,
                    previous: new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODULE_REQUIRED_MISSING,
                'reason' => ModuleRequiredMissingException::REASON_PRESET_REQUIRED_MODULE_MISSING,
                'context' => [
                    'missingModuleId' => 'platform.http',
                    'preset' => 'micro',
                ],
            ],
            [
                'exception' => ModuleRequiredMissingException::dependencyRequiredModuleMissing(
                    requiredByModuleId: $coreKernel,
                    missingModuleId: $platformHttp,
                    previous: new \RuntimeException('previous throwable message must not leak'),
                ),
                'code' => ModuleErrorCodes::CORETSIA_MODULE_REQUIRED_MISSING,
                'reason' => ModuleRequiredMissingException::REASON_DEPENDENCY_REQUIRED_MODULE_MISSING,
                'context' => [
                    'missingModuleId' => 'platform.http',
                    'requiredByModuleId' => 'core.kernel',
                ],
            ],
        ];

        foreach ($cases as $case) {
            $exception = $case['exception'];

            self::assertInstanceOf(ModuleResolutionException::class, $exception);
            self::assertTrue(ModuleErrorCodes::has($exception->errorCode()));
            self::assertSame($case['code'], $exception->errorCode());
            self::assertSame($case['reason'], $exception->reason());
            self::assertSame($case['context'], $exception->context());
            self::assertSame(
                $case['code'] . ': ' . $case['reason'],
                $exception->getMessage(),
            );

            self::assertSafeJsonLikeContext($exception->context());
        }
    }

    public function testEveryConcreteResolutionExceptionExtendsBaseException(): void
    {
        $classes = [
            ModePresetNotFoundException::class,
            ModePresetInvalidException::class,
            ModuleManifestInvalidException::class,
            ModuleDiscoverySourceUnsupportedException::class,
            ModuleCycleDetectedException::class,
            ModuleConflictException::class,
            ModuleRequiredMissingException::class,
        ];

        foreach ($classes as $class) {
            self::assertTrue(
                \is_subclass_of($class, ModuleResolutionException::class),
                $class . ' must extend ' . ModuleResolutionException::class,
            );
        }
    }

    public function testKernelDoesNotIntroduceResolutionExceptionInterface(): void
    {
        self::assertFalse(
            \interface_exists('Coretsia\\Kernel\\Module\\Exception\\ModuleResolutionExceptionInterface'),
        );

        self::assertFalse(
            \interface_exists('Coretsia\\Kernel\\Module\\ModuleResolutionExceptionInterface'),
        );
    }

    private static function moduleId(string $value): ModuleId
    {
        return ModuleId::fromString($value);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function assertSafeJsonLikeContext(array $context): void
    {
        self::assertContextValueIsSafe($context);
    }

    private static function assertContextValueIsSafe(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return;
        }

        if (\is_string($value)) {
            self::assertSafeDiagnosticString($value);

            return;
        }

        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                if (!\array_is_list($value)) {
                    self::assertIsString($key);
                    self::assertSafeDiagnosticString($key);
                }

                self::assertContextValueIsSafe($item);
            }

            return;
        }

        self::fail('Module resolution exception context must be scalar/json-like.');
    }

    private static function assertSafeDiagnosticString(string $value): void
    {
        self::assertNotSame('', $value);
        self::assertStringNotContainsString('/', $value);
        self::assertStringNotContainsString('\\', $value);
        self::assertStringNotContainsString(' ', $value);
        self::assertStringNotContainsString("\t", $value);
        self::assertStringNotContainsString("\r", $value);
        self::assertStringNotContainsString("\n", $value);
        self::assertStringNotContainsString("\0", $value);
        self::assertStringNotContainsString('://', $value);
        self::assertStringNotContainsString('..', $value);
    }
}
