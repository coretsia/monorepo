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

namespace Coretsia\Tools\Tests\Integration;

use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Id\Exception\IdGenerationFailedException;
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

final class DependencySyncFailureRecoveryTest extends TestCase
{
    public function testCoordinatorHasSingleEffectAttemptAndExplicitRecoveryLifecycle(): void
    {
        $repoRoot = \dirname(__DIR__, 3);
        $source = \file_get_contents(
            $repoRoot
            . '/packages/core/kernel/src/DependencySync/Composer/'
            . 'ComposerSyncCoordinator.php',
        );

        self::assertIsString($source);

        foreach (
            [
                'writeRecoverySnapshot',
                'restorePreComposerState',
                'discardRecoverySnapshot',
                'RECOVERY_REQUIRED',
                'RECOVERY_STORAGE_FAILED',
                'PROJECT_STATE_CHANGED',
                'COMPOSER_EXECUTION_FAILED',
            ] as $needle
        ) {
            self::assertStringContainsString($needle, $source);
        }

        self::assertStringContainsString('protectedThirdPartyRoots', $source);

        $method = new \ReflectionMethod(ComposerSyncCoordinator::class, 'executeEffect');
        $path = $method->getFileName();

        self::assertIsString($path);

        $lines = \file($path);

        self::assertIsArray($lines);

        $effectSource = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );

        self::assertStringContainsString("\$composer['timedOut']", $effectSource);
        self::assertStringContainsString("\$composer['exitCode'] !== 0", $effectSource);
        self::assertStringContainsString('recoveryRequired(', $effectSource);
        self::assertStringContainsString('COMPOSER_EXECUTION_FAILED', $effectSource);

