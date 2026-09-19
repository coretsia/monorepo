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

final class ModulePlanWarningsAreDeterministicallySortedContractTest extends TestCase
{
    public function testOptionalMissingAndWarningsAreSortedDeterministically(): void
    {
        $plan = self::createPlan();

        self::assertSame(
            [
                'platform.logging',
                'platform.metrics',
                'platform.tracing',
            ],
            self::moduleIdsToStrings($plan->optionalMissing()),
        );

        self::assertSame(
            [
                'platform.logging',
                'platform.metrics',
                'platform.tracing',
            ],
            $plan->toArray()['optionalMissing'],
        );

        $canonicalKeys = \array_map(
            static fn (ModuleOptionalMissingWarning $warning): string => $warning->canonicalKey(),
            $plan->warnings(),
        );

        $expectedCanonicalKeys = $canonicalKeys;

        \usort(
            $expectedCanonicalKeys,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        self::assertSame($expectedCanonicalKeys, $canonicalKeys);

        self::assertSame(
            [
                'platform.logging',
                'platform.metrics',
                'platform.tracing',
            ],
            \array_map(
                static fn (array $warning): string => $warning['moduleId'],
                $plan->toArray()['warnings'],
            ),
        );
    }

    public function testOrderingDoesNotDependOnLocale(): void
    {
        $originalLocale = \setlocale(\LC_COLLATE, '0');

        try {
            $expectedPayload = null;

            foreach (self::availableCollationLocales() as $locale) {
                \setlocale(\LC_COLLATE, $locale);

                $payload = self::createPlan()->toArray();

                if ($expectedPayload === null) {
                    $expectedPayload = $payload;

                    continue;
                }

                self::assertSame($expectedPayload, $payload);
            }

            self::assertNotNull($expectedPayload);
        } finally {
            if (\is_string($originalLocale)) {
                \setlocale(\LC_COLLATE, $originalLocale);
            }
        }
    }

    private static function createPlan(): ModulePlan
    {
        return new ModulePlan(
            app: 'api',
            preset: 'micro',
            enabled: [
                self::moduleId('platform.cli'),
                self::moduleId('core.kernel'),
                self::moduleId('core.foundation'),
            ],
            disabled: [],
            optionalMissing: [
                self::moduleId('platform.tracing'),
                self::moduleId('platform.logging'),
                self::moduleId('platform.metrics'),
            ],
            topologicalOrder: [
                self::moduleId('core.foundation'),
                self::moduleId('platform.cli'),
                self::moduleId('core.kernel'),
            ],
            modules: [
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
                new ModulePlanEntry(
                    moduleId: self::moduleId('core.foundation'),
                    composerName: 'coretsia/core-foundation',
                ),
            ],
            warnings: [
                ModuleOptionalMissingWarning::forPresetOptionalModule(
                    moduleId: self::moduleId('platform.tracing'),
                    preset: 'micro',
                ),
                ModuleOptionalMissingWarning::forPresetOptionalModule(
                    moduleId: self::moduleId('platform.logging'),
                    preset: 'micro',
                ),
                ModuleOptionalMissingWarning::forPresetOptionalModule(
                    moduleId: self::moduleId('platform.metrics'),
                    preset: 'micro',
                ),
            ],
        );
    }

    /**
     * @return list<string>
     */
    private static function availableCollationLocales(): array
    {
        $candidates = [
            'C',
            'POSIX',
            'en_US.UTF-8',
            'de_DE.UTF-8',
            'uk_UA.UTF-8',
        ];

        $available = [];

        foreach ($candidates as $candidate) {
            $result = @\setlocale(\LC_COLLATE, $candidate);

            if (\is_string($result)) {
                $available[$result] = $result;
            }
        }

        $available['C'] = 'C';

        \ksort($available, \SORT_STRING);

        return \array_values($available);
    }

    private static function moduleId(string $value): ModuleId
    {
        return ModuleId::fromString($value);
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdsToStrings(array $moduleIds): array
    {
        return \array_map(
            static fn (ModuleId $moduleId): string => $moduleId->value(),
            $moduleIds,
        );
    }
}
