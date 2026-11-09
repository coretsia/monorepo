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

use Coretsia\Kernel\DependencySync\ProjectPackagePlan;
use PHPUnit\Framework\TestCase;

final class ProjectPackagePlanTest extends TestCase
{
    public function testCanonicalPlanIsImmutableAndEqualsStructurally(): void
    {
        $left = self::plan();
        $right = self::plan();

        self::assertTrue(
            new \ReflectionClass(ProjectPackagePlan::class)->isReadOnly(),
        );

        self::assertTrue($left->equals($right));
        self::assertSame(['web', 'worker'], $left->applications());
        self::assertSame(
            ['core.foundation', 'core.kernel', 'platform.worker'],
            $left->unionModuleIds(),
        );
        self::assertSame(
            [
                'coretsia/core-foundation' => '^0.7.0',
                'coretsia/core-kernel' => '^0.7.0',
                'coretsia/platform-worker' => '^0.7.0',
            ],
            $left->desiredRootRequirements(),
        );
    }

    public function testStructuralEqualityIncludesEveryIndependentPlanningDimension(): void
    {
        $plan = self::plan();

        $enabled = $plan->enabledModuleIdsByTarget();
        $enabled['web'] = [
            'core.foundation',
            'core.kernel',
            'platform.worker',
        ];

        $excluded = $plan->excludedModuleIdsByTarget();
        $excluded['web'] = ['platform.worker'];

        $metadata = $plan->expectedModuleMetadataById();
        $metadata['core.foundation']['conflicts'] = ['platform.worker'];

        $requirements = $plan->desiredRootRequirements();
        $requirements['coretsia/platform-worker'] = '^0.8.0';

        $variants = [
            self::copyPlan(
                $plan,
                effectivePresetsByTarget: [
                    'web' => 'express',
                    'worker' => 'enterprise',
                ],
            ),
            self::copyPlan(
                $plan,
                fixedPresetByTarget: [],
            ),
            self::copyPlan(
                $plan,
                enabledModuleIdsByTarget: $enabled,
            ),
            self::copyPlan(
                $plan,
                excludedModuleIdsByTarget: $excluded,
            ),
            self::copyPlan(
                $plan,
                expectedModuleMetadataById: $metadata,
            ),
            self::copyPlan(
                $plan,
                desiredRootRequirements: $requirements,
            ),
        ];

        foreach ($variants as $variant) {
            self::assertFalse($plan->equals($variant));
        }
    }

