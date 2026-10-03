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

use Composer\InstalledVersions;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\DependencySync\Composer\ComposerManifestStore;
use Coretsia\Kernel\DependencySync\Composer\ComposerRootReconciler;
use Coretsia\Kernel\DependencySync\Composer\ComposerSyncCoordinator;
use Coretsia\Kernel\DependencySync\Composer\InstalledProjectVerifier;
use Coretsia\Kernel\DependencySync\DependencySyncExecutionPolicy;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\Process\DependencySyncProcessRunner;
use Coretsia\Kernel\DependencySync\ProjectApplicationSet;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;
use Coretsia\Kernel\DependencySync\ProjectInstallationIntent;
use Coretsia\Kernel\DependencySync\ProjectPackagePlan;
use Coretsia\Kernel\DependencySync\ProjectPackagePlanner;
use PHPUnit\Framework\TestCase;

final class ComposerSyncCoordinatorTest extends TestCase
{
    public function testCoordinatorUsesProjectLockAndProcessRunnerBoundaries(): void
    {
        $source = self::source();

        foreach (
            [
                'exclusiveNonBlocking',
                'PROJECT_SYNC_LOCKED',
                'PROJECT_SYNC_LOCK_FAILED',
                'runComposer',
                'runPhpScript',
            ] as $needle
        ) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testReviewDoesNotAcquireProjectLockOrInvokeComposer(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator($root);
            $intent = self::intent();
            $plan = self::plan($coordinator, $root, $intent);
            $beforeManifest = \file_get_contents($root . '/composer.json');

            self::assertIsString($beforeManifest);

            $result = $coordinator->synchronize(
                $root,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: false),
            );

            self::assertFalse($result->changed());
            self::assertSame([], self::composerCalls($root));
            self::assertSame(
                $beforeManifest,
                \file_get_contents($root . '/composer.json'),
            );
            self::assertDirectoryDoesNotExist($root . '/var');
        } finally {
            self::remove($root);
        }
    }

    public function testHealthyUnchangedManifestRunsOnlyLockCurrentValidation(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator($root);
            $intent = self::intent();
            $plan = self::plan($coordinator, $root, $intent);

            self::writeManagedManifest($root, $plan);
            self::writeLock($root);
            $beforeState = self::composerState($root);
            self::writeControl(
                $root,
                [
                    'lockCurrent' => [true],
                    'verification' => ['ok'],
                ],
            );

            $result = $coordinator->synchronize(
                $root,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: true),
            );

            self::assertFalse($result->changed());
            self::assertSame(
                [
                    [
                        'validate',
                        '--no-check-publish',
                        '--check-lock',
                        '--no-interaction',
                        '--no-plugins',
                        '--no-scripts',
                        '--no-ansi',
                    ],
                ],
                self::composerCalls($root),
            );
            self::assertSame(1, self::verifierCount($root));
            self::assertComposerStateUnchanged($root, $beforeState);
            self::assertDirectoryDoesNotExist($root . '/vendor');
        } finally {
            self::remove($root);
        }
    }

    public function testIncompleteVendorRequiresExplicitRepair(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator($root);
            $intent = self::intent();
            $plan = self::plan($coordinator, $root, $intent);

            self::writeManagedManifest($root, $plan);
            self::writeLock($root);
            self::writeControl(
                $root,
                [
                    'lockCurrent' => [true],
                    'verification' => [
                        DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE,
                    ],
                ],
            );

            try {
                $coordinator->synchronize(
                    $root,
                    $intent,
                    $plan,
                    new DependencySyncExecutionPolicy(
                        apply: true,
                        allowRepair: false,
                    ),
                );
                self::fail('Expected repair policy rejection.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY,
                    $exception->errorCode(),
                );
            }

            $calls = self::composerCalls($root);

            self::assertSame(1, self::commandCount($calls, 'validate'));
            self::assertSame(0, self::commandCount($calls, 'install'));
            self::assertSame(0, self::commandCount($calls, 'update'));
            self::assertSame(1, self::verifierCount($root));
        } finally {
            self::remove($root);
        }
    }

    public function testIncompleteVendorRunsExactlyOneInstallWhenRepairIsAuthorized(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator($root);
            $intent = self::intent();
            $plan = self::plan($coordinator, $root, $intent);

            self::writeManagedManifest($root, $plan);
            self::writeLock($root);
            self::writeControl(
                $root,
                [
                    'lockCurrent' => [true, true],
                    'verification' => [
                        DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE,
                        'ok',
                    ],
                    'effectExitCodes' => [0],
                ],
            );

            $result = $coordinator->synchronize(
                $root,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(
                    apply: true,
                    allowRepair: true,
                ),
            );

            self::assertTrue($result->changed());

            $calls = self::composerCalls($root);

            self::assertSame('validate', $calls[0][0] ?? null);
            self::assertSame('install', $calls[1][0] ?? null);
            self::assertSame('validate', $calls[2][0] ?? null);
            self::assertSame(1, self::commandCount($calls, 'install'));
            self::assertSame(2, self::verifierCount($root));
        } finally {
            self::remove($root);
        }
    }

    public function testSemanticVerificationFailureIsNeverTreatedAsRepairableVendorDamage(): void
    {
        foreach (
            [
                DependencySyncErrorCodes::INSTALLED_PLAN_MISMATCH,
                DependencySyncErrorCodes::INSTALLED_METADATA_INVALID,
            ] as $errorCode
        ) {
            $root = self::projectRoot();

            try {
                $coordinator = self::coordinator($root);
                $intent = self::intent();
                $plan = self::plan(
                    $coordinator,
                    $root,
                    $intent,
                );

                self::writeManagedManifest($root, $plan);
                self::writeLock($root);
                self::writeControl(
                    $root,
                    [
                        'lockCurrent' => [true],
                        'verification' => [$errorCode],
                    ],
                );

                try {
                    $coordinator->synchronize(
                        $root,
                        $intent,
                        $plan,
                        new DependencySyncExecutionPolicy(
                            apply: true,
                            allowRepair: true,
                        ),
                    );
                    self::fail('Expected semantic verification failure.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        $errorCode,
                        $exception->errorCode(),
                    );
                }

                $calls = self::composerCalls($root);

                self::assertSame(
                    1,
                    self::commandCount($calls, 'validate'),
                );
                self::assertSame(
                    0,
                    self::commandCount($calls, 'install'),
                );
                self::assertSame(
                    0,
                    self::commandCount($calls, 'update'),
                );
            } finally {
                self::remove($root);
            }
        }
    }

    public function testMissingOrStaleLockRequiresBroadUpdateAuthorization(): void
    {
        $missing = self::projectRoot();

        try {
            $coordinator = self::coordinator($missing);
            $intent = self::intent();
            $plan = self::plan($coordinator, $missing, $intent);
            $beforeState = self::composerState($missing);

            self::assertUnsupportedPolicy(
                $coordinator,
                $missing,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: true),
            );
            self::assertComposerStateUnchanged(
                $missing,
                $beforeState,
            );
            self::assertSame([], self::composerCalls($missing));
        } finally {
            self::remove($missing);
        }

        $staleUnchanged = self::projectRoot();

        try {
            $coordinator = self::coordinator($staleUnchanged);
            $intent = self::intent();
            $plan = self::plan(
                $coordinator,
                $staleUnchanged,
                $intent,
            );

            self::writeManagedManifest($staleUnchanged, $plan);
            self::writeLock($staleUnchanged);
            self::writeControl(
                $staleUnchanged,
                ['lockCurrent' => [false]],
            );

            $beforeState = self::composerState($staleUnchanged);

            self::assertUnsupportedPolicy(
                $coordinator,
                $staleUnchanged,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: true),
            );
            self::assertComposerStateUnchanged(
                $staleUnchanged,
                $beforeState,
            );
            self::assertSame(
                1,
                self::commandCount(
                    self::composerCalls($staleUnchanged),
                    'validate',
                ),
            );
        } finally {
            self::remove($staleUnchanged);
        }

        $staleChanged = self::projectRoot();

        try {
            $coordinator = self::coordinator($staleChanged);
            $intent = self::intent();
            $plan = self::plan(
                $coordinator,
                $staleChanged,
                $intent,
            );

            self::writeLock($staleChanged);
            self::writeControl(
                $staleChanged,
                ['lockCurrent' => [false]],
            );

            $beforeState = self::composerState($staleChanged);

            self::assertUnsupportedPolicy(
                $coordinator,
                $staleChanged,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: true),
            );
            self::assertComposerStateUnchanged(
                $staleChanged,
                $beforeState,
            );
        } finally {
            self::remove($staleChanged);
        }
    }

    public function testProtectedRootsForbidFullUpdateWithoutAuthoritativeLock(): void
    {
        $missing = self::projectRoot();

        try {
            self::writeManifest(
                $missing,
                [
                    'php' => '^8.4',
                    'coretsia/framework' => '^0.7.0',
                    'acme/protected' => '^1.0',
                ],
            );

            $coordinator = self::coordinator($missing);
            $intent = self::intent();
            $plan = self::plan($coordinator, $missing, $intent);

            $beforeState = self::composerState($missing);

            self::assertUnsupportedPolicy(
                $coordinator,
                $missing,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(
                    apply: true,
                    allowBroadUpdate: true,
                ),
            );
            self::assertComposerStateUnchanged(
                $missing,
                $beforeState,
            );
            self::assertSame([], self::composerCalls($missing));
        } finally {
            self::remove($missing);
        }

        $stale = self::projectRoot();

        try {
            self::writeManifest(
                $stale,
                [
                    'php' => '^8.4',
                    'coretsia/framework' => '^0.7.0',
                    'psr/log' => '^3.0',
                ],
            );

            $coordinator = self::coordinator($stale);
            $intent = self::intent();
            $plan = self::plan($coordinator, $stale, $intent);

            self::writeLock(
                $stale,
                [self::installedLockRecord('psr/log')],
            );
            self::writeControl(
                $stale,
                ['lockCurrent' => [false]],
            );

            $beforeState = self::composerState($stale);

            self::assertUnsupportedPolicy(
                $coordinator,
                $stale,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(
                    apply: true,
                    allowBroadUpdate: true,
                ),
            );
            self::assertComposerStateUnchanged(
                $stale,
                $beforeState,
            );
            self::assertSame(
                0,
                self::commandCount(
                    self::composerCalls($stale),
                    'update',
                ),
            );
        } finally {
            self::remove($stale);
        }
    }

    public function testBroadUpdateUsesExactlyOneFullUpdateOnlyWhenAuthorized(): void
    {
        foreach (
            [
                [
                    false,
                    [
                        'lockCurrent' => [true],
                        'verification' => ['ok'],
                        'effectExitCodes' => [0],
                        'writeLockOnEffect' => true,
                    ],
                ],
                [
                    true,
                    [
                        'lockCurrent' => [false, true],
                        'verification' => ['ok'],
                        'effectExitCodes' => [0],
                    ],
                ],
            ] as [$hasLock, $control]
        ) {
            $root = self::projectRoot();

            try {
                $coordinator = self::coordinator($root);
                $intent = self::intent();
                $plan = self::plan(
                    $coordinator,
                    $root,
                    $intent,
                );

                if ($hasLock) {
                    self::writeLock($root);
                }

                self::writeControl($root, $control);

                $result = $coordinator->synchronize(
                    $root,
                    $intent,
                    $plan,
                    new DependencySyncExecutionPolicy(
                        apply: true,
                        allowBroadUpdate: true,
                    ),
                );

                self::assertTrue($result->changed());

                $calls = self::composerCalls($root);

                self::assertSame(
                    1,
                    self::commandCount($calls, 'update'),
                );
                self::assertSame(
                    $hasLock ? 2 : 1,
                    self::commandCount($calls, 'validate'),
                );

                $update = self::firstCommand($calls, 'update');

                self::assertSame(
                    'update',
                    $update[0] ?? null,
                );
                self::assertNotContains('--with-dependencies', $update);

                foreach ($update as $argument) {
                    self::assertFalse(
                        \str_starts_with($argument, 'coretsia/'),
                    );
                }
            } finally {
                self::remove($root);
            }
        }
    }

    public function testBroadUpdatePermissionDoesNotWidenCurrentConstrainedSolve(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator($root);
            $intent = self::intent();
            $plan = self::plan($coordinator, $root, $intent);

            self::writeLock($root);
            self::writeControl(
                $root,
                [
                    'lockCurrent' => [true, true],
                    'verification' => ['ok'],
                    'effectExitCodes' => [0],
                ],
            );

            $result = $coordinator->synchronize(
                $root,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(
                    apply: true,
                    allowBroadUpdate: true,
                ),
            );

            self::assertTrue($result->changed());

            $update = self::firstCommand(
                self::composerCalls($root),
                'update',
            );
            self::assertContains('--with-dependencies', $update);
            self::assertNotContains('--with-all-dependencies', $update);
            self::assertContains('coretsia/framework', $update);
            self::assertContains('coretsia/core-foundation', $update);
            self::assertContains('coretsia/core-kernel', $update);
        } finally {
            self::remove($root);
        }
    }

    public function testChangedManifestUsesOnlyCanonicalCoretsiaUpdateTargets(): void
    {
        $root = self::projectRoot();

        try {
            self::writeManifest(
                $root,
                [
                    'php' => '^8.4',
                    'coretsia/framework' => '^0.7.0',
                    'coretsia/platform-worker' => '^0.7.0',
                    'psr/log' => '^3.0',
                ],
                [
                    'coretsia/platform-worker' => '^0.7.0',
                ],
            );

            $coordinator = self::coordinator($root);
            $intent = self::intent();
            $plan = self::plan($coordinator, $root, $intent);

            self::writeLock(
                $root,
                [self::installedLockRecord('psr/log')],
            );
            self::writeControl(
                $root,
                [
                    'lockCurrent' => [true, true],
                    'verification' => ['ok'],
                    'effectExitCodes' => [0],
                ],
            );

            $result = $coordinator->synchronize(
                $root,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: true),
            );

            self::assertTrue($result->changed());
            self::assertSame(
                ['coretsia/platform-worker'],
                $result->managedRequireRemovals(),
            );

            $update = self::firstCommand(
                self::composerCalls($root),
                'update',
            );

            self::assertContains('coretsia/framework', $update);
            self::assertContains('coretsia/core-foundation', $update);
            self::assertContains('coretsia/core-kernel', $update);
            self::assertContains('--with-dependencies', $update);
            self::assertNotContains('coretsia/platform-worker', $update);
            self::assertNotContains('psr/log', $update);
            self::assertNotContains('--with-all-dependencies', $update);

            foreach ($update as $argument) {
                if (
                    \str_contains($argument, '/')
                    && !\str_starts_with($argument, '--')
                ) {
                    self::assertStringStartsWith('coretsia/', $argument);
                }
            }
        } finally {
            self::remove($root);
        }
    }

    public function testFailedConstrainedUpdateNeverRetriesAsBroadUpdate(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator($root);
            $intent = self::intent();
            $plan = self::plan($coordinator, $root, $intent);

            self::writeLock($root);
            self::writeControl(
                $root,
                [
                    'lockCurrent' => [true],
                    'effectExitCodes' => [1],
                ],
            );

            try {
                $coordinator->synchronize(
                    $root,
                    $intent,
                    $plan,
                    new DependencySyncExecutionPolicy(
                        apply: true,
                        allowBroadUpdate: true,
                    ),
                );
                self::fail('Expected recovery-required failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::RECOVERY_REQUIRED,
                    $exception->errorCode(),
                );
                self::assertSame(
                    DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                    $exception->context()['causeCode'] ?? null,
                );
            }

            $calls = self::composerCalls($root);

            self::assertSame(
                1,
                self::commandCount($calls, 'update'),
            );

            $update = self::firstCommand($calls, 'update');

            self::assertContains('--no-scripts', $update);
            self::assertContains('--no-plugins', $update);
        } finally {
            self::remove($root);
        }
    }

    public function testComposerScriptsAndPluginsAreAuthorizedIndependently(): void
    {
        foreach (
            [
                [
                    new DependencySyncExecutionPolicy(
                        apply: true,
                        allowComposerScripts: true,
                    ),
                    false,
                    true,
                ],
                [
                    new DependencySyncExecutionPolicy(
                        apply: true,
                        allowComposerPlugins: true,
                    ),
                    true,
                    false,
                ],
            ] as [$policy, $noScripts, $noPlugins]
        ) {
            $root = self::projectRoot();

            try {
                $coordinator = self::coordinator($root);
                $intent = self::intent();
                $plan = self::plan(
                    $coordinator,
                    $root,
                    $intent,
                );

                self::writeLock($root);
                self::writeControl(
                    $root,
                    [
                        'lockCurrent' => [true, true],
                        'verification' => ['ok'],
                        'effectExitCodes' => [0],
                    ],
                );

                $coordinator->synchronize(
                    $root,
                    $intent,
                    $plan,
                    $policy,
                );

                $update = self::firstCommand(
                    self::composerCalls($root),
                    'update',
                );

                self::assertSame(
                    $noScripts,
                    \in_array('--no-scripts', $update, true),
                );
                self::assertSame(
                    $noPlugins,
                    \in_array('--no-plugins', $update, true),
                );
            } finally {
                self::remove($root);
            }
        }
    }

    public function testLockDisabledManifestFailsBeforeComposerOrPublication(): void
    {
        $root = self::projectRoot();

        try {
            self::writeManifest(
                $root,
                [
                    'php' => '^8.4',
                    'coretsia/framework' => '^0.7.0',
                ],
                [],
                [],
                ['lock' => false],
            );

            $coordinator = self::coordinator($root);
            $intent = self::intent();
            $plan = self::plan(
                $coordinator,
                $root,
                $intent,
            );
            $before = \file_get_contents($root . '/composer.json');

            self::assertIsString($before);

            self::assertUnsupportedPolicy(
                $coordinator,
                $root,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: true),
            );

            self::assertSame(
                $before,
                \file_get_contents($root . '/composer.json'),
            );
            self::assertSame([], self::composerCalls($root));
        } finally {
            self::remove($root);
        }
    }

    public function testAbsentProtectedDevelopmentRootAllowsNoOpButBlocksEffects(): void
    {
        $packageName = 'acme/coretsia-absent-dev-fixture';
        $developmentRecord = [
            'name' => $packageName,
            'version' => '1.0.0',
            'type' => 'library',
        ];

        $noOp = self::projectRoot();

        try {
            $coordinator = self::coordinator($noOp);
            $intent = self::intent();
            $plan = self::plan(
                $coordinator,
                $noOp,
                $intent,
            );

            self::writeManifest(
                $noOp,
                [
                    'php' => '^8.4',
                    'coretsia/framework' => '^0.7.0',
                    ...$plan->desiredRootRequirements(),
                ],
                $plan->desiredRootRequirements(),
                [$packageName => '^1.0'],
            );
            self::writeLock(
                $noOp,
                [],
                [$developmentRecord],
            );
            self::writeControl(
                $noOp,
                [
                    'lockCurrent' => [true],
                    'verification' => ['ok'],
                ],
            );

            $result = $coordinator->synchronize(
                $noOp,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: true),
            );

            self::assertFalse($result->changed());

            $calls = self::composerCalls($noOp);

            self::assertSame(
                1,
                self::commandCount($calls, 'validate'),
            );
            self::assertSame(
                0,
                self::commandCount($calls, 'install'),
            );
            self::assertSame(
                0,
                self::commandCount($calls, 'update'),
            );
        } finally {
            self::remove($noOp);
        }

        $effect = self::projectRoot();

        try {
            self::writeManifest(
                $effect,
                [
                    'php' => '^8.4',
                    'coretsia/framework' => '^0.7.0',
                ],
                [],
                [$packageName => '^1.0'],
            );

            $coordinator = self::coordinator($effect);
            $intent = self::intent();
            $plan = self::plan(
                $coordinator,
                $effect,
                $intent,
            );

            self::writeLock(
                $effect,
                [],
                [$developmentRecord],
            );

            $beforeState = self::composerState($effect);

            self::assertUnsupportedPolicy(
                $coordinator,
                $effect,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: true),
            );

            self::assertSame(
                [],
                self::composerCalls($effect),
            );
            self::assertComposerStateUnchanged(
                $effect,
                $beforeState,
            );
        } finally {
            self::remove($effect);
        }
    }

    private static function coordinator(
        string $projectRoot,
    ): ComposerSyncCoordinator {
        $composer = self::writeFakeComposer($projectRoot);
        $kernelRoot = self::writeFakeVerifier($projectRoot);
        $facade = ProjectDependencySync::create($composer);
        $base = self::privateValue($facade, 'syncCoordinator');

        self::assertInstanceOf(ComposerSyncCoordinator::class, $base);

        $planner = self::privateValue($base, 'planner');
        $rootReconciler = self::privateValue($base, 'rootReconciler');
        $manifestStore = self::privateValue($base, 'manifestStore');
        $installedProjectVerifier = self::privateValue($base, 'installedProjectVerifier');
        $fileLock = self::privateValue($base, 'fileLock');
        $idGenerator = self::privateValue($base, 'idGenerator');

        self::assertInstanceOf(ProjectPackagePlanner::class, $planner);
        self::assertInstanceOf(ComposerRootReconciler::class, $rootReconciler);
        self::assertInstanceOf(ComposerManifestStore::class, $manifestStore);
        self::assertInstanceOf(InstalledProjectVerifier::class, $installedProjectVerifier);
        self::assertInstanceOf(ScopedFileLock::class, $fileLock);
        self::assertInstanceOf(IdGeneratorInterface::class, $idGenerator);

        return new ComposerSyncCoordinator(
            planner: $planner,
            rootReconciler: $rootReconciler,
            manifestStore: $manifestStore,
            processRunner: new DependencySyncProcessRunner($composer),
            installedProjectVerifier: $installedProjectVerifier,
            fileLock: $fileLock,
            idGenerator: $idGenerator,
            kernelPackageRoot: $kernelRoot,
        );
    }

    private static function plan(
        ComposerSyncCoordinator $coordinator,
        string $projectRoot,
        ProjectInstallationIntent $intent,
    ): ProjectPackagePlan {
        $planner = self::privateValue($coordinator, 'planner');

        self::assertInstanceOf(ProjectPackagePlanner::class, $planner);

        return $planner->plan($projectRoot, $intent);
    }

    private static function intent(): ProjectInstallationIntent
    {
        return new ProjectInstallationIntent(
            new ProjectApplicationSet([AppTarget::Web]),
        );
    }

    private static function projectRoot(): string
    {
        $root = \sys_get_temp_dir()
            . '/coretsia-sync-coordinator-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root . '/config', 0777, true);
        \file_put_contents(
            $root . '/config/app.php',
            "<?php\n\nreturn [];\n",
        );

        self::writeManifest(
            $root,
            [
                'php' => '^8.4',
                'coretsia/framework' => '^0.7.0',
            ],
        );

        return $root;
    }

    /**
     * @param array<string, string> $require
     * @param array<string, string> $managed
     * @param array<string, string> $requireDev
     * @param array<string, mixed> $config
     */
    private static function writeManifest(
        string $root,
        array $require,
        array $managed = [],
        array $requireDev = [],
        array $config = [],
    ): void {
        \ksort($require, \SORT_STRING);
        \ksort($managed, \SORT_STRING);
        \ksort($requireDev, \SORT_STRING);
        \ksort($config, \SORT_STRING);

        $document = [
            'name' => 'coretsia/test-consumer',
            'type' => 'project',
            'require' => $require,
        ];

        if ($requireDev !== []) {
            $document['require-dev'] = $requireDev;
        }

        if ($config !== []) {
            $document['config'] = $config;
        }

        if ($managed !== []) {
            $document['extra'] = [
                'coretsia' => [
                    'dependencySync' => [
                        'schemaVersion' => 1,
                        'managedRequire' => \array_keys($managed),
                        'lastAppliedRequire' => $managed,
                    ],
                ],
            ];
        }

        \file_put_contents(
            $root . '/composer.json',
            \json_encode(
                $document,
                \JSON_THROW_ON_ERROR
                | \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );
    }

    private static function writeManagedManifest(
        string $root,
        ProjectPackagePlan $plan,
    ): void {
        self::writeManifest(
            $root,
            [
                'php' => '^8.4',
                'coretsia/framework' => '^0.7.0',
                ...$plan->desiredRootRequirements(),
            ],
            $plan->desiredRootRequirements(),
        );
    }

    /**
     * @param list<array<string, mixed>> $packages
     * @param list<array<string, mixed>> $developmentPackages
     */
    private static function writeLock(
        string $root,
        array $packages = [],
        array $developmentPackages = [],
    ): void {
        \file_put_contents(
            $root . '/composer.lock',
            \json_encode(
                [
                    'packages' => $packages,
                    'packages-dev' => $developmentPackages,
                ],
                \JSON_THROW_ON_ERROR
                | \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );
    }

    /** @return array<string, mixed> */
    private static function installedLockRecord(
        string $packageName,
    ): array {
        self::assertTrue(InstalledVersions::isInstalled($packageName));

        $version = InstalledVersions::getPrettyVersion($packageName);
        $reference = InstalledVersions::getReference($packageName);

        self::assertIsString($version);
        self::assertNotSame('', $version);

        $record = [
            'name' => $packageName,
            'version' => $version,
            'type' => 'library',
        ];

        if ($reference !== null) {
            $record['source'] = [
                'reference' => $reference,
            ];
        }

        return $record;
    }

    /** @param array<string, mixed> $control */
    private static function writeControl(
        string $root,
        array $control,
    ): void {
        \file_put_contents(
            $root . '/.dependency-sync-test-control.json',
            \json_encode(
                $control,
                \JSON_THROW_ON_ERROR
                | \JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    /**
     * @return array{
     *     manifest: string,
     *     lock: string|null
     * }
     */
    private static function composerState(string $root): array
    {
        $manifest = \file_get_contents($root . '/composer.json');

        self::assertIsString($manifest);

        $lockPath = $root . '/composer.lock';
        $lock = null;

        if (\is_file($lockPath)) {
            $lock = \file_get_contents($lockPath);

            self::assertIsString($lock);
        }

        return [
            'manifest' => $manifest,
            'lock' => $lock,
        ];
    }

    /**
     * @param array{
     *     manifest: string,
     *     lock: string|null
     * } $expected
     */
    private static function assertComposerStateUnchanged(
        string $root,
        array $expected,
    ): void {
        self::assertSame(
            $expected['manifest'],
            \file_get_contents($root . '/composer.json'),
        );

        if ($expected['lock'] === null) {
            self::assertFileDoesNotExist($root . '/composer.lock');
        } else {
            self::assertSame(
                $expected['lock'],
                \file_get_contents($root . '/composer.lock'),
            );
        }

        self::assertDirectoryDoesNotExist($root . '/var/dependency-sync/recovery');
    }

    private static function writeFakeComposer(
        string $root,
    ): string {
        $directory = $root . '/.dependency-sync-test-bin';
        $path = $directory . '/composer.php';

        \mkdir($directory, 0777, true);
        \file_put_contents(
            $path,
            <<<'PHP'
<?php

declare(strict_types=1);

$root = \getcwd();

if (!\is_string($root)) {
    exit(1);
}

$control = \json_decode(
    (string) \file_get_contents(
        $root . '/.dependency-sync-test-control.json',
    ),
    true,
    512,
    \JSON_THROW_ON_ERROR,
);
$arguments = \array_slice($argv, 1);

\file_put_contents(
    $root . '/.dependency-sync-test-composer.log',
    \json_encode(
        $arguments,
        \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
    ) . "\n",
    \FILE_APPEND | \LOCK_EX,
);

$command = $arguments[0] ?? '';

if ($command === 'validate') {
    $counterPath = $root . '/.dependency-sync-test-validate-count';
    $index = (int) @\file_get_contents($counterPath);
    $sequence = $control['lockCurrent'] ?? [true];
    $value = $sequence[$index]
        ?? $sequence[\count($sequence) - 1]
        ?? true;

    \file_put_contents(
        $counterPath,
        (string) ($index + 1),
    );

    exit($value === true ? 0 : 1);
}

if ($command === 'install' || $command === 'update') {
    $counterPath = $root . '/.dependency-sync-test-effect-count';
    $index = (int) @\file_get_contents($counterPath);
    $sequence = $control['effectExitCodes'] ?? [0];
    $value = $sequence[$index]
        ?? $sequence[\count($sequence) - 1]
        ?? 0;

    \file_put_contents(
        $counterPath,
        (string) ($index + 1),
    );

    if (
        $value === 0
        && ($control['writeLockOnEffect'] ?? false) === true
    ) {
        \file_put_contents(
            $root . '/composer.lock',
            "{\n  \"packages\": [],\n"
            . "  \"packages-dev\": []\n}\n",
        );
    }

    exit(\is_int($value) ? $value : 1);
}

exit(0);
PHP,
        );

        return $path;
    }

    private static function writeFakeVerifier(
        string $root,
    ): string {
        $kernelRoot = $root . '/.dependency-sync-test-kernel';
        $bin = $kernelRoot . '/bin';

        \mkdir($bin, 0777, true);
        \file_put_contents(
            $bin . '/dependency-sync-verify.php',
            <<<'PHP'
<?php

declare(strict_types=1);

$root = \getcwd();

if (!\is_string($root)) {
    exit(1);
}

\stream_get_contents(STDIN);

$control = \json_decode(
    (string) \file_get_contents($root . '/.dependency-sync-test-control.json'),
    true,
    512,
    \JSON_THROW_ON_ERROR,
);
$counterPath = $root . '/.dependency-sync-test-verifier-count';
$index = (int) @\file_get_contents($counterPath);
$sequence = $control['verification'] ?? ['ok'];
$value = $sequence[$index]
    ?? $sequence[\count($sequence) - 1]
    ?? 'ok';

\file_put_contents(
    $counterPath,
    (string) ($index + 1),
);

if ($value === 'ok') {
    \fwrite(
        STDOUT,
        '{"schemaVersion":1,"status":"ok"}',
    );

    exit(0);
}

if (!\is_string($value) || $value === '') {
    exit(1);
}

\fwrite(
    STDOUT,
    \json_encode(
        [
            'schemaVersion' => 1,
            'status' => 'error',
            'code' => $value,
        ],
        \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
    ),
);

exit(2);
PHP,
        );

        return $kernelRoot;
    }

    /** @return list<list<string>> */
    private static function composerCalls(
        string $root,
    ): array {
        $path = $root . '/.dependency-sync-test-composer.log';

        if (!\is_file($path)) {
            return [];
        }

        $calls = [];

        foreach (
            \file(
                $path,
                \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES,
            ) ?: [] as $line
        ) {
            $decoded = \json_decode(
                $line,
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            self::assertIsArray($decoded);
            self::assertTrue(\array_is_list($decoded));

            $calls[] = $decoded;
        }

        return $calls;
    }

    private static function verifierCount(
        string $root,
    ): int {
        return (int) @\file_get_contents($root . '/.dependency-sync-test-verifier-count');
    }

    /** @param list<list<string>> $calls */
    private static function commandCount(
        array $calls,
        string $command,
    ): int {
        $count = 0;

        foreach ($calls as $call) {
            if (($call[0] ?? null) === $command) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<list<string>> $calls
     *
     * @return list<string>
     */
    private static function firstCommand(
        array $calls,
        string $command,
    ): array {
        foreach ($calls as $call) {
            if (($call[0] ?? null) === $command) {
                return $call;
            }
        }

        self::fail('Expected Composer command: ' . $command);
    }

    private static function assertUnsupportedPolicy(
        ComposerSyncCoordinator $coordinator,
        string $root,
        ProjectInstallationIntent $intent,
        ProjectPackagePlan $plan,
        DependencySyncExecutionPolicy $policy,
    ): void {
        try {
            $coordinator->synchronize(
                $root,
                $intent,
                $plan,
                $policy,
            );
            self::fail('Expected unsupported Composer policy.');
        } catch (DependencySyncException $exception) {
            self::assertSame(
                DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY,
                $exception->errorCode(),
            );
        }
    }

    private static function privateValue(
        object $object,
        string $property,
    ): mixed {
        return new \ReflectionProperty($object, $property)->getValue($object);
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

    private static function source(): string
    {
        $path = new \ReflectionClass(ComposerSyncCoordinator::class)->getFileName();

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }
}
