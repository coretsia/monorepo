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
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\Warning\ModuleOptionalMissingWarning;
use PHPUnit\Framework\TestCase;

final class ModulePlanShapeContractTest extends TestCase
{
    public function testModulePlanExportsCanonicalStablePayloadShape(): void
    {
        $coreFoundation = self::moduleId('core.foundation');
        $coreKernel = self::moduleId('core.kernel');
        $platformCli = self::moduleId('platform.cli');
        $platformHttp = self::moduleId('platform.http');
        $platformLogging = self::moduleId('platform.logging');
        $platformTracing = self::moduleId('platform.tracing');

        $foundationEntry = new ModulePlanEntry(
            moduleId: $coreFoundation,
            composerName: 'coretsia/core-foundation',
        );

        $kernelEntry = new ModulePlanEntry(
            moduleId: $coreKernel,
            composerName: 'coretsia/core-kernel',
            requires: [
                $coreFoundation,
            ],
        );

        $cliEntry = new ModulePlanEntry(
            moduleId: $platformCli,
            composerName: 'coretsia/platform-cli',
        );

        $plan = new ModulePlan(
            app: 'api',
            preset: 'micro',
            enabled: [
                $coreKernel,
                $platformCli,
                $coreFoundation,
            ],
            disabled: [
                $platformHttp,
            ],
            optionalMissing: [
                $platformTracing,
                $platformLogging,
            ],
            topologicalOrder: [
                $coreFoundation,
                $platformCli,
                $coreKernel,
            ],
            modules: [
                $kernelEntry,
                $cliEntry,
                $foundationEntry,
            ],
            warnings: [
                ModuleOptionalMissingWarning::forPresetOptionalModule(
                    moduleId: $platformTracing,
                    preset: 'micro',
                ),
                ModuleOptionalMissingWarning::forPresetOptionalModule(
                    moduleId: $platformLogging,
                    preset: 'micro',
                ),
            ],
        );

        $payload = $plan->toArray();

        self::assertSame(
            [
                'app',
                'disabled',
                'enabled',
                'modules',
                'optionalMissing',
                'preset',
                'schemaVersion',
                'topologicalOrder',
                'warnings',
            ],
            \array_keys($payload),
        );

        self::assertSame('api', $payload['app']);
        self::assertSame('micro', $payload['preset']);
        self::assertSame(1, $payload['schemaVersion']);

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            $payload['enabled'],
        );

        self::assertSame(
            [
                'platform.http',
            ],
            $payload['disabled'],
        );

        self::assertSame(
            [
                'platform.logging',
                'platform.tracing',
            ],
            $payload['optionalMissing'],
        );

        self::assertSame(
            [
                'core.foundation',
                'platform.cli',
                'core.kernel',
            ],
            $payload['topologicalOrder'],
        );

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            \array_keys($payload['modules']),
        );

        foreach ($payload['modules'] as $entry) {
            self::assertSame(
                [
                    'composerName',
                    'conflicts',
                    'moduleId',
                    'requires',
                ],
                \array_keys($entry),
            );
        }

        self::assertSame(
            [
                'composerName' => 'coretsia/core-foundation',
                'conflicts' => [],
                'moduleId' => 'core.foundation',
                'requires' => [],
            ],
            $payload['modules']['core.foundation'],
        );

        self::assertSame(
            [
                'composerName' => 'coretsia/core-kernel',
                'conflicts' => [],
                'moduleId' => 'core.kernel',
                'requires' => [
                    'core.foundation',
                ],
            ],
            $payload['modules']['core.kernel'],
        );

        self::assertSame(
            [
                'composerName' => 'coretsia/platform-cli',
                'conflicts' => [],
                'moduleId' => 'platform.cli',
                'requires' => [],
            ],
            $payload['modules']['platform.cli'],
        );

        self::assertSame(
            [
                [
                    'code' => ModuleErrorCodes::CORETSIA_MODULE_OPTIONAL_MISSING,
                    'moduleId' => 'platform.logging',
                    'preset' => 'micro',
                    'reason' => ModuleOptionalMissingWarning::REASON_PRESET_OPTIONAL_MODULE_MISSING,
                ],
                [
                    'code' => ModuleErrorCodes::CORETSIA_MODULE_OPTIONAL_MISSING,
                    'moduleId' => 'platform.tracing',
                    'preset' => 'micro',
                    'reason' => ModuleOptionalMissingWarning::REASON_PRESET_OPTIONAL_MODULE_MISSING,
                ],
            ],
            $payload['warnings'],
        );

        foreach ($payload['warnings'] as $warning) {
            self::assertSame(
                [
                    'code',
                    'moduleId',
                    'preset',
                    'reason',
                ],
                \array_keys($warning),
            );
        }
    }

    private static function moduleId(string $value): ModuleId
    {
        return ModuleId::fromString($value);
    }
}