    public function testRejectsUnsortedApplicationsInconsistentUnionAndFixedPresetMismatch(): void
    {
        $cases = [
            static fn (): ProjectPackagePlan => new ProjectPackagePlan(
                applications: ['worker', 'web'],
                effectivePresetsByTarget: [
                    'web' => 'micro',
                    'worker' => 'enterprise',
                ],
                fixedPresetByTarget: [],
                enabledModuleIdsByTarget: [
                    'web' => ['core.kernel'],
                    'worker' => ['core.kernel'],
                ],
                excludedModuleIdsByTarget: [
                    'web' => [],
                    'worker' => [],
                ],
                unionModuleIds: ['core.kernel'],
                expectedModuleMetadataById: [
                    'core.kernel' => [
                        'composerName' => 'coretsia/core-kernel',
                        'requires' => [],
                        'conflicts' => [],
                    ],
                ],
                desiredRootRequirements: [
                    'coretsia/core-kernel' => '^0.7.0',
                ],
                bootstrapInstallationIdentity: [],
            ),
            static fn (): ProjectPackagePlan => new ProjectPackagePlan(
                applications: ['web'],
                effectivePresetsByTarget: ['web' => 'micro'],
                fixedPresetByTarget: [],
                enabledModuleIdsByTarget: ['web' => ['core.kernel']],
                excludedModuleIdsByTarget: ['web' => []],
                unionModuleIds: [],
                expectedModuleMetadataById: [],
                desiredRootRequirements: [],
                bootstrapInstallationIdentity: [],
            ),
            static fn (): ProjectPackagePlan => new ProjectPackagePlan(
                applications: ['web'],
                effectivePresetsByTarget: ['web' => 'micro'],
                fixedPresetByTarget: ['web' => 'enterprise'],
                enabledModuleIdsByTarget: ['web' => ['core.kernel']],
                excludedModuleIdsByTarget: ['web' => []],
                unionModuleIds: ['core.kernel'],
                expectedModuleMetadataById: [
                    'core.kernel' => [
                        'composerName' => 'coretsia/core-kernel',
                        'requires' => [],
                        'conflicts' => [],
                    ],
                ],
                desiredRootRequirements: [
                    'coretsia/core-kernel' => '^0.7.0',
                ],
                bootstrapInstallationIdentity: [],
            ),
        ];

        foreach ($cases as $case) {
            try {
                $case();
                self::fail('Expected invalid project package plan.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testApprovedBootstrapIdentityTracksInstallationRelevantValuesAndMembership(): void
    {
        $plan = self::plan();
        $identity = $plan->bootstrapInstallationIdentity();

        self::assertSame(
            ['appEnv', 'envSourcePolicy', 'presets', 'moduleOverrides'],
            \array_keys($identity),
        );
        self::assertSame('local', $identity['appEnv']);
        self::assertSame('strict_dotenv', $identity['envSourcePolicy']);
        self::assertSame($plan->effectivePresetsByTarget(), $identity['presets']);
        self::assertSame(
            ['worker' => ['include' => ['platform.worker'], 'exclude' => []]],
            $identity['moduleOverrides'],
        );

        $differentAppEnv = $identity;
        $differentAppEnv['appEnv'] = 'production';
        $differentPolicy = $identity;
        $differentPolicy['envSourcePolicy'] = 'allow_system';
        $differentOverrides = $identity;
        $differentOverrides['moduleOverrides']['worker']['include'] = ['platform.logging'];

        foreach ([$differentAppEnv, $differentPolicy, $differentOverrides] as $variant) {
            self::assertFalse($plan->equals(self::copyPlan($plan, bootstrapInstallationIdentity: $variant)));
        }

        $returned = $plan->bootstrapInstallationIdentity();
        $returned['presets']['web'] = 'hybrid';
        self::assertSame($identity, $plan->bootstrapInstallationIdentity());

        $workerOnly = new ProjectPackagePlan(
            applications: ['worker'],
            effectivePresetsByTarget: ['worker' => 'enterprise'],
            fixedPresetByTarget: ['worker' => 'enterprise'],
            enabledModuleIdsByTarget: ['worker' => $plan->unionModuleIds()],
            excludedModuleIdsByTarget: ['worker' => []],
            unionModuleIds: $plan->unionModuleIds(),
            expectedModuleMetadataById: $plan->expectedModuleMetadataById(),
            desiredRootRequirements: $plan->desiredRootRequirements(),
            bootstrapInstallationIdentity: [
                'appEnv' => 'local',
                'envSourcePolicy' => 'strict_dotenv',
                'presets' => ['worker' => 'enterprise'],
                'moduleOverrides' => [],
            ],
        );

        self::assertSame($plan->unionModuleIds(), $workerOnly->unionModuleIds());
        self::assertSame($plan->desiredRootRequirements(), $workerOnly->desiredRootRequirements());
        self::assertFalse($plan->equals($workerOnly));
    }

    public function testBootstrapIdentityRejectsInactiveTargetsAndUnrelatedSourceFields(): void
    {
        $plan = self::plan();
        $identity = $plan->bootstrapInstallationIdentity();
        $inactive = $identity;
        $inactive['moduleOverrides']['api'] = [
            'include' => ['platform.logging'],
            'exclude' => [],
        ];
        \ksort($inactive['moduleOverrides'], \SORT_STRING);
        $debug = $identity;
        $debug['debug'] = true;
        $cache = $identity;
        $cache['artifactsCacheDir'] = '/tmp/private-cache';
        $provenance = $identity;
        $provenance['sourceProvenance'] = ['appEnv' => 'dotenv/.env'];
        $wrongPreset = $identity;
        $wrongPreset['presets']['worker'] = 'micro';

        foreach ([$inactive, $debug, $cache, $provenance, $wrongPreset] as $invalidIdentity) {
            try {
                self::copyPlan($plan, bootstrapInstallationIdentity: $invalidIdentity);
                self::fail('Expected noncanonical bootstrap installation identity.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('project-package-plan-bootstrap-identity-invalid', $exception->getMessage());
            }
        }
    }

    /**
     * @param array<string, non-empty-string>|null $effectivePresetsByTarget
     * @param array<string, non-empty-string>|null $fixedPresetByTarget
     * @param array<string, list<string>>|null $enabledModuleIdsByTarget
     * @param array<string, list<string>>|null $excludedModuleIdsByTarget
     * @param array<string, array{
     *     composerName: non-empty-string,
     *     requires: list<string>,
     *     conflicts: list<string>,
     * }>|null $expectedModuleMetadataById
     * @param array<string, non-empty-string>|null $desiredRootRequirements
     * @param array<string,mixed>|null $bootstrapInstallationIdentity
     */
    private static function copyPlan(
        ProjectPackagePlan $plan,
        ?array $effectivePresetsByTarget = null,
        ?array $fixedPresetByTarget = null,
        ?array $enabledModuleIdsByTarget = null,
        ?array $excludedModuleIdsByTarget = null,
        ?array $expectedModuleMetadataById = null,
        ?array $desiredRootRequirements = null,
        ?array $bootstrapInstallationIdentity = null,
    ): ProjectPackagePlan {
        $identity = $bootstrapInstallationIdentity ?? $plan->bootstrapInstallationIdentity();

        if ($effectivePresetsByTarget !== null && $bootstrapInstallationIdentity === null && $identity !== []) {
            $identity['presets'] = $effectivePresetsByTarget;
        }

        return new ProjectPackagePlan(
            applications: $plan->applications(),
            effectivePresetsByTarget: $effectivePresetsByTarget ?? $plan->effectivePresetsByTarget(),
            fixedPresetByTarget: $fixedPresetByTarget ?? $plan->fixedPresetByTarget(),
            enabledModuleIdsByTarget: $enabledModuleIdsByTarget ?? $plan->enabledModuleIdsByTarget(),
            excludedModuleIdsByTarget: $excludedModuleIdsByTarget ?? $plan->excludedModuleIdsByTarget(),
            unionModuleIds: $plan->unionModuleIds(),
            expectedModuleMetadataById: $expectedModuleMetadataById ?? $plan->expectedModuleMetadataById(),
            desiredRootRequirements: $desiredRootRequirements ?? $plan->desiredRootRequirements(),
            bootstrapInstallationIdentity: $identity,
        );
    }

    private static function plan(): ProjectPackagePlan
    {
        return new ProjectPackagePlan(
            applications: ['web', 'worker'],
            effectivePresetsByTarget: [
                'web' => 'micro',
                'worker' => 'enterprise',
            ],
            fixedPresetByTarget: [
                'worker' => 'enterprise',
            ],
            enabledModuleIdsByTarget: [
                'web' => ['core.foundation', 'core.kernel'],
                'worker' => [
                    'core.foundation',
                    'core.kernel',
                    'platform.worker',
                ],
            ],
            excludedModuleIdsByTarget: [
                'web' => [],
                'worker' => [],
            ],
            unionModuleIds: [
                'core.foundation',
                'core.kernel',
                'platform.worker',
            ],
            expectedModuleMetadataById: [
                'core.foundation' => [
                    'composerName' => 'coretsia/core-foundation',
                    'requires' => [],
                    'conflicts' => [],
                ],
                'core.kernel' => [
                    'composerName' => 'coretsia/core-kernel',
                    'requires' => ['core.foundation'],
                    'conflicts' => [],
                ],
                'platform.worker' => [
                    'composerName' => 'coretsia/platform-worker',
                    'requires' => ['core.kernel'],
                    'conflicts' => [],
                ],
            ],
            desiredRootRequirements: [
                'coretsia/core-foundation' => '^0.7.0',
                'coretsia/core-kernel' => '^0.7.0',
                'coretsia/platform-worker' => '^0.7.0',
            ],
            bootstrapInstallationIdentity: [
                'appEnv' => 'local',
                'envSourcePolicy' => 'strict_dotenv',
                'presets' => [
                    'web' => 'micro',
                    'worker' => 'enterprise',
                ],
                'moduleOverrides' => [
                    'worker' => [
                        'include' => ['platform.worker'],
                        'exclude' => [],
                    ],
                ],
            ],
        );
    }
}