        self::assertStringContainsString('publishManifestCandidate', $source);
        self::assertStringContainsString('discardBeforeProcessStart', $source);
        self::assertStringContainsString('handlePreStartComposerFailure', $source);
        self::assertStringContainsString('COMPOSER_MANIFEST_WRITE_FAILED', $source);
    }

    public function testRecoveryStoreUsesDurableFlushSyncAndPosixPrivateMode(): void
    {
        $repoRoot = \dirname(__DIR__, 3);
        $storeSource = \file_get_contents(
            $repoRoot
            . '/packages/core/kernel/src/DependencySync/Composer/'
            . 'ComposerManifestStore.php',
        );

        self::assertIsString($storeSource);
        self::assertStringContainsString('RECOVERY_STORAGE_FAILED', $storeSource);

        $method = new \ReflectionMethod(ComposerManifestStore::class, 'writeRecoveryFile');
        $path = $method->getFileName();

        self::assertIsString($path);

        $lines = \file($path);

        self::assertIsArray($lines);

        $source = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );

        self::assertStringContainsString('\\fflush(', $source);
        self::assertStringContainsString('\\fsync(', $source);
        self::assertStringContainsString('!@\\fclose($handle)', $source);
        self::assertStringContainsString('recovery-file-close-failed', $source);
        self::assertStringContainsString('0600', $source);
    }

    public function testRecoverySnapshotPersistsExactPrivateBytesAndDiscardsCleanly(): void
    {
        $root = self::projectRoot();
        $store = new ComposerManifestStore();
        $manifestBytes = (string) \file_get_contents($root . '/composer.json');
        $lockBytes = "{\n  \"packages\": [],\n  \"packages-dev\": []\n}\n";

        \file_put_contents(
            $root . '/composer.lock',
            $lockBytes,
        );
        \mkdir($root . '/var', 0775);

        try {
            $store->writeRecoverySnapshot(
                $root,
                'receipt_1',
                $manifestBytes,
                $lockBytes,
            );

            $receipt = self::receiptPath($root);
            $manifestSnapshot = $receipt . '/composer.json.original';
            $lockSnapshot = $receipt . '/composer.lock.original';

            self::assertSame(
                $manifestBytes,
                \file_get_contents($manifestSnapshot),
            );
            self::assertSame(
                $lockBytes,
                \file_get_contents($lockSnapshot),
            );

            if (\DIRECTORY_SEPARATOR !== '\\') {
                self::assertSame(
                    0600,
                    \fileperms($manifestSnapshot) & 0777,
                );
                self::assertSame(
                    0600,
                    \fileperms($lockSnapshot) & 0777,
                );
            }

            $store->discardRecoverySnapshot($root, 'receipt_1');

            self::assertDirectoryDoesNotExist($receipt);
        } finally {
            self::remove($root);
        }
    }

    public function testUnsafeRecoveryDirectorySymlinksFailClosed(): void
    {
        if (!\function_exists('symlink')) {
            self::assertRecoverySymlinkGuardContract();

            return;
        }

        foreach (
            [
                'dependency-sync',
                'recovery',
            ] as $boundary
        ) {
            $root = self::projectRoot();
            $outside = $root . '-outside';
            $store = new ComposerManifestStore();

            \mkdir($root . '/var', 0775, true);
            \mkdir($outside, 0775, true);

            if ($boundary === 'recovery') {
                \mkdir(
                    $root . '/var/dependency-sync',
                    0775,
                    true,
                );
            }

            $unsafePath = $boundary === 'dependency-sync'
                ? $root . '/var/dependency-sync'
                : $root . '/var/dependency-sync/recovery';

            if (!@\symlink($outside, $unsafePath)) {
                self::remove($root);
                self::remove($outside);
                self::assertRecoverySymlinkGuardContract();

                return;
            }

            try {
                try {
                    $store->writeRecoverySnapshot(
                        $root,
                        'receipt_1',
                        '{}',
                        null,
                    );
                    self::fail('Expected unsafe recovery path rejection.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED,
                        $exception->errorCode(),
                    );
                }

                self::assertDirectoryExists($outside);
                self::assertSame(['.', '..'], \scandir($outside));
            } finally {
                self::remove($root);
                self::remove($outside);
            }
        }
    }

    public function testRecoveryPathNonDirectoriesFailClosed(): void
    {
        foreach (
            [
                'dependency-sync',
                'recovery',
            ] as $boundary
        ) {
            $root = self::projectRoot();
            $store = new ComposerManifestStore();

            \mkdir($root . '/var', 0775, true);

            if ($boundary === 'recovery') {
                \mkdir(
                    $root . '/var/dependency-sync',
                    0775,
                    true,
                );
            }

            $unsafePath = $boundary === 'dependency-sync'
                ? $root . '/var/dependency-sync'
                : $root . '/var/dependency-sync/recovery';

            \file_put_contents($unsafePath, 'not-a-directory');

            try {
                try {
                    $store->writeRecoverySnapshot(
                        $root,
                        'receipt_1',
                        '{}',
                        null,
                    );
                    self::fail('Expected non-directory recovery ' . 'path rejection.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED,
                        $exception->errorCode(),
                    );
                }

                self::assertSame('not-a-directory', \file_get_contents($unsafePath));
            } finally {
                self::remove($root);
            }
        }
    }

    public function testRepairSnapshotExistsBeforeInstallAndIsDiscardedAfterVerification(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator(
                $root,
                self::fixedIdGenerator('receipt_1'),
            );
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
                    'lockCurrent' => [true, true],
                    'verification' => [
                        DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE,
                        'ok',
                    ],
                    'effectExitCode' => 0,
                    'requireSnapshot' => true,
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
            self::assertSame(
                1,
                self::commandCount(
                    self::composerCalls($root),
                    'install',
                ),
            );
            self::assertDirectoryDoesNotExist(
                self::receiptPath($root),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testStartedEffectFailureAndPostEffectMutationRetainRecoveryReceipt(): void
    {
        foreach (
            [
                [
                    'effectExitCode' => 1,
                    'mutateManifest' => false,
                    'causeCode' => DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                ],
                [
                    'effectExitCode' => 0,
                    'mutateManifest' => true,
                    'causeCode' => DependencySyncErrorCodes::PROJECT_STATE_CHANGED,
                ],
            ] as $case
        ) {
            $root = self::projectRoot();

            try {
                $coordinator = self::coordinator(
                    $root,
                    self::fixedIdGenerator('receipt_1'),
                );
                $intent = self::intent();
                $plan = self::plan(
                    $coordinator,
                    $root,
                    $intent,
                );
                $originalManifest = (string) \file_get_contents($root . '/composer.json');
                $lockBytes = self::writeLock($root);

                self::writeControl(
                    $root,
                    [
                        'lockCurrent' => [true, true],
                        'verification' => ['ok'],
                        'effectExitCode' => $case['effectExitCode'],
                        'mutateManifest' => $case['mutateManifest'],
                        'requireSnapshot' => true,
                    ],
                );

                try {
                    $coordinator->synchronize(
                        $root,
                        $intent,
                        $plan,
                        new DependencySyncExecutionPolicy(apply: true),
                    );
                    self::fail('Expected recovery-required failure.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::RECOVERY_REQUIRED,
                        $exception->errorCode(),
                    );
                    self::assertSame(
                        'receipt_1',
                        $exception->context()
                        ['recoveryReceiptId'] ?? null,
                    );
                    self::assertSame(
                        $case['causeCode'],
                        $exception->context()
                        ['causeCode'] ?? null,
                    );
                }

                $receipt = self::receiptPath($root);

                self::assertDirectoryExists($receipt);
                self::assertSame(
                    $originalManifest,
                    \file_get_contents($receipt . '/composer.json.original'),
                );
                self::assertSame(
                    $lockBytes,
                    \file_get_contents($receipt . '/composer.lock.original'),
                );
                self::assertSame(
                    1,
                    self::commandCount(
                        self::composerCalls($root),
                        'update',
                    ),
                );
                self::assertSame(
                    0,
                    self::commandCount(
                        self::composerCalls($root),
                        'install',
                    ),
                );
            } finally {
                self::remove($root);
            }
        }
    }

    public function testSuccessfulVerificationWithUndiscardableSnapshotFailsStorageContract(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator(
                $root,
                self::fixedIdGenerator('receipt_1'),
            );
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
                    'effectExitCode' => 0,
                    'blockDiscard' => true,
                ],
            );

            try {
                $coordinator->synchronize(
                    $root,
                    $intent,
                    $plan,
                    new DependencySyncExecutionPolicy(apply: true),
                );
                self::fail('Expected recovery-storage failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED,
                    $exception->errorCode(),
                );
                self::assertSame(
                    'receipt_1',
                    $exception->context()
                    ['recoveryReceiptId'] ?? null,
                );
            }

            self::assertDirectoryExists(self::receiptPath($root));
        } finally {
            self::remove($root);
        }
    }

    public function testInvalidOrFailingReceiptGeneratorFailsBeforeStorageAllocation(): void
    {
        foreach (
            [
                self::fixedIdGenerator('bad value'),
                self::failingIdGenerator(),
            ] as $idGenerator
        ) {
            $root = self::projectRoot();

            try {
                $coordinator = self::coordinator(
                    $root,
                    $idGenerator,
                );
                $intent = self::intent();
                $plan = self::plan(
                    $coordinator,
                    $root,
                    $intent,
                );

                self::writeLock($root);
                self::writeControl(
                    $root,
                    ['lockCurrent' => [true]],
                );

                try {
                    $coordinator->synchronize(
                        $root,
                        $intent,
                        $plan,
                        new DependencySyncExecutionPolicy(apply: true),
                    );
                    self::fail('Expected recovery-storage failure.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED,
                        $exception->errorCode(),
                    );
                    self::assertArrayNotHasKey('recoveryReceiptId', $exception->context());
                }

                self::assertDirectoryDoesNotExist($root . '/var/dependency-sync/recovery');
                self::assertSame(
                    0,
                    self::commandCount(
                        self::composerCalls($root),
                        'update',
                    ),
                );
            } finally {
                self::remove($root);
            }
        }
    }

    private static function coordinator(
        string $projectRoot,
        IdGeneratorInterface $idGenerator,
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

        self::assertInstanceOf(ProjectPackagePlanner::class, $planner);
        self::assertInstanceOf(ComposerRootReconciler::class, $rootReconciler);
        self::assertInstanceOf(ComposerManifestStore::class, $manifestStore);
        self::assertInstanceOf(InstalledProjectVerifier::class, $installedProjectVerifier);
        self::assertInstanceOf(ScopedFileLock::class, $fileLock);

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

    public function testRecoverySnapshotFailureStopsBeforeComposerEffect(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator(
                $root,
                self::fixedIdGenerator('receipt_1'),
            );
            $intent = self::intent();
            $plan = self::plan(
                $coordinator,
                $root,
                $intent,
            );
            $beforeManifest = (string) \file_get_contents($root . '/composer.json');
            $beforeLock = self::writeLock($root);

            self::writeControl(
                $root,
                ['lockCurrent' => [true]],
            );
            \mkdir(
                self::receiptPath($root),
                0700,
                true,
            );

            try {
                $coordinator->synchronize(
                    $root,
                    $intent,
                    $plan,
                    new DependencySyncExecutionPolicy(apply: true),
                );
                self::fail('Expected recovery-storage failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED,
                    $exception->errorCode(),
                );
            }

            self::assertSame(
                $beforeManifest,
                \file_get_contents($root . '/composer.json'),
            );
            self::assertSame(
                $beforeLock,
                \file_get_contents($root . '/composer.lock'),
            );
            self::assertSame(
                0,
                self::commandCount(
                    self::composerCalls($root),
                    'update',
                ),
            );
            self::assertSame(
                0,
                self::commandCount(
                    self::composerCalls($root),
                    'install',
                ),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testEffectfulMissingVerifierRetainsRecoveryReceipt(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator(
                $root,
                self::fixedIdGenerator('receipt_1'),
            );
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
                    'effectExitCode' => 0,
                ],
            );

            $verifier = $root
                . '/.dependency-sync-test-kernel/bin/'
                . 'dependency-sync-verify.php';

            self::assertFileExists($verifier);
            self::assertTrue(\unlink($verifier));

            try {
                $coordinator->synchronize(
                    $root,
                    $intent,
                    $plan,
                    new DependencySyncExecutionPolicy(apply: true),
                );
                self::fail('Expected recovery-required failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::RECOVERY_REQUIRED,
                    $exception->errorCode(),
                );
                self::assertSame(
                    'receipt_1',
                    $exception->context()
                    ['recoveryReceiptId'] ?? null,
                );
                self::assertSame(
                    DependencySyncErrorCodes::VERIFICATION_EXECUTION_FAILED,
                    $exception->context()
                    ['causeCode'] ?? null,
                );
            }

            self::assertDirectoryExists(
                self::receiptPath($root),
            );
            self::assertSame(
                1,
                self::commandCount(
                    self::composerCalls($root),
                    'update',
                ),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testComposerStartFailureRestoresPublishedCandidateAndDiscardsRecovery(): void
    {
        $root = self::projectRoot();

        try {
            $coordinator = self::coordinator(
                $root,
                self::composerStartFailureIdGenerator($root),
            );
            $intent = self::intent();
            $plan = self::plan(
                $coordinator,
                $root,
                $intent,
            );
            $manifest = (string) \file_get_contents($root . '/composer.json');
            $lock = self::writeLock($root);

            self::writeControl(
                $root,
                ['lockCurrent' => [true]],
            );

            try {
                $coordinator->synchronize(
                    $root,
                    $intent,
                    $plan,
                    new DependencySyncExecutionPolicy(apply: true),
                );
                self::fail('Expected Composer start failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                    $exception->errorCode(),
                );
            }

            self::assertSame(
                $manifest,
                \file_get_contents($root . '/composer.json'),
            );
            self::assertSame(
                $lock,
                \file_get_contents($root . '/composer.lock'),
            );
            self::assertDirectoryDoesNotExist(
                self::receiptPath($root),
            );

            $calls = self::composerCalls($root);

            self::assertSame(
                1,
                self::commandCount($calls, 'validate'),
            );
            self::assertSame(
                0,
                self::commandCount($calls, 'update'),
            );
            self::assertSame(
                0,
                self::commandCount($calls, 'install'),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testPreStartCleanupFailureIsNotMaskedByComposerFailure(): void
    {
        $root = self::projectRoot();
        $store = new ComposerManifestStore();

        try {
            $manifest = (string) \file_get_contents($root . '/composer.json');
            $lock = self::writeLock($root);

            \mkdir($root . '/var', 0775);

            $store->writeRecoverySnapshot(
                $root,
                'receipt_1',
                $manifest,
                $lock,
            );

            \file_put_contents(
                self::receiptPath($root) . '/unexpected',
                'block-discard',
            );

            $coordinator = self::coordinator(
                $root,
                self::fixedIdGenerator('unused'),
            );

            try {
                self::invokePrivate(
                    $coordinator,
                    'handlePreStartComposerFailure',
                    [
                        $root,
                        'receipt_1',
                        false,
                        $manifest,
                        'sha256:' . \hash('sha256', $manifest),
                        'sha256:' . \hash('sha256', $lock),
                        DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED),
                    ],
                );
                self::fail('Expected recovery-storage failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED,
                    $exception->errorCode(),
                );
                self::assertSame(
                    'receipt_1',
                    $exception->context()
                    ['recoveryReceiptId'] ?? null,
                );
            }

            self::assertDirectoryExists(
                self::receiptPath($root),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testManifestPublicationPermissionOutcomePreservesRecoveryAndEffectOrdering(): void
    {
        $root = self::projectRoot();
        $originalMode = \fileperms($root);

        self::assertIsInt($originalMode);
        $originalMode &= 0777;

        try {
            self::assertTrue(
                \chmod($root, 0555),
            );

            $writeFailureExpected = !self::canMutateDirectory($root);

            self::assertTrue(
                \chmod($root, $originalMode),
            );

            $coordinator = self::coordinator(
                $root,
                self::readOnlyProjectRootIdGenerator($root),
            );
            $intent = self::intent();
            $plan = self::plan(
                $coordinator,
                $root,
                $intent,
            );
            $manifest = (string) \file_get_contents($root . '/composer.json');
            $lock = self::writeLock($root);

            self::writeControl(
                $root,
                ['lockCurrent' => [true]],
            );

            if ($writeFailureExpected) {
                try {
                    $coordinator->synchronize(
                        $root,
                        $intent,
                        $plan,
                        new DependencySyncExecutionPolicy(apply: true),
                    );
                    self::fail('Expected manifest publication failure.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::COMPOSER_MANIFEST_WRITE_FAILED,
                        $exception->errorCode(),
                    );
                }

                self::assertSame(
                    $manifest,
                    \file_get_contents($root . '/composer.json'),
                );
                self::assertSame(
                    $lock,
                    \file_get_contents($root . '/composer.lock'),
                );
                self::assertDirectoryDoesNotExist(
                    self::receiptPath($root),
                );
                self::assertSame(
                    0,
                    self::commandCount(
                        self::composerCalls($root),
                        'update',
                    ),
                );
                self::assertSame(
                    0,
                    self::commandCount(
                        self::composerCalls($root),
                        'install',
                    ),
                );

                return;
            }

            $result = $coordinator->synchronize(
                $root,
                $intent,
                $plan,
                new DependencySyncExecutionPolicy(apply: true),
            );

            self::assertTrue($result->changed());
            self::assertNotSame(
                $manifest,
                \file_get_contents($root . '/composer.json'),
            );
            self::assertDirectoryDoesNotExist(
                self::receiptPath($root),
            );
            self::assertSame(
                1,
                self::commandCount(
                    self::composerCalls($root),
                    'update',
                ),
            );
            self::assertSame(
                0,
                self::commandCount(
                    self::composerCalls($root),
                    'install',
                ),
            );
        } finally {
            @\chmod($root, $originalMode);
            self::remove($root);
        }
    }

    public function testPreStartRestoreDetectsConcurrentStateAndRetainsRecovery(): void
    {
        $root = self::projectRoot();
        $store = new ComposerManifestStore();

        try {
            $originalManifest = (string) \file_get_contents($root . '/composer.json');
            $lock = self::writeLock($root);
            $candidate = $originalManifest . " \n";

            \mkdir($root . '/var', 0775);

            $store->writeRecoverySnapshot(
                $root,
                'receipt_1',
                $originalManifest,
                $lock,
            );

            \file_put_contents($root . '/composer.json', $candidate . " \n");

            $coordinator = self::coordinator(
                $root,
                self::fixedIdGenerator('unused'),
            );

            try {
                self::invokePrivate(
                    $coordinator,
                    'handlePreStartComposerFailure',
                    [
                        $root,
                        'receipt_1',
                        true,
                        $originalManifest,
                        'sha256:' . \hash('sha256', $candidate),
                        'sha256:' . \hash('sha256', $lock),
                        DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED),
                    ],
                );
                self::fail('Expected recovery-required failure.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::RECOVERY_REQUIRED,
                    $exception->errorCode(),
                );
                self::assertSame(
                    'receipt_1',
                    $exception->context()
                    ['recoveryReceiptId'] ?? null,
                );
                self::assertSame(
                    DependencySyncErrorCodes::PROJECT_STATE_CHANGED,
                    $exception->context()
                    ['causeCode'] ?? null,
                );
            }

            self::assertDirectoryExists(
                self::receiptPath($root),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testPreStartRestorePermissionOutcomePreservesRecoverySemantics(): void
    {
        $root = self::projectRoot();
        $store = new ComposerManifestStore();
        $originalMode = \fileperms($root);

        self::assertIsInt($originalMode);
        $originalMode &= 0777;

        try {
            $originalManifest = (string) \file_get_contents($root . '/composer.json');
            $lock = self::writeLock($root);
            $candidate = $originalManifest . " \n";

            \mkdir($root . '/var', 0775);

            $store->writeRecoverySnapshot(
                $root,
                'receipt_1',
                $originalManifest,
                $lock,
            );

            \file_put_contents($root . '/composer.json', $candidate);

            $coordinator = self::coordinator(
                $root,
                self::fixedIdGenerator('unused'),
            );

            self::assertTrue(
                \chmod($root, 0555),
            );

            $restoreFailureExpected = !self::canMutateDirectory($root);

            try {
                self::invokePrivate(
                    $coordinator,
                    'handlePreStartComposerFailure',
                    [
                        $root,
                        'receipt_1',
                        true,
                        $originalManifest,
                        'sha256:' . \hash('sha256', $candidate),
                        'sha256:' . \hash('sha256', $lock),
                        DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED),
                    ],
                );
                self::fail('Expected pre-start Composer failure.');
            } catch (DependencySyncException $exception) {
                if ($restoreFailureExpected) {
                    self::assertSame(
                        DependencySyncErrorCodes::RECOVERY_REQUIRED,
                        $exception->errorCode(),
                    );
                    self::assertSame(
                        'receipt_1',
                        $exception->context()
                        ['recoveryReceiptId'] ?? null,
                    );
                    self::assertSame(
                        DependencySyncErrorCodes::COMPOSER_MANIFEST_WRITE_FAILED,
                        $exception->context()
                        ['causeCode'] ?? null,
                    );
                } else {
                    self::assertSame(
                        DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                        $exception->errorCode(),
                    );
                }
            } finally {
                self::assertTrue(
                    \chmod($root, $originalMode),
                );
            }

            if ($restoreFailureExpected) {
                self::assertDirectoryExists(
                    self::receiptPath($root),
                );

                return;
            }

            self::assertSame(
                $originalManifest,
                \file_get_contents($root . '/composer.json'),
            );
            self::assertDirectoryDoesNotExist(
                self::receiptPath($root),
            );
        } finally {
            @\chmod($root, $originalMode);
            self::remove($root);
        }
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
            new ProjectApplicationSet([
                AppTarget::Web,
            ]),
        );
    }

    private static function projectRoot(): string
    {
        $root = \sys_get_temp_dir()
            . '/coretsia-failure-recovery-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root . '/config', 0777, true);
        \file_put_contents($root . '/config/app.php', "<?php\n\nreturn [];\n");

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
     */
    private static function writeManifest(
        string $root,
        array $require,
        array $managed = [],
    ): void {
        \ksort($require, \SORT_STRING);
        \ksort($managed, \SORT_STRING);

        $document = [
            'name' => 'coretsia/test-consumer',
            'type' => 'project',
            'require' => $require,
        ];

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

    private static function writeLock(string $root): string
    {
        $bytes = "{\n"
            . "  \"packages\": [],\n"
            . "  \"packages-dev\": []\n"
            . "}\n";

        \file_put_contents(
            $root . '/composer.lock',
            $bytes,
        );

        return $bytes;
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

$root = getcwd();

if (!is_string($root)) {
    exit(1);
}

$control = json_decode(
    (string) file_get_contents(
        $root . '/.dependency-sync-test-control.json',
    ),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$arguments = array_slice($argv, 1);

file_put_contents(
    $root . '/.dependency-sync-test-composer.log',
    json_encode(
        $arguments,
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES,
    ) . "\n",
    FILE_APPEND | LOCK_EX,
);

$command = $arguments[0] ?? '';

if ($command === 'validate') {
    $counterPath = $root . '/.dependency-sync-test-validate-count';
    $index = (int) @file_get_contents($counterPath);
    $sequence = $control['lockCurrent'] ?? [true];
    $value = $sequence[$index]
        ?? $sequence[count($sequence) - 1]
        ?? true;

    file_put_contents(
        $counterPath,
        (string) ($index + 1),
    );

    exit($value === true ? 0 : 1);
}

if (
    $command === 'install'
    || $command === 'update'
) {
    if (
        ($control['requireSnapshot'] ?? false)
        === true
    ) {
        $snapshot = $root
            . '/var/dependency-sync/recovery/'
            . 'receipt_1/'
            . 'composer.json.original';

        if (!is_file($snapshot)) {
            exit(91);
        }
    }

    if (
        ($control['mutateManifest'] ?? false)
        === true
    ) {
        file_put_contents($root . '/composer.json', " \n", FILE_APPEND);
    }

    $exitCode = $control['effectExitCode'] ?? 0;

    exit(is_int($exitCode) ? $exitCode : 1);
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

$root = getcwd();

if (!is_string($root)) {
    exit(1);
}

stream_get_contents(STDIN);

$control = json_decode(
    (string) file_get_contents(
        $root . '/.dependency-sync-test-control.json',
    ),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$counterPath = $root . '/.dependency-sync-test-verifier-count';
$index = (int) @file_get_contents($counterPath);
$sequence = $control['verification'] ?? ['ok'];
$value = $sequence[$index]
    ?? $sequence[count($sequence) - 1]
    ?? 'ok';

file_put_contents(
    $counterPath,
    (string) ($index + 1),
);

if (
    ($control['blockDiscard'] ?? false)
    === true
) {
    $receipt = $root . '/var/dependency-sync/recovery/' . 'receipt_1';

    if (is_dir($receipt)) {
        file_put_contents($receipt . '/unexpected', 'block-discard');
    }
}

if ($value === 'ok') {
    fwrite(STDOUT, '{"schemaVersion":1,"status":"ok"}');

    exit(0);
}

if (!is_string($value) || $value === '') {
    exit(1);
}

fwrite(
    STDOUT,
    json_encode(
        [
            'schemaVersion' => 1,
            'status' => 'error',
            'code' => $value,
        ],
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES,
    ),
);

exit(2);
PHP,
        );

        return $kernelRoot;
    }

    private static function composerStartFailureIdGenerator(
        string $root,
    ): IdGeneratorInterface {
        return new class($root) implements IdGeneratorInterface {
            public function __construct(
                private string $root,
            ) {
            }

            public function generate(): string
            {
                $composer = $this->root . '/.dependency-sync-test-bin/composer.php';

                if (!@\unlink($composer)) {
                    throw new IdGenerationFailedException();
                }

                return 'receipt_1';
            }
        };
    }

    private static function readOnlyProjectRootIdGenerator(
        string $root,
    ): IdGeneratorInterface {
        return new class($root) implements IdGeneratorInterface {
            public function __construct(
                private string $root,
            ) {
            }

            public function generate(): string
            {
                if (!@\chmod($this->root, 0555)) {
                    throw new IdGenerationFailedException();
                }

                return 'receipt_1';
            }
        };
    }

    private static function fixedIdGenerator(
        string $id,
    ): IdGeneratorInterface {
        return new class($id) implements IdGeneratorInterface {
            public function __construct(
                private string $id,
            ) {
            }

            public function generate(): string
            {
                return $this->id;
            }
        };
    }

    private static function failingIdGenerator(): IdGeneratorInterface
    {
        return new class() implements IdGeneratorInterface {
            public function generate(): string
            {
                throw new IdGenerationFailedException();
            }
        };
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
                \FILE_IGNORE_NEW_LINES
                | \FILE_SKIP_EMPTY_LINES,
            ) ?: [] as $line
        ) {
            $decoded = \json_decode(
                $line,
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            self::assertIsArray($decoded);
            self::assertTrue(
                \array_is_list($decoded),
            );
            $calls[] = $decoded;
        }

        return $calls;
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

    private static function receiptPath(
        string $root,
    ): string {
        return $root . '/var/dependency-sync/recovery/' . 'receipt_1';
    }

    private static function canMutateDirectory(string $directory): bool
    {
        $path = $directory
            . '/.coretsia-write-probe-'
            . \bin2hex(\random_bytes(8));
        $handle = @\fopen($path, 'x+b');

        if (!\is_resource($handle)) {
            return false;
        }

        $closed = @\fclose($handle);
        $removed = @\unlink($path);

        return $closed && $removed;
    }

    private static function assertRecoverySymlinkGuardContract(): void
    {
        $ensureDirectory = self::methodSource(
            ComposerManifestStore::class,
            'ensureRecoveryDirectory',
        );
        $writeSnapshot = self::methodSource(
            ComposerManifestStore::class,
            'writeRecoverySnapshot',
        );

        self::assertStringContainsString(
            'if (\is_link($path))',
            $ensureDirectory,
        );
        self::assertStringContainsString(
            'recovery-directory-symlink',
            $ensureDirectory,
        );
        self::assertStringContainsString(
            'self::ensureRecoveryDirectory($dependencySync, true)',
            $writeSnapshot,
        );
        self::assertStringContainsString(
            'self::ensureRecoveryDirectory($recovery, true)',
            $writeSnapshot,
        );
    }

    private static function methodSource(
        string $class,
        string $method,
    ): string {
        $reflection = new \ReflectionMethod($class, $method);
        $path = $reflection->getFileName();

        self::assertIsString($path);

        $lines = \file($path);

        self::assertIsArray($lines);

        return \implode(
            '',
            \array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1,
            ),
        );
    }

    /** @param list<mixed> $arguments */
    private static function invokePrivate(
        object $object,
        string $method,
        array $arguments,
    ): mixed {
        return new \ReflectionMethod(
            $object,
            $method,
        )->invokeArgs(
            $object,
            $arguments,
        );
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
}
