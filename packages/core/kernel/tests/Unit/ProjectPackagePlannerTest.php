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

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\DependencySync\Catalog\ReleaseInstallationCatalogLoader;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectApplicationSet;
use Coretsia\Kernel\DependencySync\ProjectInstallationIntent;
use Coretsia\Kernel\DependencySync\ProjectPackagePlanner;
use Coretsia\Kernel\DependencySync\Verification\DependencySyncVerificationCodec;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ProjectPackagePlannerTest extends TestCase
{
    public function testUnselectedApplicationDirectoryDoesNotJoinInstallationSet(): void
    {
        $root = self::projectRoot();

        try {
            \mkdir($root . '/apps/worker', 0777, true);
            self::writeAppConfig($root, [
                'preset' => 'hybrid',
                'presets' => [
                    'web' => 'micro',
                ],
            ]);

            $plan = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([AppTarget::Web]),
                ),
            );

            self::assertSame(['web'], $plan->applications());
            self::assertSame('micro', $plan->effectivePresetsByTarget()['web']);
            self::assertNotContains(
                'platform.worker',
                $plan->unionModuleIds(),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testPerTargetPresetsPreventAutomaticGlobalPresetUnion(): void
    {
        $root = self::projectRoot();

        try {
            self::writeAppConfig($root, [
                'preset' => 'hybrid',
                'presets' => [
                    'web' => 'micro',
                    'worker' => 'express',
                ],
            ]);

            $plan = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([
                        AppTarget::Web,
                        AppTarget::Worker,
                    ]),
                ),
            );

            self::assertSame(
                [
                    'web' => 'micro',
                    'worker' => 'express',
                ],
                $plan->effectivePresetsByTarget(),
            );
            self::assertSame(
                [
                    'coretsia/core-foundation',
                    'coretsia/core-kernel',
                ],
                \array_keys($plan->desiredRootRequirements()),
            );
            self::assertNotContains(
                'platform.worker',
                $plan->unionModuleIds(),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testUndeclaredRuntimePresetOverrideDoesNotChangeInstallationPlan(): void
    {
        $root = self::projectRoot();

        try {
            self::writeAppConfig($root, [
                'preset' => 'micro',
            ]);
            self::writeCustomPreset(
                $root,
                'runtime-only',
                ['core.foundation', 'core.kernel', 'platform.worker'],
            );

            $plan = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([AppTarget::Web]),
                ),
            );

            $kernelRoot = \dirname(__DIR__, 2);
            $kernelConfig = require $kernelRoot . '/config/kernel.php';
            $normalizer = new ModuleIdSetNormalizer();
            $runtimeConfig = new BootstrapConfigResolver(
                new BootstrapOverridesLoader(),
                $normalizer,
            )->resolve(
                new BootstrapInput(
                    applicationRoot: $root,
                    appTarget: AppTarget::Web,
                    preset: 'runtime-only',
                ),
                $kernelConfig,
            );
            $runtimeSelection = ModeInfrastructureTestSupport::orchestrator(
                $kernelRoot,
                ModeInfrastructureTestSupport::manifest([]),
            )->resolveSelection($runtimeConfig);

            self::assertSame([], $plan->fixedPresetByTarget());
            self::assertSame('micro', $plan->effectivePresetsByTarget()['web']);
            self::assertNotContains(
                'platform.worker',
                $plan->unionModuleIds(),
            );
            self::assertContains(
                'platform.worker',
                ModeInfrastructureTestSupport::values($runtimeSelection->roots()),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testFixedPresetOverridesProjectPresetOnlyForDeclaredTarget(): void
    {
        $root = self::projectRoot();

        try {
            self::writeAppConfig($root, [
                'preset' => 'micro',
                'presets' => [
                    'worker' => 'micro',
                ],
            ]);

            $configBefore = \file_get_contents($root . '/config/app.php');

            self::assertIsString($configBefore);

            $plan = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([
                        AppTarget::Web,
                        AppTarget::Worker,
                    ]),
                    ['worker' => 'enterprise'],
                ),
            );

            self::assertSame(
                [
                    'web' => 'micro',
                    'worker' => 'enterprise',
                ],
                $plan->effectivePresetsByTarget(),
            );
            self::assertSame(
                ['worker' => 'enterprise'],
                $plan->fixedPresetByTarget(),
            );
            self::assertContains(
                'platform.worker',
                $plan->unionModuleIds(),
            );
            self::assertSame(
                $configBefore,
                \file_get_contents($root . '/config/app.php'),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testPerTargetExclusionDoesNotRemovePackageRequiredByAnotherTarget(): void
    {
        $root = self::projectRoot();

        try {
            self::writeAppConfig($root, [
                'presets' => [
                    'web' => 'hybrid',
                    'worker' => 'enterprise',
                ],
                'moduleOverrides' => [
                    'web' => [
                        'include' => [],
                        'exclude' => ['platform.worker'],
                    ],
                ],
            ]);

            $plan = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([
                        AppTarget::Web,
                        AppTarget::Worker,
                    ]),
                ),
            );

            self::assertSame(
                ['platform.worker'],
                $plan->excludedModuleIdsByTarget()['web'],
            );
            self::assertNotContains(
                'platform.worker',
                $plan->enabledModuleIdsByTarget()['web'],
            );
            self::assertContains(
                'platform.worker',
                $plan->enabledModuleIdsByTarget()['worker'],
            );
            self::assertContains(
                'platform.worker',
                $plan->unionModuleIds(),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testTransitiveKernelBootstrapFloorIsAccepted(): void
    {
        $root = self::projectRoot();

        try {
            self::writeCustomPreset(
                $root,
                'worker-only',
                ['platform.worker'],
            );

            $plan = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([AppTarget::Worker]),
                    ['worker' => 'worker-only'],
                ),
            );

            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.worker',
                ],
                $plan->unionModuleIds(),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testExcludedTransitiveKernelDependencyFailsBeforeComposerEffects(): void
    {
        $root = self::projectRoot();

        try {
            self::writeCustomPreset(
                $root,
                'worker-only',
                ['platform.worker'],
            );
            self::writeAppConfig($root, [
                'moduleOverrides' => [
                    'worker' => [
                        'include' => [],
                        'exclude' => ['core.kernel'],
                    ],
                ],
            ]);

            try {
                self::planner()->plan(
                    $root,
                    new ProjectInstallationIntent(
                        new ProjectApplicationSet([AppTarget::Worker]),
                        ['worker' => 'worker-only'],
                    ),
                );
                self::fail('Expected excluded required dependency failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::EXCLUDED_REQUIRED_DEPENDENCY,
                    $exception->errorCode(),
                );
            }
        } finally {
            self::remove($root);
        }
    }

    public function testCustomPresetWithUnknownModuleFailsClosed(): void
    {
        $root = self::projectRoot();

        try {
            self::writeCustomPreset(
                $root,
                'unknown-module',
                ['vendor.unknown'],
            );

            try {
                self::planner()->plan(
                    $root,
                    new ProjectInstallationIntent(
                        new ProjectApplicationSet([AppTarget::Web]),
                        ['web' => 'unknown-module'],
                    ),
                );
                self::fail('Expected unknown catalog module failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::CATALOG_MODULE_UNKNOWN,
                    $exception->errorCode(),
                );
            }
        } finally {
            self::remove($root);
        }
    }

    public function testMicroAndExpressCurrentlyProduceTheSameModuleUnion(): void
    {
        $root = self::projectRoot();

        try {
            $planner = self::planner();
            $micro = $planner->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([AppTarget::Web]),
                    ['web' => 'micro'],
                ),
            );
            $express = $planner->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([AppTarget::Web]),
                    ['web' => 'express'],
                ),
            );

            self::assertSame(
                $micro->unionModuleIds(),
                $express->unionModuleIds(),
            );
            self::assertSame(
                $micro->desiredRootRequirements(),
                $express->desiredRootRequirements(),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testPlannerSourceContainsNoImplicitTargetDiscovery(): void
    {
        $path = new \ReflectionClass(ProjectPackagePlanner::class)->getFileName();

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        foreach (
            [
                "glob('apps/",
                'scandir(',
                'AppTarget::cases()',
            ] as $needle
        ) {
            self::assertStringNotContainsString($needle, $source);
        }
    }

    public function testTargetOrderDoesNotChangeCanonicalPlanningPayload(): void
    {
        $root = self::projectRoot();

        try {
            self::writeAppConfig($root, [
                'presets' => [
                    'web' => 'micro',
                    'worker' => 'enterprise',
                ],
            ]);

            $planner = self::planner();

            $left = $planner->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([
                        AppTarget::Worker,
                        AppTarget::Web,
                    ]),
                ),
            );
            $right = $planner->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([
                        AppTarget::Web,
                        AppTarget::Worker,
                    ]),
                ),
            );

            self::assertTrue($left->equals($right));

            $identity = 'sha256:' . \str_repeat('1', 64);

            self::assertSame(
                DependencySyncVerificationCodec::encodeApprovedPlanningPayload(
                    $left,
                    $identity,
                ),
                DependencySyncVerificationCodec::encodeApprovedPlanningPayload(
                    $right,
                    $identity,
                ),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testApplicationOverrideCanRestoreKernelBootstrapFloor(): void
    {
        $root = self::projectRoot();

        try {
            self::writeCustomPreset(
                $root,
                'foundation-only',
                ['core.foundation'],
            );
            self::writeAppConfig($root, [
                'moduleOverrides' => [
                    'web' => [
                        'include' => ['core.kernel'],
                        'exclude' => [],
                    ],
                ],
            ]);

            $plan = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([AppTarget::Web]),
                    ['web' => 'foundation-only'],
                ),
            );

            self::assertSame(
                ['core.foundation', 'core.kernel'],
                $plan->unionModuleIds(),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testPresetWithoutKernelInResolvedClosureFailsBootstrapFloor(): void
    {
        $root = self::projectRoot();

        try {
            self::writeCustomPreset(
                $root,
                'foundation-only',
                ['core.foundation'],
            );

            try {
                self::planner()->plan(
                    $root,
                    new ProjectInstallationIntent(
                        new ProjectApplicationSet([AppTarget::Web]),
                        ['web' => 'foundation-only'],
                    ),
                );
                self::fail('Expected Kernel bootstrap floor failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::PRESET_POLICY_INVALID,
                    $exception->errorCode(),
                );
            }
        } finally {
            self::remove($root);
        }
    }

    private static function planner(): ProjectPackagePlanner
    {
        $kernelRoot = \dirname(__DIR__, 2);
        $kernelConfig = require $kernelRoot . '/config/kernel.php';
        $normalizer = new ModuleIdSetNormalizer();
        $bootstrapConfigResolver = new BootstrapConfigResolver(
            new BootstrapOverridesLoader(),
            $normalizer,
        );
        $graphResolver = new ModuleGraphResolver(new TopologicalSorter());
        $orchestrator = ModeInfrastructureTestSupport::orchestrator(
            $kernelRoot,
            ModeInfrastructureTestSupport::manifest([]),
        );

        return new ProjectPackagePlanner(
            $bootstrapConfigResolver,
            $orchestrator,
            $graphResolver,
            $normalizer,
            new ReleaseInstallationCatalogLoader(),
            $kernelConfig,
        );
    }

    private static function projectRoot(): string
    {
        $root = \sys_get_temp_dir()
            . '/coretsia-project-package-planner-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root . '/config/modes', 0777, true);
        \mkdir($root . '/apps', 0777, true);

        return $root;
    }

    /** @param array<string, mixed> $config */
    private static function writeAppConfig(string $root, array $config): void
    {
        \file_put_contents(
            $root . '/config/app.php',
            '<?php return ' . \var_export($config, true) . ';',
        );
    }

    /** @param list<string> $required */
    private static function writeCustomPreset(
        string $root,
        string $name,
        array $required,
    ): void {
        $payload = [
            'schemaVersion' => 1,
            'name' => $name,
            'description' => null,
            'required' => $required,
            'modules' => [],
            'featureBundles' => [],
            'metadata' => [],
        ];

        \file_put_contents(
            $root . '/config/modes/' . $name . '.php',
            '<?php return ' . \var_export($payload, true) . ';',
        );
    }

    private static function remove(string $path): void
    {
        if (\is_link($path) || \is_file($path)) {
            @\unlink($path);
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }

        @\rmdir($path);
    }
}
