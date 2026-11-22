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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerSeedSetRejectsUnknownSeedsTest extends TestCase
{
    public function testAcceptsOnlyTheExactEntrypointOwnedSeedMap(): void
    {
        $instances = self::validInstances();
        $seeds = new RuntimeContainerSeedSet($instances);
        $expectedIds = [
            ConfigRepositoryInterface::class,
            ModulePlan::class,
            RuntimeDiagnosticsInput::class,
            RuntimePathContext::class,
        ];

        \sort($expectedIds, \SORT_STRING);

        self::assertSame($expectedIds, $seeds->ids());

        foreach ($expectedIds as $id) {
            self::assertSame(
                $instances[$id],
                $seeds->instances()[$id],
            );
        }
    }

    public function testRejectsUnknownSeedId(): void
    {
        $instances = self::validInstances();
        $instances[\stdClass::class] = new \stdClass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('runtime-container-seed-set-ids-invalid');

        new RuntimeContainerSeedSet($instances);
    }

    #[DataProvider('runtimeSeedIdProvider')]
    public function testRejectsMissingSeedId(string $runtimeSeedId): void
    {
        $instances = self::validInstances();
        unset($instances[$runtimeSeedId]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('runtime-container-seed-set-ids-invalid');

        new RuntimeContainerSeedSet($instances);
    }

    public function testRejectsListShapedSeedInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('runtime-container-seed-set-ids-invalid');

        new RuntimeContainerSeedSet(
            \array_values(self::validInstances()),
        );
    }

    #[DataProvider('runtimeSeedIdProvider')]
    public function testRejectsObjectThatDoesNotMatchItsServiceId(string $runtimeSeedId): void
    {
        $instances = self::validInstances();
        $instances[$runtimeSeedId] = new \stdClass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('runtime-container-seed-set-instance-invalid');

        new RuntimeContainerSeedSet($instances);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function runtimeSeedIdProvider(): iterable
    {
        yield 'config repository' => [ConfigRepositoryInterface::class];
        yield 'module plan' => [ModulePlan::class];
        yield 'runtime diagnostics input' => [RuntimeDiagnosticsInput::class];
        yield 'runtime path context' => [RuntimePathContext::class];
    }

    /**
     * @return array<class-string, object>
     */
    private static function validInstances(): array
    {
        $moduleId = ModuleId::fromString('core.kernel');
        $root = self::runtimeRoot();

        return [
            ConfigRepositoryInterface::class => new ArrayConfigRepository(
                config: [
                    'kernel' => [
                        'runtime' => [],
                    ],
                ],
                sources: [],
            ),
            ModulePlan::class => new ModulePlan(
                app: 'worker',
                enabled: [
                    $moduleId,
                ],
                excluded: [],
                topologicalOrder: [
                    $moduleId,
                ],
                modules: [
                    new ModulePlanEntry(
                        moduleId: $moduleId,
                        composerName: 'coretsia/core-kernel',
                    ),
                ],
            ),
            RuntimePathContext::class => new RuntimePathContext(
                applicationRoot: $root,
                artifactRoot: $root . '/var/cache/worker',
            ),
            RuntimeDiagnosticsInput::class => new RuntimeDiagnosticsInput(
                appEnv: 'prod',
                artifactGenerationLoaded: false,
            ),
        ];
    }

    private static function runtimeRoot(): string
    {
        return \rtrim(
            \str_replace('\\', '/', \sys_get_temp_dir()),
            '/',
        ) . '/coretsia-runtime-seed-set';
    }
}
