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
use Coretsia\Kernel\Module\Exception\InvalidModuleSelectionException;
use Coretsia\Kernel\Module\ModePreset;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ModuleSelectionFactory;
use Coretsia\Kernel\Module\ResolvedModuleOverrides;
use PHPUnit\Framework\TestCase;

final class ModuleSelectionFactoryTest extends TestCase
{
    public function testIncludesAddsAndExcludesNonRequiredPresetModule(): void
    {
        $preset = new ModePreset(
            1,
            'hybrid',
            null,
            required: [ModuleId::fromString('core.foundation')],
            modules: [ModuleId::fromString('platform.http'), ModuleId::fromString('core.kernel')],
        );
        $selection = new ModuleSelectionFactory(new ModuleIdSetNormalizer())->create(
            $preset,
            new ResolvedModuleOverrides(
                include: [ModuleId::fromString('platform.worker')],
                exclude: [ModuleId::fromString('platform.http')],
            ),
        );
        self::assertSame(
            ['core.foundation', 'core.kernel', 'platform.worker'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $selection->roots())
        );
        self::assertSame(
            ['platform.http'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $selection->excluded())
        );
    }

    public function testPresetIncludeUnionDoesNotTreatSameModuleAsSourceDuplicate(): void
    {
        $preset = new ModePreset(
            1,
            'micro',
            null,
            required: [ModuleId::fromString('core.kernel')],
            modules: [],
        );
        $selection = new ModuleSelectionFactory(new ModuleIdSetNormalizer())->create(
            $preset,
            new ResolvedModuleOverrides(include: [ModuleId::fromString('core.kernel')], exclude: []),
        );
        self::assertSame(
            ['core.kernel'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $selection->roots())
        );
    }

    public function testRequiredPresetModuleCannotBeExcluded(): void
    {
        $preset = new ModePreset(
            1,
            'micro',
            null,
            required: [ModuleId::fromString('core.kernel')],
            modules: [],
        );
        try {
            new ModuleSelectionFactory(new ModuleIdSetNormalizer())->create(
                $preset,
                new ResolvedModuleOverrides(include: [], exclude: [ModuleId::fromString('core.kernel')]),
            );
            self::fail('Expected required/excluded conflict.');
        } catch (InvalidModuleSelectionException $exception) {
            self::assertSame(InvalidModuleSelectionException::REASON_REQUIRED_EXCLUDED, $exception->reason());
            self::assertSame(['moduleId' => 'core.kernel'], $exception->context());
        }
    }

    public function testFactoryOwnsNoManifestOrGraphDependency(): void
    {
        $source = \file_get_contents(new \ReflectionClass(ModuleSelectionFactory::class)->getFileName());
        self::assertIsString($source);
        foreach (
            [
                'ModuleManifest',
                'ModuleGraphResolver',
                'ManifestReaderInterface',
                'ComposerInstalled',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }
}
