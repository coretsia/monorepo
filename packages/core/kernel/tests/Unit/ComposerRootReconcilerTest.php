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

use Coretsia\Kernel\DependencySync\Composer\ComposerRootReconciler;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectPackagePlan;
use PHPUnit\Framework\TestCase;

final class ComposerRootReconcilerTest extends TestCase
{
    public function testPreservesProjectOwnedRootsAndAddsOnlyManagedRequirements(): void
    {
        $original = (object) [
            'require' => (object) [
                'php' => '^8.4',
                'ext-json' => '*',
                'coretsia/framework' => '^0.7.0',
                'acme/pkg' => '1.2.3',
            ],
            'require-dev' => (object) [
                'phpunit/phpunit' => '^12.0',
            ],
            'scripts' => (object) [
                'test' => 'phpunit',
            ],
            'extra' => (object) [
                'x' => (object) [
                    'empty' => (object) [],
                ],
            ],
        ];
        $candidate = self::copy($original);

        $result = new ComposerRootReconciler()->reconcile(
            $original,
            $candidate,
            self::plan(),
        );

        self::assertTrue($result['manifestChanged']);
        self::assertSame(
            [
                'coretsia/core-foundation' => '^0.7.0',
                'coretsia/core-kernel' => '^0.7.0',
            ],
            $result['managedRequireWrites'],
        );
        self::assertSame(
            [
                'acme/pkg' => 'require',
                'phpunit/phpunit' => 'require-dev',
            ],
            $result['protectedThirdPartyRoots'],
        );
        self::assertSame(
            '^0.7.0',
            $result['candidateDocument']->require->{'coretsia/framework'},
        );
        self::assertNotContains(
            'coretsia/framework',
            $result['candidateDocument']
                ->extra
                ->coretsia
                ->dependencySync
                ->managedRequire,
        );
        self::assertSame(
            '1.2.3',
            $result['candidateDocument']->require->{'acme/pkg'},
        );
        self::assertSame(
            '^12.0',
            $result['candidateDocument']
                ->{'require-dev'}
                ->{'phpunit/phpunit'},
        );
        self::assertSame(
            '^8.4',
            $result['candidateDocument']->require->php,
        );
        self::assertSame(
            '*',
            $result['candidateDocument']->require->{'ext-json'},
        );
        self::assertEquals(
            (object) ['test' => 'phpunit'],
            $result['candidateDocument']->scripts,
        );
        self::assertEquals(
            (object) ['empty' => (object) []],
            $result['candidateDocument']->extra->x,
        );
    }

    public function testUnmarkedCoretsiaRootIsProjectOwned(): void
    {
        $original = (object) [
            'require' => (object) [
                'php' => '^8.4',
                'coretsia/framework' => '^0.7.0',
                'coretsia/platform-worker' => '^9.9',
            ],
        ];

        $result = new ComposerRootReconciler()->reconcile(
            $original,
            self::copy($original),
            self::plan(),
        );

        self::assertSame(
            '^9.9',
            $result['candidateDocument']->require->{'coretsia/platform-worker'},
        );
        self::assertNotContains(
            'coretsia/platform-worker',
            $result['managedRequireRemovals'],
        );
        self::assertArrayNotHasKey(
            'coretsia/platform-worker',
            $result['managedRequireWrites'],
        );
        self::assertNotContains(
            'coretsia/platform-worker',
            $result['candidateDocument']
                ->extra
                ->coretsia
                ->dependencySync
                ->managedRequire,
        );
    }

    public function testDesiredPackagePresentOnlyInRequireDevFailsClosed(): void
    {
        $original = (object) [
            'require' => (object) [
                'php' => '^8.4',
                'coretsia/framework' => '^0.7.0',
            ],
            'require-dev' => (object) [
                'coretsia/core-foundation' => '^0.7.0',
            ],
        ];

        $candidate = self::copy($original);

        try {
            new ComposerRootReconciler()->reconcile(
                $original,
                $candidate,
                self::plan(),
            );
            self::fail('Expected unsupported require-dev policy.');
        } catch (DependencySyncException $exception) {
            self::assertSame(
                DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY,
                $exception->errorCode(),
            );
            self::assertEquals($original, $candidate);
        }
    }

    public function testEditedManagedConstraintFailsBeforeReconciliation(): void
    {
        $marker = (object) [
            'schemaVersion' => 1,
            'managedRequire' => ['coretsia/core-kernel'],
            'lastAppliedRequire' => (object) [
                'coretsia/core-kernel' => '^0.7.0',
            ],
        ];
        $original = (object) [
            'require' => (object) [
                'php' => '^8.4',
                'coretsia/core-kernel' => '^0.8.0',
            ],
            'extra' => (object) [
                'coretsia' => (object) [
                    'dependencySync' => $marker,
                ],
            ],
        ];
        $candidate = self::copy($original);

        try {
            new ComposerRootReconciler()->reconcile(
                $original,
                $candidate,
                self::plan(),
            );
            self::fail('Expected managed state conflict.');
        } catch (DependencySyncException $exception) {
            self::assertSame(
                DependencySyncErrorCodes::MANAGED_STATE_CONFLICT,
                $exception->errorCode(),
            );
            self::assertEquals($original, $candidate);
        }
    }

