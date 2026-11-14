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
use Coretsia\Kernel\Boot\BootstrapInput;
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
            $expectedCliClosure = [
                'core.foundation',
                'core.kernel',
                'platform.cli',
                'platform.errors',
                'platform.http',
                'platform.logging',
                'platform.problem-details',
                'platform.redaction',
            ];

            self::assertSame(
                $expectedCliClosure,
                $plan->enabledModuleIdsByTarget()['web'],
            );
            self::assertSame(
                $expectedCliClosure,
                $plan->enabledModuleIdsByTarget()['worker'],
            );
            self::assertSame($expectedCliClosure, $plan->unionModuleIds());

            $publicConstraint = new ReleaseInstallationCatalogLoader()->load()->publicConstraint();

            self::assertSame(
                [
                    'coretsia/core-foundation' => $publicConstraint,
                    'coretsia/core-kernel' => $publicConstraint,
                    'coretsia/platform-cli' => $publicConstraint,
                    'coretsia/platform-errors' => $publicConstraint,
                    'coretsia/platform-http' => $publicConstraint,
                    'coretsia/platform-logging' => $publicConstraint,
                    'coretsia/platform-problem-details' => $publicConstraint,
                    'coretsia/platform-redaction' => $publicConstraint,
                ],
                $plan->desiredRootRequirements(),
            );
            self::assertSame(
                [
                    'composerName' => 'coretsia/platform-cli',
                    'requires' => ['core.kernel', 'platform.errors', 'platform.redaction'],
                    'conflicts' => [],
                ],
                $plan->expectedModuleMetadataById()['platform.cli'],
            );
            self::assertSame(
                [
                    'composerName' => 'coretsia/platform-errors',
                    'requires' => ['core.foundation', 'platform.redaction'],
                    'conflicts' => [],
                ],
                $plan->expectedModuleMetadataById()['platform.errors'],
            );
            self::assertSame(
                [
                    'composerName' => 'coretsia/platform-logging',
                    'requires' => ['core.foundation', 'core.kernel', 'platform.redaction'],
                    'conflicts' => [],
                ],
                $plan->expectedModuleMetadataById()['platform.logging'],
            );
            self::assertArrayNotHasKey(
                'coretsia/platform-worker',
                $plan->desiredRootRequirements(),
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
            $runtimeConfig = ModeInfrastructureTestSupport::bootstrapConfigResolver()->resolve(
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

    public function testCanonicalPresetsHaveExactResolvedModuleClosures(): void
    {
        $root = self::projectRoot();

        try {
            foreach (['micro', 'express', 'hybrid', 'enterprise'] as $preset) {
                $plan = self::planner()->plan(
                    $root,
                    new ProjectInstallationIntent(
                        new ProjectApplicationSet([AppTarget::Web]),
                        ['web' => $preset],
                    ),
                );

                $expected = [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                    'platform.errors',
                    'platform.http',
                    'platform.logging',
                    ...($preset === 'enterprise' ? ['platform.metrics'] : []),
                    'platform.problem-details',
                    'platform.redaction',
                    ...($preset === 'enterprise' ? ['platform.tracing'] : []),
                ];

                if (\in_array($preset, ['hybrid', 'enterprise'], true)) {
                    $expected[] = 'platform.worker';
                }

                self::assertSame($expected, $plan->enabledModuleIdsByTarget()['web']);
                self::assertSame($expected, $plan->unionModuleIds());
            }
        } finally {
            self::remove($root);
        }
    }

    public function testTargetLocalLoggingExclusionPreservesOtherTargetAndRedaction(): void
    {
        $root = self::projectRoot();

        try {
            self::writeAppConfig($root, [
                'presets' => [
                    'web' => 'micro',
                    'worker' => 'enterprise',
                ],
                'moduleOverrides' => [
                    'web' => [
                        'include' => [],
                        'exclude' => ['platform.logging'],
                    ],
                ],
            ]);

            $plan = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([AppTarget::Web, AppTarget::Worker]),
                ),
            );

            self::assertSame(['platform.logging'], $plan->excludedModuleIdsByTarget()['web']);
            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                    'platform.errors',
                    'platform.http',
                    'platform.problem-details',
                    'platform.redaction',
                ],
                $plan->enabledModuleIdsByTarget()['web'],
            );
            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                    'platform.errors',
                    'platform.http',
                    'platform.logging',
                    'platform.metrics',
                    'platform.problem-details',
                    'platform.redaction',
                    'platform.tracing',
                    'platform.worker',
                ],
                $plan->enabledModuleIdsByTarget()['worker'],
            );
            self::assertSame(
                $plan->enabledModuleIdsByTarget()['worker'],
                $plan->unionModuleIds(),
            );
            self::assertArrayHasKey(
                'coretsia/platform-logging',
                $plan->desiredRootRequirements(),
            );
            self::assertContains('platform.redaction', $plan->enabledModuleIdsByTarget()['web']);
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
            self::assertNotContains('platform.logging', $plan->unionModuleIds());
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
            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                    'platform.errors',
                    'platform.http',
                    'platform.logging',
                    'platform.metrics',
                    'platform.problem-details',
                    'platform.redaction',
                    'platform.tracing',
                    'platform.worker',
                ],
                $left->unionModuleIds(),
            );
            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                    'platform.errors',
                    'platform.http',
                    'platform.logging',
                    'platform.problem-details',
                    'platform.redaction',
                ],
                $left->enabledModuleIdsByTarget()['web'],
            );
            self::assertSame(
                $left->unionModuleIds(),
                $left->enabledModuleIdsByTarget()['worker'],
            );

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

    public function testPreResolvedSnapshotIsReusedForEveryTargetAndTemporaryPreset(): void
    {
        $root = self::projectRoot();

        try {
            self::writeAppConfig($root, [
                'activeTargets' => ['web', 'worker'],
                'presets' => ['web' => 'micro', 'worker' => 'enterprise'],
            ]);
            file_put_contents($root . '/.env', "CORETSIA_BOOT_PRESET=micro\n");

            $config = require dirname(__DIR__, 2) . '/config/kernel.php';
            $sourceResolver = ModeInfrastructureTestSupport::bootstrapSourceResolver();
            $snapshot = $sourceResolver->resolveApplicationRoot($root, $config);
            $intent = new ProjectInstallationIntent(
                new ProjectApplicationSet([AppTarget::Web, AppTarget::Worker]),
            );

            file_put_contents(
                $root . '/config/app.php',
                '<?php throw new RuntimeException("unexpected-source-reload");',
            );
            file_put_contents($root . '/.env', "CORETSIA_BOOT_PRESET=INVALID\n");

            $plan = self::planner()->plan($root, $intent, $snapshot);
            self::assertSame(['web', 'worker'], $plan->applications());
            self::assertSame(['web' => 'micro', 'worker' => 'enterprise'], $plan->effectivePresetsByTarget());
            self::assertContains('platform.worker', $plan->enabledModuleIdsByTarget()['worker']);
            self::assertNotContains('platform.worker', $plan->enabledModuleIdsByTarget()['web']);

            $preview = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([AppTarget::Web, AppTarget::Worker]),
                    ['worker' => 'micro'],
                ),
                $snapshot,
            );

            self::assertSame('micro', $preview->effectivePresetsByTarget()['web']);
            self::assertSame('micro', $preview->effectivePresetsByTarget()['worker']);
            self::assertSame($plan->enabledModuleIdsByTarget()['web'], $preview->enabledModuleIdsByTarget()['web']);
            self::assertNotContains('platform.worker', $preview->unionModuleIds());
            self::assertSame(['worker' => 'micro'], $preview->fixedPresetByTarget());
        } finally {
            self::remove($root);
        }
    }

    public function testCanonicalJsonMapKeyOrderCannotChangeInstallationIdentityOrUnion(): void
    {
        $root = self::projectRoot();

        try {
            self::writeAppConfig($root, ['activeTargets' => ['worker', 'web']]);
            $intent = new ProjectInstallationIntent(
                new ProjectApplicationSet([AppTarget::Web, AppTarget::Worker]),
            );
            $planner = self::planner();

            file_put_contents(
                $root . '/.env',
                "CORETSIA_BOOT_PRESETS={\"web\":\"micro\",\"worker\":\"enterprise\"}\n"
                . "CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={\"web\":[],\"worker\":[\"platform.worker\"]}\n",
            );
            $first = $planner->plan($root, $intent);

            file_put_contents(
                $root . '/.env',
                "CORETSIA_BOOT_PRESETS={\"worker\":\"enterprise\",\"web\":\"micro\"}\n"
                . "CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={\"worker\":[\"platform.worker\"],\"web\":[]}\n",
            );
            $second = $planner->plan($root, $intent);

            self::assertTrue($first->equals($second));
            self::assertSame($first->bootstrapInstallationIdentity(), $second->bootstrapInstallationIdentity());
            self::assertSame($first->desiredRootRequirements(), $second->desiredRootRequirements());
            self::assertSame($first->unionModuleIds(), $second->unionModuleIds());
            self::assertSame(
                ['worker' => ['include' => ['platform.worker'], 'exclude' => []]],
                $first->bootstrapInstallationIdentity()['moduleOverrides'],
            );
        } finally {
            self::remove($root);
        }
    }

    public function testConfigAppPhpIsReadOncePerMultiTargetPlanningAttempt(): void
    {
        $root = self::projectRoot();
        $counter = 'coretsia_project_package_planner_source_reads';
        unset($GLOBALS[$counter]);

        try {
            file_put_contents(
                $root . '/config/app.php',
                '<?php $GLOBALS["' . $counter . '"] = ($GLOBALS["' . $counter
                . '"] ?? 0) + 1; return ["presets" => ["web" => "micro", "worker" => "enterprise"]];',
            );
            $plan = self::planner()->plan(
                $root,
                new ProjectInstallationIntent(
                    new ProjectApplicationSet([AppTarget::Web, AppTarget::Worker]),
                ),
            );

            self::assertSame(1, $GLOBALS[$counter]);
            self::assertSame(['web', 'worker'], $plan->applications());
            self::assertSame(['web' => 'micro', 'worker' => 'enterprise'], $plan->effectivePresetsByTarget());
        } finally {
            unset($GLOBALS[$counter]);
            self::remove($root);
        }
    }

    public function testInactiveTargetAndNonInstallationBootstrapChangesDoNotAlterPlanIdentity(): void
    {
        $root = self::projectRoot();

        try {
            self::writeAppConfig($root, [
                'activeTargets' => ['web'],
                'preset' => 'micro',
                'presets' => ['worker' => 'enterprise'],
                'debug' => false,
                'artifactsCacheDir' => 'var/cache_first',
            ]);
            $intent = new ProjectInstallationIntent(
                new ProjectApplicationSet([AppTarget::Web]),
            );
            $planner = self::planner();
            $first = $planner->plan($root, $intent);

            self::writeAppConfig($root, [
                'activeTargets' => ['web'],
                'preset' => 'micro',
                'presets' => ['worker' => 'hybrid'],
                'debug' => true,
                'artifactsCacheDir' => 'var/cache_second',
            ]);
            $second = $planner->plan($root, $intent);

            self::assertTrue($first->equals($second));
            self::assertSame($first->bootstrapInstallationIdentity(), $second->bootstrapInstallationIdentity());
            self::assertSame(['web' => 'micro'], $second->bootstrapInstallationIdentity()['presets']);
            self::assertSame([], $second->bootstrapInstallationIdentity()['moduleOverrides']);
            self::assertSame($first->desiredRootRequirements(), $second->desiredRootRequirements());
        } finally {
            self::remove($root);
        }
    }

    private static function planner(): ProjectPackagePlanner
    {
        $kernelRoot = \dirname(__DIR__, 2);
        $kernelConfig = require $kernelRoot . '/config/kernel.php';
        $normalizer = new ModuleIdSetNormalizer();
        $bootstrapConfigResolver = ModeInfrastructureTestSupport::bootstrapConfigResolver();
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
            $bootstrapConfigResolver->sourceResolver(),
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
