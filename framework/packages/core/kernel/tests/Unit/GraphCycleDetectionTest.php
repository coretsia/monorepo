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

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\Exception\ModuleCycleDetectedException;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;

final class GraphCycleDetectionTest extends TestCase
{
    public function testCycleDetectionThrowsDeterministicCycleException(): void
    {
        $sorter = new TopologicalSorter();

        try {
            $sorter->sort([
                new ModulePlanEntry(
                    moduleId: self::moduleId('platform.cli'),
                    composerName: 'coretsia/platform-cli',
                    requires: [
                        self::moduleId('core.kernel'),
                    ],
                ),
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.kernel'),
                    composerName: 'coretsia/core-kernel',
                    requires: [
                        self::moduleId('core.foundation'),
                    ],
                ),
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.foundation'),
                    composerName: 'coretsia/core-foundation',
                    requires: [
                        self::moduleId('platform.cli'),
                    ],
                ),
            ]);

            self::fail('Expected module cycle detection failure.');
        } catch (ModuleCycleDetectedException $exception) {
            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_CYCLE_DETECTED,
                $exception->errorCode(),
            );

            self::assertSame(
                ModuleCycleDetectedException::REASON_CYCLE_DETECTED,
                $exception->reason(),
            );

            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_CYCLE_DETECTED
                . ': '
                . ModuleCycleDetectedException::REASON_CYCLE_DETECTED,
                $exception->getMessage(),
            );

            self::assertSame(
                [
                    'moduleIds' => [
                        'core.foundation',
                        'core.kernel',
                        'platform.cli',
                    ],
                ],
                $exception->context(),
            );
        }
    }

    public function testCycleDiagnosticsAreStableAndSafe(): void
    {
        $sorter = new TopologicalSorter();

        try {
            $sorter->sort([
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.kernel'),
                    composerName: 'coretsia/core-kernel',
                    requires: [
                        self::moduleId('core.foundation'),
                    ],
                ),
                new ModulePlanEntry(
                    moduleId: self::moduleId('platform.cli'),
                    composerName: 'coretsia/platform-cli',
                    requires: [
                        self::moduleId('core.kernel'),
                    ],
                ),
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.foundation'),
                    composerName: 'coretsia/core-foundation',
                    requires: [
                        self::moduleId('platform.cli'),
                    ],
                ),
            ]);

            self::fail('Expected module cycle detection failure.');
        } catch (ModuleCycleDetectedException $exception) {
            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_CYCLE_DETECTED
                . ': '
                . ModuleCycleDetectedException::REASON_CYCLE_DETECTED,
                $exception->getMessage(),
            );

            self::assertSafeDiagnostics([
                'errorCode' => $exception->errorCode(),
                'reason' => $exception->reason(),
                'context' => $exception->context(),
            ]);
        }
    }

    public function testCycleDiagnosticsDoNotDependOnInputOrder(): void
    {
        $first = self::cycleContextFor([
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.cli'),
                composerName: 'coretsia/platform-cli',
                requires: [
                    self::moduleId('core.kernel'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('core.kernel'),
                composerName: 'coretsia/core-kernel',
                requires: [
                    self::moduleId('core.foundation'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('core.foundation'),
                composerName: 'coretsia/core-foundation',
                requires: [
                    self::moduleId('platform.cli'),
                ],
            ),
        ]);

        $second = self::cycleContextFor([
            new ModulePlanEntry(
                moduleId: self::moduleId('core.foundation'),
                composerName: 'coretsia/core-foundation',
                requires: [
                    self::moduleId('platform.cli'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('platform.cli'),
                composerName: 'coretsia/platform-cli',
                requires: [
                    self::moduleId('core.kernel'),
                ],
            ),
            new ModulePlanEntry(
                moduleId: self::moduleId('core.kernel'),
                composerName: 'coretsia/core-kernel',
                requires: [
                    self::moduleId('core.foundation'),
                ],
            ),
        ]);

        self::assertSame($first, $second);
        self::assertSame(
            [
                'moduleIds' => [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                ],
            ],
            $first,
        );
    }

    /**
     * @param list<ModulePlanEntry> $entries
     *
     * @return array<string, mixed>
     */
    private static function cycleContextFor(array $entries): array
    {
        try {
            new TopologicalSorter()->sort($entries);
        } catch (ModuleCycleDetectedException $exception) {
            return $exception->context();
        }

        self::fail('Expected module cycle detection failure.');
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
                    self::assertSafeDiagnosticString($key);
                }

                self::assertSafeDiagnosticValue($item);
            }

            return;
        }

        self::fail('Cycle diagnostics must be scalar/json-like.');
    }

    private static function assertSafeDiagnosticString(string $value): void
    {
        self::assertNotSame('', $value);
        self::assertStringNotContainsString('/', $value);
        self::assertStringNotContainsString('\\', $value);
        self::assertStringNotContainsString('://', $value);
        self::assertStringNotContainsString('..', $value);
        self::assertStringNotContainsString("\0", $value);
        self::assertStringNotContainsString("\r", $value);
        self::assertStringNotContainsString("\n", $value);
        self::assertStringNotContainsString("\t", $value);
        self::assertStringNotContainsString(' ', $value);

        self::assertFalse(
            self::looksLikeAbsoluteUnixPath($value),
            'Cycle diagnostics must not contain absolute Unix paths.',
        );

        self::assertFalse(
            self::looksLikeWindowsDrivePath($value),
            'Cycle diagnostics must not contain Windows drive paths.',
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