    public function testRemovesPreviouslyManagedRequirementThatIsNoLongerSelected(): void
    {
        $marker = (object) [
            'schemaVersion' => 1,
            'managedRequire' => ['coretsia/platform-worker'],
            'lastAppliedRequire' => (object) [
                'coretsia/platform-worker' => '^0.7.0',
            ],
        ];
        $original = (object) [
            'require' => (object) [
                'php' => '^8.4',
                'coretsia/framework' => '^0.7.0',
                'coretsia/platform-worker' => '^0.7.0',
            ],
            'extra' => (object) [
                'coretsia' => (object) [
                    'dependencySync' => $marker,
                ],
            ],
        ];

        $result = new ComposerRootReconciler()->reconcile(
            $original,
            self::copy($original),
            self::plan(),
        );

        self::assertSame(
            ['coretsia/platform-worker'],
            $result['managedRequireRemovals'],
        );
        self::assertFalse(
            \property_exists(
                $result['candidateDocument']->require,
                'coretsia/platform-worker',
            ),
        );
        self::assertSame(
            '^0.7.0',
            $result['candidateDocument']->require->{'coretsia/framework'},
        );
        self::assertNotContains(
            'coretsia/platform-worker',
            $result['candidateDocument']
                ->extra
                ->coretsia
                ->dependencySync
                ->managedRequire,
        );
        self::assertFalse(
            \property_exists(
                $result['candidateDocument']
                    ->extra
                    ->coretsia
                    ->dependencySync
                    ->lastAppliedRequire,
                'coretsia/platform-worker',
            ),
        );
    }

    public function testMarkerReferringToAbsentManagedRootFailsClosed(): void
    {
        $original = (object) [
            'require' => (object) [
                'php' => '^8.4',
            ],
            'extra' => (object) [
                'coretsia' => (object) [
                    'dependencySync' => (object) [
                        'schemaVersion' => 1,
                        'managedRequire' => ['coretsia/core-kernel'],
                        'lastAppliedRequire' => (object) [
                            'coretsia/core-kernel' => '^0.7.0',
                        ],
                    ],
                ],
            ],
        ];

        $candidate = self::copy($original);

        try {
            new ComposerRootReconciler()->reconcile(
                $original,
                $candidate,
                self::plan(),
            );
            self::fail('Expected invalid managed state.');
        } catch (DependencySyncException $exception) {
            self::assertSame(
                DependencySyncErrorCodes::MANAGED_STATE_INVALID,
                $exception->errorCode(),
            );
            self::assertEquals($original, $candidate);
        }
    }

    public function testEditedManagedConstraintFailsBeforeRemoval(): void
    {
        $marker = (object) [
            'schemaVersion' => 1,
            'managedRequire' => ['coretsia/platform-worker'],
            'lastAppliedRequire' => (object) [
                'coretsia/platform-worker' => '^0.7.0',
            ],
        ];
        $original = (object) [
            'require' => (object) [
                'php' => '^8.4',
                'coretsia/platform-worker' => '^0.8.0',
            ],
            'extra' => (object) [
                'coretsia' => (object) [
                    'dependencySync' => $marker,
                ],
            ],
        ];
        $candidate = self::copy($original);

        try {
            new ComposerRootReconciler()->reconcile(
                $original,
                $candidate,
                self::plan(),
            );
            self::fail('Expected managed state conflict.');
        } catch (DependencySyncException $exception) {
            self::assertSame(
                DependencySyncErrorCodes::MANAGED_STATE_CONFLICT,
                $exception->errorCode(),
            );
            self::assertEquals($original, $candidate);
        }
    }

    public function testInvalidMarkerSchemaFailsClosed(): void
    {
        $original = (object) [
            'require' => (object) [
                'php' => '^8.4',
                'coretsia/core-kernel' => '^0.7.0',
            ],
            'extra' => (object) [
                'coretsia' => (object) [
                    'dependencySync' => (object) [
                        'schemaVersion' => 2,
                        'managedRequire' => ['coretsia/core-kernel'],
                        'lastAppliedRequire' => (object) [
                            'coretsia/core-kernel' => '^0.7.0',
                        ],
                    ],
                ],
            ],
        ];
        $candidate = self::copy($original);

        try {
            new ComposerRootReconciler()->reconcile(
                $original,
                $candidate,
                self::plan(),
            );
            self::fail('Expected invalid managed state.');
        } catch (DependencySyncException $exception) {
            self::assertSame(
                DependencySyncErrorCodes::MANAGED_STATE_INVALID,
                $exception->errorCode(),
            );
            self::assertEquals($original, $candidate);
        }
    }

    private static function plan(): ProjectPackagePlan
    {
        return new ProjectPackagePlan(
            applications: ['web'],
            effectivePresetsByTarget: ['web' => 'micro'],
            fixedPresetByTarget: [],
            enabledModuleIdsByTarget: [
                'web' => ['core.foundation', 'core.kernel'],
            ],
            excludedModuleIdsByTarget: ['web' => []],
            unionModuleIds: ['core.foundation', 'core.kernel'],
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
            ],
            desiredRootRequirements: [
                'coretsia/core-foundation' => '^0.7.0',
                'coretsia/core-kernel' => '^0.7.0',
            ],
        );
    }

    private static function copy(\stdClass $value): \stdClass
    {
        $copy = \unserialize(\serialize($value));

        self::assertInstanceOf(\stdClass::class, $copy);

        return $copy;
    }
}
