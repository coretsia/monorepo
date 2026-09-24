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

use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;

final class ModuleGraphResolverRejectsExcludedDependencyTest extends TestCase
{
    public function testDirectRequiredDependencyExcludedEvenWhenNotInstalled(): void
    {
        $kernel = ModuleId::fromString('core.kernel');
        $http = ModuleId::fromString('platform.http');
        $manifest = new ModuleManifest([
            self::descriptor('core.kernel', ['platform.http']),
        ]);
        try {
            new ModuleGraphResolver(new TopologicalSorter())->resolve(
                app: 'api',
                installed: $manifest,
                selection: new ModuleSelection(roots: [$kernel], excluded: [$http]),
            );
            self::fail('Expected excluded dependency to fail before required-missing diagnosis.');
        } catch (ModuleConflictException $exception) {
            self::assertSame(ModuleErrorCodes::CORETSIA_MODULE_CONFLICT, $exception->errorCode());
            self::assertSame(ModuleConflictException::REASON_DEPENDENCY_EXCLUDED, $exception->reason());
            self::assertSame(
                ['excludedModuleId' => 'platform.http', 'requiredByModuleId' => 'core.kernel'],
                $exception->context(),
            );
        }
    }

    public function testTransitiveExcludedDependencyFailsDeterministically(): void
    {
        $manifest = new ModuleManifest([
            self::descriptor('core.kernel', ['core.foundation']),
            self::descriptor('core.foundation', ['platform.http']),
            self::descriptor('platform.http'),
        ]);
        try {
            new ModuleGraphResolver(new TopologicalSorter())->resolve(
                app: 'api',
                installed: $manifest,
                selection: new ModuleSelection(
                    roots: [ModuleId::fromString('core.kernel')],
                    excluded: [ModuleId::fromString('platform.http')],
                ),
            );
            self::fail('Expected transitive excluded dependency to fail.');
        } catch (ModuleConflictException $exception) {
            self::assertSame(ModuleConflictException::REASON_DEPENDENCY_EXCLUDED, $exception->reason());
            self::assertSame(
                ['excludedModuleId' => 'platform.http', 'requiredByModuleId' => 'core.foundation'],
                $exception->context(),
            );
        }
    }

    private static function descriptor(string $id, array $requires = []): ModuleDescriptor
    {
        return new ModuleDescriptor(
            id: ModuleId::fromString($id),
            composerName: 'coretsia/' . \str_replace('.', '-', $id),
            packageKind: 'runtime',
            moduleClass: null,
            capabilities: [],
            metadata: ['conflicts' => [], 'requires' => $requires],
        );
    }
}
