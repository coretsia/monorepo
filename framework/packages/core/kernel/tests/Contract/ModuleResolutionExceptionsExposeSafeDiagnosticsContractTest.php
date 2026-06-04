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
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\Exception\ModuleResolutionException;
use PHPUnit\Framework\TestCase;

final class ModuleResolutionExceptionsExposeSafeDiagnosticsContractTest extends TestCase
{
    public function testResolutionExceptionDiagnosticsDoNotExposeUnsafeValues(): void
    {
        $previous = new \RuntimeException(
            'raw previous throwable message with /tmp/secret TOKEN=abc stack trace must not leak',
        );

        $exceptions = [
            ModePresetNotFoundException::forPreset('micro', $previous),
            ModePresetNotFoundException::invalidPresetName($previous),
            ModePresetInvalidException::forPreset(
                'micro',
                ModePresetInvalidException::REASON_PATH_LIKE_VALUE_FORBIDDEN,
                $previous,
            ),
            ModuleManifestInvalidException::duplicateModuleId(
                self::moduleId('core.kernel'),
                $previous,
            ),
            ModuleDiscoverySourceUnsupportedException::forSource(
                source: 'filesystem',
                allowedSources: [
                    'composer',
                ],
                previous: $previous,
            ),
            ModuleConflictException::between(
                firstModuleId: self::moduleId('core.kernel'),
                secondModuleId: self::moduleId('core.foundation'),
                previous: $previous,
            ),
            ModuleConflictException::requiredModuleDisabled(
                moduleId: self::moduleId('core.kernel'),
                disabledModuleId: self::moduleId('core.foundation'),
                previous: $previous,
            ),
            ModuleRequiredMissingException::presetRequiredModuleMissing(
                presetName: 'micro',
                missingModuleId: self::moduleId('platform.http'),
                previous: $previous,
            ),
            ModuleRequiredMissingException::dependencyRequiredModuleMissing(
                requiredByModuleId: self::moduleId('core.kernel'),
                missingModuleId: self::moduleId('platform.http'),
                previous: $previous,
            ),
            ModuleCycleDetectedException::forModules(
                [
                    self::moduleId('core.kernel'),
                    self::moduleId('core.foundation'),
                ],
                $previous,
            ),
        ];

        foreach ($exceptions as $exception) {
            self::assertInstanceOf(ModuleResolutionException::class, $exception);

            $diagnostics = [
                'errorCode' => $exception->errorCode(),
                'reason' => $exception->reason(),
                'message' => $exception->getMessage(),
                'context' => $exception->context(),
            ];

            self::assertSafeDiagnostics($diagnostics);
            self::assertStringNotContainsString('raw previous throwable message', $exception->getMessage());
            self::assertStringNotContainsString('/tmp/secret', $exception->getMessage());
            self::assertStringNotContainsString('TOKEN=abc', $exception->getMessage());
        }
    }

    public function testUnsafePresetDiagnosticValueIsRejectedBeforeExposure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('module-resolution-context-string-invalid');

        ModePresetNotFoundException::forPreset('/tmp/micro');
    }

    public function testUnsafeRequiredMissingPresetDiagnosticValueIsRejectedBeforeExposure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('module-resolution-context-string-invalid');

        ModuleRequiredMissingException::presetRequiredModuleMissing(
            presetName: 'micro preset',
            missingModuleId: self::moduleId('platform.http'),
        );
    }

    /**
     * @param array<string, mixed> $diagnostics
     */
    private static function assertSafeDiagnostics(array $diagnostics): void
    {
        self::assertSafeDiagnosticValue($diagnostics);
    }

    private static function assertSafeDiagnosticValue(mixed $value): void
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
                    self::assertSafeDiagnosticKey($key);
                }

                self::assertSafeDiagnosticValue($item);
            }

            return;
        }

        self::fail('Module resolution diagnostics must be scalar/json-like.');
    }

    private static function assertSafeDiagnosticKey(string $key): void
    {
        self::assertNotSame('', $key);
        self::assertStringNotContainsString('/', $key);
        self::assertStringNotContainsString('\\', $key);
        self::assertStringNotContainsString("\0", $key);
        self::assertStringNotContainsString('://', $key);
        self::assertStringNotContainsString('..', $key);
    }

    private static function assertSafeDiagnosticString(string $value): void
    {
        self::assertNotSame('', $value);
        self::assertStringNotContainsString('/', $value);
        self::assertStringNotContainsString('\\', $value);
        self::assertStringNotContainsString("\0", $value);
        self::assertStringNotContainsString('://', $value);
        self::assertStringNotContainsString('..', $value);
        self::assertStringNotContainsString("\r", $value);
        self::assertStringNotContainsString("\n", $value);
        self::assertStringNotContainsString("\t", $value);

        self::assertStringNotContainsString('TOKEN=', $value);
        self::assertStringNotContainsString('PASSWORD=', $value);
        self::assertStringNotContainsString('SECRET=', $value);
        self::assertStringNotContainsString('AUTH=', $value);
        self::assertStringNotContainsString('COOKIE=', $value);

        self::assertFalse(
            self::looksLikeAbsoluteUnixPath($value),
            'Diagnostics must not contain absolute Unix paths.',
        );

        self::assertFalse(
            self::looksLikeWindowsDrivePath($value),
            'Diagnostics must not contain Windows drive paths.',
        );
    }

    private static function looksLikeAbsoluteUnixPath(string $value): bool
    {
        return \str_starts_with($value, '/');
    }

    private static function looksLikeWindowsDrivePath(string $value): bool
    {
        return \strlen($value) >= 3
            && (($value[0] >= 'a' && $value[0] <= 'z') || ($value[0] >= 'A' && $value[0] <= 'Z'))
            && $value[1] === ':'
            && ($value[2] === '\\' || $value[2] === '/');
    }

    private static function moduleId(string $value): ModuleId
    {
        return ModuleId::fromString($value);
    }
}
