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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CliRequiresExplicitConsoleModuleSelectionTest extends CliIntegrationTestCase
{
    public function testInstalledCliDoesNotEnableItselfWithoutModuleSelection(): void
    {
        $manifest = self::manifest(true);
        $resolver = new ModuleGraphResolver(new TopologicalSorter());
        $selected = $resolver->resolve(
            'console',
            $manifest,
            new ModuleSelection(
                [ModuleId::fromString('core.foundation'), ModuleId::fromString('core.kernel')],
                [],
            ),
        );
        self::assertFalse($selected->hasEnabledModule('platform.cli'));
        self::assertFalse($selected->hasEnabledModule('platform.errors'));
        self::assertFalse($selected->hasEnabledModule('platform.redaction'));
    }

    public function testSelectingCliClosesOverInstalledRedactionAndMissingRedactionFails(): void
    {
        $resolver = new ModuleGraphResolver(new TopologicalSorter());
        $roots = [
            ModuleId::fromString('core.foundation'),
            ModuleId::fromString('core.kernel'),
            ModuleId::fromString('platform.cli'),
        ];
        $plan = $resolver->resolve('console', self::manifest(true), new ModuleSelection($roots, []));
        self::assertTrue($plan->hasEnabledModule('platform.cli'));
        self::assertTrue($plan->hasEnabledModule('platform.errors'));
        self::assertTrue($plan->hasEnabledModule('platform.redaction'));
        self::assertLessThan(
            \array_search(
                'platform.cli',
                \array_map(static fn ($id): string => $id->value(), $plan->topologicalOrder()),
                true,
            ),
            \array_search(
                'platform.redaction',
                \array_map(static fn ($id): string => $id->value(), $plan->topologicalOrder()),
                true,
            ),
        );
        $order = \array_map(static fn ($id): string => $id->value(), $plan->topologicalOrder());
        self::assertLessThan(
            \array_search('platform.cli', $order, true),
            \array_search('platform.errors', $order, true),
        );
        self::assertLessThan(
            \array_search('platform.errors', $order, true),
            \array_search('platform.redaction', $order, true),
        );

        $this->expectException(ModuleRequiredMissingException::class);
        $resolver->resolve('console', self::manifest(false), new ModuleSelection($roots, []));
    }

    public function testSelectingCliFailsWhenErrorsPackageIsMissing(): void
    {
        $resolver = new ModuleGraphResolver(new TopologicalSorter());
        $roots = [
            ModuleId::fromString('core.foundation'),
            ModuleId::fromString('core.kernel'),
            ModuleId::fromString('platform.cli'),
        ];

        $this->expectException(ModuleRequiredMissingException::class);
        $resolver->resolve('console', self::manifest(true, false), new ModuleSelection($roots, []));
    }

    public function testSelectingCliFailsWhenErrorsModuleIsExplicitlyExcluded(): void
    {
        $resolver = new ModuleGraphResolver(new TopologicalSorter());
        $roots = [
            ModuleId::fromString('core.foundation'),
            ModuleId::fromString('core.kernel'),
            ModuleId::fromString('platform.cli'),
        ];

        $this->expectException(ModuleConflictException::class);
        $resolver->resolve(
            'console',
            self::manifest(true),
            new ModuleSelection($roots, [ModuleId::fromString('platform.errors')]),
        );
    }

    private static function manifest(bool $withRedaction, bool $withErrors = true): ModuleManifest
    {
        $composer = \json_decode(
            (string) \file_get_contents(self::packageRoot() . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($composer);

        $requires = $composer['extra']['coretsia']['requires'] ?? null;
        self::assertSame(['core.kernel', 'platform.errors', 'platform.redaction'], $requires);

        $descriptors = [
            self::module('core.foundation'),
            self::module('core.kernel', ['core.foundation']),
            self::module('platform.cli', $requires),
        ];
        if ($withErrors) {
            $descriptors[] = self::module('platform.errors', ['core.foundation', 'platform.redaction']);
        }
        if ($withRedaction) {
            $descriptors[] = self::module('platform.redaction');
        }
        return new ModuleManifest($descriptors);
    }

    /** @param list<string> $requires */
    private static function module(string $id, array $requires = []): ModuleDescriptor
    {
        return new ModuleDescriptor(
            id: ModuleId::fromString($id),
            composerName: 'coretsia/' . \str_replace('.', '-', $id),
            packageKind: 'runtime',
            metadata: ['requires' => $requires, 'conflicts' => []],
        );
    }
}
