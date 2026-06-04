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
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\Warning\ModuleOptionalMissingWarning;
use PHPUnit\Framework\TestCase;

final class ModulePlanDoesNotExportFilesystemPathsContractTest extends TestCase
{
    public function testModulePlanExportDoesNotContainFilesystemPathKeysOrPathLikeValues(): void
    {
        $plan = new ModulePlan(
            app: 'api',
            preset: 'micro',
            enabled: [
                self::moduleId('core.foundation'),
                self::moduleId('core.kernel'),
                self::moduleId('platform.cli'),
            ],
            disabled: [
                self::moduleId('platform.http'),
            ],
            optionalMissing: [
                self::moduleId('platform.logging'),
            ],
            topologicalOrder: [
                self::moduleId('core.foundation'),
                self::moduleId('platform.cli'),
                self::moduleId('core.kernel'),
            ],
            modules: [
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.foundation'),
                    composerName: 'coretsia/core-foundation',
                ),
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
                ),
            ],
            warnings: [
                ModuleOptionalMissingWarning::forPresetOptionalModule(
                    moduleId: self::moduleId('platform.logging'),
                    preset: 'micro',
                ),
            ],
        );

        self::assertNoForbiddenPathKeysOrValues($plan->toArray());
    }

    private static function moduleId(string $value): ModuleId
    {
        return ModuleId::fromString($value);
    }

    private static function assertNoForbiddenPathKeysOrValues(mixed $value): void
    {
        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                if (\is_string($key)) {
                    self::assertNotContains(
                        $key,
                        [
                            'skeletonRoot',
                            'appRoot',
                            'defaultsPath',
                            'overridesPath',
                            'defaultsConfigPath',
                            'path',
                            'file',
                            'filename',
                            'directory',
                            'sourceFile',
                            'sourcePath',
                            'installPath',
                            'realPath',
                            'absolutePath',
                            'filesystemPath',
                            'packagePath',
                            'vendorPath',
                            'rootPath',
                            'resourcesPath',
                            'configPath',
                        ],
                    );
                }

                self::assertNoForbiddenPathKeysOrValues($item);
            }

            return;
        }

        if (!\is_string($value)) {
            return;
        }

        self::assertStringNotContainsString('\\', $value);
        self::assertStringNotContainsString('://', $value);
        self::assertStringNotContainsString('..', $value);
        self::assertStringNotContainsString("\0", $value);

        self::assertFalse(
            self::looksLikeAbsoluteUnixPath($value),
            'ModulePlan export must not contain absolute Unix paths.',
        );

        self::assertFalse(
            self::looksLikeWindowsDrivePath($value),
            'ModulePlan export must not contain Windows drive paths.',
        );

        foreach (
            [
                'framework/packages/',
                'skeleton/',
                'vendor/',
                'resources/modes',
                'config/modes',
                '/tmp/',
                '/var/',
                '/home/',
            ] as $forbiddenFragment
        ) {
            self::assertStringNotContainsString($forbiddenFragment, $value);
        }
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
}
