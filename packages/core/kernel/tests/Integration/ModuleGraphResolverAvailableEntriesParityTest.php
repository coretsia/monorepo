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

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Kernel\Module\Exception\ModuleResolutionException;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ModuleGraphResolverAvailableEntriesParityTest extends TestCase
{
    public function testInstalledManifestAndAvailableEntriesProduceSameGraph(): void
    {
        $manifest = ModeInfrastructureTestSupport::manifest([
            ModeInfrastructureTestSupport::descriptor('core.foundation'),
            ModeInfrastructureTestSupport::descriptor(
                'core.kernel',
                ['core.foundation'],
            ),
            ModeInfrastructureTestSupport::descriptor(
                'platform.worker',
                ['core.kernel'],
            ),
        ]);

        $entries = self::entriesFromManifest($manifest);

        $selection = new ModuleSelection(
            [ModuleId::fromString('platform.worker')],
            [],
        );
        $resolver = new ModuleGraphResolver(new TopologicalSorter());

        $installed = $resolver->resolve(
            'worker',
            $manifest,
            $selection,
        );
        $available = $resolver->resolveEntries(
            $entries,
            $selection,
        );

        self::assertSame(
            ModeInfrastructureTestSupport::values($installed->enabled()),
            ModeInfrastructureTestSupport::values($available['enabled']),
        );
        self::assertSame(
            ModeInfrastructureTestSupport::values(
                $installed->topologicalOrder(),
            ),
            ModeInfrastructureTestSupport::values(
                $available['topologicalOrder'],
            ),
        );
        self::assertSame(
            \array_map(
                static fn (ModulePlanEntry $entry): array => $entry->toArray(),
                \array_values($installed->modules()),
            ),
            \array_map(
                static fn (ModulePlanEntry $entry): array => $entry->toArray(),
                $available['entries'],
            ),
        );
    }

    public function testInstalledManifestAndAvailableEntriesUseSameGraphFailurePrecedence(): void
    {
        $manifest = ModeInfrastructureTestSupport::manifest([
            ModeInfrastructureTestSupport::descriptor('core.foundation'),
            ModeInfrastructureTestSupport::descriptor(
                'core.kernel',
                ['core.foundation'],
                ['platform.worker'],
            ),
            ModeInfrastructureTestSupport::descriptor(
                'platform.worker',
                ['platform.cli'],
            ),
        ]);
        $entries = self::entriesFromManifest($manifest);
        $selection = new ModuleSelection(
            [
                ModuleId::fromString('core.kernel'),
                ModuleId::fromString('platform.worker'),
            ],
            [],
        );
        $resolver = new ModuleGraphResolver(new TopologicalSorter());

        $installedFailure = self::captureGraphFailure(
            static fn () => $resolver->resolve(
                'worker',
                $manifest,
                $selection,
            ),
        );
        $availableFailure = self::captureGraphFailure(
            static fn () => $resolver->resolveEntries(
                $entries,
                $selection,
            ),
        );

        self::assertSame(
            $installedFailure::class,
            $availableFailure::class,
        );
        self::assertSame(
            $installedFailure->errorCode(),
            $availableFailure->errorCode(),
        );
        self::assertSame(
            $installedFailure->reason(),
            $availableFailure->reason(),
        );
        self::assertSame(
            $installedFailure->context(),
            $availableFailure->context(),
        );
    }

    /** @return list<ModulePlanEntry> */
    private static function entriesFromManifest(
        ModuleManifest $manifest,
    ): array {
        $entries = [];

        foreach ($manifest->modules() as $descriptor) {
            $composerName = $descriptor->composerName();
            $metadata = $descriptor->metadata();

            self::assertIsString($composerName);

            $entries[] = new ModulePlanEntry(
                $descriptor->id(),
                $composerName,
                self::moduleIds($metadata['requires'] ?? []),
                self::moduleIds($metadata['conflicts'] ?? []),
            );
        }

        return $entries;
    }

    /**
     * @param list<string> $values
     *
     * @return list<ModuleId>
     */
    private static function moduleIds(array $values): array
    {
        return \array_map(
            static fn (string $value): ModuleId => ModuleId::fromString($value),
            $values,
        );
    }

    private static function captureGraphFailure(
        \Closure $operation,
    ): ModuleResolutionException {
        try {
            $operation();
        } catch (ModuleResolutionException $exception) {
            return $exception;
        }

        self::fail('Expected module graph resolution failure.');
    }
}
