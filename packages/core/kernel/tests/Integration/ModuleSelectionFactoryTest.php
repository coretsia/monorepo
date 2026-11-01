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
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ModuleSelectionFactory;
use Coretsia\Kernel\Module\ResolvedModuleOverrides;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ModuleSelectionFactoryTest extends TestCase
{
    public function testCanonicalPresetsSelectLoggingAndPermitTargetLocalExclusion(): void
    {
        $validator = new ModePresetSchemaValidator();
        $factory = new ModuleSelectionFactory(new ModuleIdSetNormalizer());
        $manifest = ModeInfrastructureTestSupport::manifest([
            ModeInfrastructureTestSupport::descriptor('core.foundation'),
            ModeInfrastructureTestSupport::descriptor('core.kernel', ['core.foundation']),
            ModeInfrastructureTestSupport::descriptor('platform.cli', [
                'core.kernel',
                'platform.errors',
                'platform.redaction',
            ]),
            ModeInfrastructureTestSupport::descriptor('platform.errors', [
                'core.foundation',
                'platform.redaction',
            ]),
            ModeInfrastructureTestSupport::descriptor('platform.logging', [
                'core.foundation',
                'core.kernel',
                'platform.redaction',
            ]),
            ModeInfrastructureTestSupport::descriptor('platform.redaction', ['core.foundation']),
            ModeInfrastructureTestSupport::descriptor('platform.worker', ['core.kernel']),
        ]);

        foreach (['micro', 'express', 'hybrid', 'enterprise'] as $name) {
            $preset = $validator->validate(
                $name,
                require \dirname(__DIR__, 2) . '/resources/modes/' . $name . '.php',
            );
            $selection = $factory->create($preset, new ResolvedModuleOverrides([], []));
            $expectedRoots = ['core.foundation', 'core.kernel', 'platform.cli', 'platform.logging'];

            if (\in_array($name, ['hybrid', 'enterprise'], true)) {
                $expectedRoots[] = 'platform.worker';
            }

            self::assertSame($expectedRoots, self::values($selection->roots()));

            $excluded = $factory->create(
                $preset,
                new ResolvedModuleOverrides(
                    include: [],
                    exclude: [ModuleId::fromString('platform.logging')],
                ),
            );
            self::assertSame(['platform.logging'], self::values($excluded->excluded()));
            self::assertSame(
                \array_values(\array_diff($expectedRoots, ['platform.logging'])),
                self::values($excluded->roots()),
            );

            $plan = new ModuleGraphResolver(new TopologicalSorter())->resolve(
                app: 'web',
                installed: $manifest,
                selection: $excluded,
            );
            $enabled = self::values($plan->enabled());
            self::assertNotContains('platform.logging', $enabled);
            self::assertContains('core.foundation', $enabled);
            self::assertContains('core.kernel', $enabled);
            self::assertContains('platform.cli', $enabled);
            self::assertContains('platform.redaction', $enabled);
        }
    }

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
            \array_map(static fn (ModuleId $id): string => $id->value(), $selection->roots()),
        );
        self::assertSame(
            ['platform.http'],
            \array_map(static fn (ModuleId $id): string => $id->value(), $selection->excluded()),
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
            \array_map(static fn (ModuleId $id): string => $id->value(), $selection->roots()),
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

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function values(array $moduleIds): array
    {
        return \array_map(static fn (ModuleId $id): string => $id->value(), $moduleIds);
    }
}
