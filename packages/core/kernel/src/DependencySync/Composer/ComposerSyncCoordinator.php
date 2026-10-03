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

namespace Coretsia\Kernel\DependencySync\Composer;

use Closure;
use Coretsia\Foundation\Filesystem\Exception\ScopedFileLockException;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Id\Exception\IdGenerationFailedException;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Kernel\DependencySync\DependencySyncExecutionPolicy;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\Process\DependencySyncProcessRunner;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;
use Coretsia\Kernel\DependencySync\ProjectDependencySyncResult;
use Coretsia\Kernel\DependencySync\ProjectInstallationIntent;
use Coretsia\Kernel\DependencySync\ProjectPackagePlan;
use Coretsia\Kernel\DependencySync\ProjectPackagePlanner;
use Coretsia\Kernel\DependencySync\Verification\DependencySyncVerificationCodec;

/** @internal */
final readonly class ComposerSyncCoordinator
{
    private const int COMPOSER_TIMEOUT_SECONDS = 900;
    private const int VERIFICATION_TIMEOUT_SECONDS = 60;
    private const int MAX_VERIFICATION_PROTOCOL_BYTES = 4096;
    private const string PLACEHOLDER_LOCK_IDENTITY = 'sha256:0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(
        private ProjectPackagePlanner $planner,
        private ComposerRootReconciler $rootReconciler,
        private ComposerManifestStore $manifestStore,
        private DependencySyncProcessRunner $processRunner,
        private InstalledProjectVerifier $installedProjectVerifier,
        private ScopedFileLock $fileLock,
        private IdGeneratorInterface $idGenerator,
        private string $kernelPackageRoot,
    ) {
    }

    public function synchronize(
        string $projectRoot,
        ProjectInstallationIntent $intent,
        ProjectPackagePlan $approvedPlan,
        DependencySyncExecutionPolicy $executionPolicy,
    ): ProjectDependencySyncResult {
        $originalManifest = $this->manifestStore->readManifest($projectRoot);
        $originalLock = $this->manifestStore->readLockState($projectRoot);
        $candidateDocument = $this->manifestStore->copyManifestDocument($originalManifest['document']);
        $reconciliation = $this->rootReconciler->reconcile(
            $originalManifest['document'],
            $candidateDocument,
            $approvedPlan,
        );
        $candidateBytes = $reconciliation['manifestChanged']
            ? $this->manifestStore->encodeManifest($reconciliation['candidateDocument'])
            : $originalManifest['bytes'];
        $candidateIdentity = self::identity($candidateBytes);
        $approvedPlanningPayload = DependencySyncVerificationCodec::encodeApprovedPlanningPayload(
            $approvedPlan,
            $candidateIdentity,
        );

        if (!$executionPolicy->apply()) {
            return self::result($approvedPlan, false, $reconciliation);
        }

        $expectedLockIdentity = $originalLock['identity'] ?? null;

        return $this->withProjectLock(
            $projectRoot,
            function () use (
                $projectRoot,
                $intent,
                $approvedPlan,
                $executionPolicy,
                $originalManifest,
                $originalLock,
                $expectedLockIdentity,
                $reconciliation,
                $candidateBytes,
                $candidateIdentity,
                $approvedPlanningPayload,
            ): ProjectDependencySyncResult {
                $this->manifestStore->assertProjectStateUnchanged(
                    $projectRoot,
                    $originalManifest['identity'],
                    $expectedLockIdentity,
                );

                $replanned = $this->planner->plan(
                    $projectRoot,
                    $intent,
                );

                if (!$replanned->equals($approvedPlan)) {
                    throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_STATE_CHANGED);
                }

                $this->manifestStore->assertProjectStateUnchanged(
                    $projectRoot,
                    $originalManifest['identity'],
                    $expectedLockIdentity,
                );

                $protectedState = [];

                if ($reconciliation['protectedThirdPartyRoots'] !== []) {
                    if ($originalLock === null) {
                        throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY);
                    }

                    $protectedState = $this->installedProjectVerifier
                        ->captureProtectedThirdPartyRootState(
                            $originalLock['document'],
                            $reconciliation['protectedThirdPartyRoots'],
                        );
                }

                $this->preflightVerificationEnvelope(
                    $approvedPlanningPayload,
                    $protectedState,
                );

                if (self::manifestDisablesLock($originalManifest['document'])) {
                    throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY);
                }

                if (!$reconciliation['manifestChanged']) {
                    return $this->synchronizeUnchangedManifest(
                        $projectRoot,
                        $approvedPlan,
                        $executionPolicy,
                        $originalManifest,
                        $originalLock,
                        $expectedLockIdentity,
                        $reconciliation,
                        $candidateIdentity,
                        $approvedPlanningPayload,
                        $protectedState,
                    );
                }

                return $this->synchronizeChangedManifest(
                    $projectRoot,
                    $approvedPlan,
                    $executionPolicy,
                    $originalManifest,
                    $originalLock,
                    $expectedLockIdentity,
                    $reconciliation,
                    $candidateBytes,
                    $candidateIdentity,
                    $approvedPlanningPayload,
                    $protectedState,
                );
            },
        );
    }

    /**
     * @param array{
     *     candidateDocument: \stdClass,
     *     manifestChanged: bool,
     *     managedRequireWrites: array<string, non-empty-string>,
     *     managedRequireRemovals: list<string>,
     *     preservedSatisfiers: array<string, non-empty-string>,
     *     protectedThirdPartyRoots: array<string, 'require'|'require-dev'>
     * } $reconciliation
     * @param array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null
     * }> $protectedState
     * @param array{bytes: string, document: \stdClass, identity: non-empty-string} $originalManifest
     * @param array{bytes: string, document: \stdClass, identity: non-empty-string}|null $originalLock
     */
    private function synchronizeUnchangedManifest(
        string $projectRoot,
        ProjectPackagePlan $approvedPlan,
        DependencySyncExecutionPolicy $executionPolicy,
        array $originalManifest,
        ?array $originalLock,
        ?string $expectedLockIdentity,
        array $reconciliation,
        string $candidateIdentity,
        string $approvedPlanningPayload,
        array $protectedState,
    ): ProjectDependencySyncResult {
        $lockCurrent = $this->runLockCurrentValidation($projectRoot);

        $this->manifestStore->assertProjectStateUnchanged(
            $projectRoot,
            $originalManifest['identity'],
            $expectedLockIdentity,
        );

        if (!$lockCurrent) {
            if (
                $protectedState !== []
                || !$executionPolicy->allowBroadUpdate()
                || self::hasAbsentProtectedDevelopmentRoot($protectedState)
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY);
            }

            return $this->executeEffect(
                projectRoot: $projectRoot,
                approvedPlan: $approvedPlan,
                originalManifest: $originalManifest,
                originalLock: $originalLock,
                expectedLockIdentity: $expectedLockIdentity,
                reconciliation: $reconciliation,
                candidateBytes: $originalManifest['bytes'],
                candidateIdentity: $candidateIdentity,
                approvedPlanningPayload: $approvedPlanningPayload,
                protectedState: $protectedState,
                composerArguments: $this->effectArguments(['update'], $executionPolicy),
                publishCandidate: false,
            );
        }

        if ($originalLock === null) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_LOCK_STALE);
        }

        $verificationInput = DependencySyncVerificationCodec::encodeEnvelope(
            $approvedPlanningPayload,
            $protectedState,
            $originalLock['identity'],
        );

        try {
            $this->runFreshVerifier(
                $projectRoot,
                $verificationInput,
            );

            return self::result(
                $approvedPlan,
                false,
                $reconciliation,
            );
        } catch (DependencySyncException $exception) {
            if ($exception->errorCode() !== DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE) {
                throw $exception;
            }

            if (
                !$executionPolicy->allowRepair()
                || self::hasAbsentProtectedDevelopmentRoot($protectedState)
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY);
            }
        }

        return $this->executeEffect(
            projectRoot: $projectRoot,
            approvedPlan: $approvedPlan,
            originalManifest: $originalManifest,
            originalLock: $originalLock,
            expectedLockIdentity: $expectedLockIdentity,
            reconciliation: $reconciliation,
            candidateBytes: $originalManifest['bytes'],
            candidateIdentity: $candidateIdentity,
            approvedPlanningPayload: $approvedPlanningPayload,
            protectedState: $protectedState,
            composerArguments: $this->effectArguments(['install'], $executionPolicy),
            publishCandidate: false,
        );
    }

    /**
     * @param array{
     *     candidateDocument: \stdClass,
     *     manifestChanged: bool,
     *     managedRequireWrites: array<string, non-empty-string>,
     *     managedRequireRemovals: list<string>,
     *     preservedSatisfiers: array<string, non-empty-string>,
     *     protectedThirdPartyRoots: array<string, 'require'|'require-dev'>
     * } $reconciliation
     * @param array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null
     * }> $protectedState
     * @param array{bytes: string, document: \stdClass, identity: non-empty-string} $originalManifest
     * @param array{bytes: string, document: \stdClass, identity: non-empty-string}|null $originalLock
     */
    private function synchronizeChangedManifest(
        string $projectRoot,
        ProjectPackagePlan $approvedPlan,
        DependencySyncExecutionPolicy $executionPolicy,
        array $originalManifest,
        ?array $originalLock,
        ?string $expectedLockIdentity,
        array $reconciliation,
        string $candidateBytes,
        string $candidateIdentity,
        string $approvedPlanningPayload,
        array $protectedState,
    ): ProjectDependencySyncResult {
        if (self::hasAbsentProtectedDevelopmentRoot($protectedState)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY);
        }

        $requiresBroadUpdate = $originalLock === null;

        if (!$requiresBroadUpdate) {
            $requiresBroadUpdate = !$this->runLockCurrentValidation($projectRoot);

            $this->manifestStore->assertProjectStateUnchanged(
                $projectRoot,
                $originalManifest['identity'],
                $expectedLockIdentity,
            );
        }

        if ($requiresBroadUpdate) {
            if (
                $protectedState !== []
                || !$executionPolicy->allowBroadUpdate()
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY);
            }

            $arguments = $this->effectArguments(
                ['update'],
                $executionPolicy,
            );
        } else {
            $targets = self::coretsiaUpdateTargets($reconciliation['candidateDocument']);

            if ($targets === []) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
            }

            $arguments = $this->effectArguments(
                [
                    'update',
                    ...$targets,
                    '--with-dependencies',
                ],
                $executionPolicy,
            );
        }

        return $this->executeEffect(
            projectRoot: $projectRoot,
            approvedPlan: $approvedPlan,
            originalManifest: $originalManifest,
            originalLock: $originalLock,
            expectedLockIdentity: $expectedLockIdentity,
            reconciliation: $reconciliation,
            candidateBytes: $candidateBytes,
            candidateIdentity: $candidateIdentity,
            approvedPlanningPayload: $approvedPlanningPayload,
            protectedState: $protectedState,
            composerArguments: $arguments,
            publishCandidate: true,
        );
    }

    /**
     * @param array{bytes: string, document: \stdClass, identity: non-empty-string} $originalManifest
     * @param array{bytes: string, document: \stdClass, identity: non-empty-string}|null $originalLock
     * @param array{
     *     candidateDocument: \stdClass,
     *     manifestChanged: bool,
     *     managedRequireWrites: array<string, non-empty-string>,
     *     managedRequireRemovals: list<string>,
     *     preservedSatisfiers: array<string, non-empty-string>,
     *     protectedThirdPartyRoots: array<string, 'require'|'require-dev'>
     * } $reconciliation
     * @param array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null
     * }> $protectedState
     * @param non-empty-list<string> $composerArguments
     */
    private function executeEffect(
        string $projectRoot,
        ProjectPackagePlan $approvedPlan,
        array $originalManifest,
        ?array $originalLock,
        ?string $expectedLockIdentity,
        array $reconciliation,
        string $candidateBytes,
        string $candidateIdentity,
        string $approvedPlanningPayload,
        array $protectedState,
        array $composerArguments,
        bool $publishCandidate,
    ): ProjectDependencySyncResult {
        $receiptId = $this->recoveryReceiptId();

        $this->manifestStore->assertProjectStateUnchanged(
            $projectRoot,
            $originalManifest['identity'],
            $expectedLockIdentity,
        );

        $this->manifestStore->writeRecoverySnapshot(
            $projectRoot,
            $receiptId,
            $originalManifest['bytes'],
            $originalLock['bytes'] ?? null,
        );

        if ($publishCandidate) {
            try {
                $this->manifestStore->publishManifestCandidate(
                    $projectRoot,
                    $candidateBytes,
                    $originalManifest['identity'],
                    $expectedLockIdentity,
                );
            } catch (DependencySyncException $exception) {
                $this->discardBeforeProcessStart(
                    $projectRoot,
                    $receiptId,
                    $exception,
                );
            }
        }

        try {
            $composer = $this->processRunner->runComposer(
                $projectRoot,
                $composerArguments,
                self::COMPOSER_TIMEOUT_SECONDS,
            );
        } catch (DependencySyncException $exception) {
            $this->handlePreStartComposerFailure(
                $projectRoot,
                $receiptId,
                $publishCandidate,
                $originalManifest['bytes'],
                $candidateIdentity,
                $expectedLockIdentity,
                $exception,
            );
        }

        if ($composer['timedOut'] || $composer['exitCode'] !== 0) {
            $this->recoveryRequired(
                $receiptId,
                DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
            );
        }

        try {
            $lockCurrent = $this->runLockCurrentValidation($projectRoot);
        } catch (DependencySyncException $exception) {
            $this->recoveryRequired(
                $receiptId,
                $exception->errorCode(),
            );
        }

        if (!$lockCurrent) {
            $this->recoveryRequired(
                $receiptId,
                DependencySyncErrorCodes::COMPOSER_LOCK_STALE,
            );
        }

        try {
            $currentManifest = $this->manifestStore->readManifest($projectRoot);

            if ($currentManifest['identity'] !== $candidateIdentity) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_STATE_CHANGED);
            }

            $currentLock = $this->manifestStore->readLockState($projectRoot);

            if ($currentLock === null) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_LOCK_STALE);
            }

            $verificationInput = DependencySyncVerificationCodec::encodeEnvelope(
                $approvedPlanningPayload,
                $protectedState,
                $currentLock['identity'],
            );

            $this->runFreshVerifier(
                $projectRoot,
                $verificationInput,
            );
        } catch (DependencySyncException $exception) {
            $this->recoveryRequired(
                $receiptId,
                $exception->errorCode(),
            );
        }

        $this->manifestStore->discardRecoverySnapshot(
            $projectRoot,
            $receiptId,
        );

        return self::result(
            $approvedPlan,
            true,
            $reconciliation,
        );
    }

    private function withProjectLock(
        string $projectRoot,
        Closure $operation,
    ): mixed {
        $var = $projectRoot . \DIRECTORY_SEPARATOR . 'var';

        try {
            if (
                (\file_exists($var) || \is_link($var))
                && !self::isNonSymlinkDirectory($var)
            ) {
                throw new \RuntimeException('dependency-sync-var-invalid');
            }
        } catch (\Throwable) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_SYNC_LOCK_FAILED);
        }

        $lockPath = $var
            . \DIRECTORY_SEPARATOR
            . 'locks'
            . \DIRECTORY_SEPARATOR
            . 'dependency-sync.lock';

        try {
            return $this->fileLock->exclusiveNonBlocking(
                $lockPath,
                $operation,
            );
        } catch (ScopedFileLockException $exception) {
            throw DependencySyncException::forCode(
                $exception->reason() === ScopedFileLockException::REASON_BUSY
                    ? DependencySyncErrorCodes::PROJECT_SYNC_LOCKED
                    : DependencySyncErrorCodes::PROJECT_SYNC_LOCK_FAILED,
            );
        }
    }

    private static function isNonSymlinkDirectory(string $path): bool
    {
        return \is_dir($path) && !\is_link($path);
    }

    private function runLockCurrentValidation(string $projectRoot): bool
    {
        $result = $this->processRunner->runComposer(
            $projectRoot,
            [
                'validate',
                '--no-check-publish',
                '--check-lock',
                '--no-interaction',
                '--no-plugins',
                '--no-scripts',
                '--no-ansi',
            ],
            self::COMPOSER_TIMEOUT_SECONDS,
        );

        if ($result['timedOut']) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
        }

        return $result['exitCode'] === 0;
    }

    /** @param non-empty-list<string> $operation */
    private function effectArguments(
        array $operation,
        DependencySyncExecutionPolicy $executionPolicy,
    ): array {
        $arguments = [
            ...$operation,
            '--no-interaction',
            '--no-ansi',
            '--no-progress',
        ];

        if (!$executionPolicy->allowComposerScripts()) {
            $arguments[] = '--no-scripts';
        }

        if (!$executionPolicy->allowComposerPlugins()) {
            $arguments[] = '--no-plugins';
        }

        return $arguments;
    }

    private function runFreshVerifier(
        string $projectRoot,
        string $verificationInput,
    ): void {
        if (\strlen($verificationInput) > ProjectDependencySync::MAX_VERIFICATION_INPUT_BYTES) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID);
        }

        $script = $this->kernelPackageRoot
            . \DIRECTORY_SEPARATOR
            . 'bin'
            . \DIRECTORY_SEPARATOR
            . 'dependency-sync-verify.php';

        $result = $this->processRunner->runPhpScript(
            $projectRoot,
            $script,
            [],
            self::VERIFICATION_TIMEOUT_SECONDS,
            $verificationInput,
        );
        $stdout = $result['stdout'];

        if (
            $result['timedOut']
            || \strlen($stdout) > self::MAX_VERIFICATION_PROTOCOL_BYTES
            || !\in_array($result['exitCode'], [0, 2], true)
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::VERIFICATION_EXECUTION_FAILED);
        }

        try {
            $protocol = StableJsonDecoder::decodeStableMap($stdout);
            $keys = \array_keys($protocol);
            \sort($keys, \SORT_STRING);

            if ($result['exitCode'] === 0) {
                if (
                    $keys !== ['schemaVersion', 'status']
                    || $protocol['schemaVersion'] !== 1
                    || $protocol['status'] !== 'ok'
                ) {
                    throw new \UnexpectedValueException();
                }

                $canonical = \json_encode(
                    [
                        'schemaVersion' => 1,
                        'status' => 'ok',
                    ],
                    \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
                );

                if ($stdout !== $canonical) {
                    throw new \UnexpectedValueException();
                }

                return;
            }

            if (
                $keys !== ['code', 'schemaVersion', 'status']
                || $protocol['schemaVersion'] !== 1
                || $protocol['status'] !== 'error'
                || !\is_string($protocol['code'])
                || !DependencySyncErrorCodes::has($protocol['code'])
            ) {
                throw new \UnexpectedValueException();
            }

            $canonical = \json_encode(
                [
                    'schemaVersion' => 1,
                    'status' => 'error',
                    'code' => $protocol['code'],
                ],
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
            );

            if ($stdout !== $canonical) {
                throw new \UnexpectedValueException();
            }
        } catch (\Throwable) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::VERIFICATION_EXECUTION_FAILED);
        }

        throw DependencySyncException::forCode($protocol['code']);
    }

    /**
     * @param array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null
     * }> $protectedState
     */
    private function preflightVerificationEnvelope(
        string $approvedPlanningPayload,
        array $protectedState,
    ): void {
        $input = DependencySyncVerificationCodec::encodeEnvelope(
            $approvedPlanningPayload,
            $protectedState,
            self::PLACEHOLDER_LOCK_IDENTITY,
        );

        if (\strlen($input) > ProjectDependencySync::MAX_VERIFICATION_INPUT_BYTES) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID);
        }
    }

    private function recoveryReceiptId(): string
    {
        try {
            $id = $this->idGenerator->generate();
        } catch (IdGenerationFailedException) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED);
        }

        if (\preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $id) !== 1) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED);
        }

        return $id;
    }

    private function handlePreStartComposerFailure(
        string $projectRoot,
        string $receiptId,
        bool $publishedCandidate,
        string $originalManifestBytes,
        string $candidateIdentity,
        ?string $expectedLockIdentity,
        DependencySyncException $_exception,
    ): never {
        if ($publishedCandidate) {
            try {
                $this->manifestStore->restorePreComposerState(
                    $projectRoot,
                    $originalManifestBytes,
                    $candidateIdentity,
                    $expectedLockIdentity,
                );
            } catch (DependencySyncException $restoreFailure) {
                $causeCode = $restoreFailure->errorCode() === DependencySyncErrorCodes::PROJECT_STATE_CHANGED
                    ? DependencySyncErrorCodes::PROJECT_STATE_CHANGED
                    : DependencySyncErrorCodes::COMPOSER_MANIFEST_WRITE_FAILED;

                $this->recoveryRequired(
                    $receiptId,
                    $causeCode,
                );
            }
        }

        try {
            $this->manifestStore->discardRecoverySnapshot(
                $projectRoot,
                $receiptId,
            );
        } catch (DependencySyncException) {
            throw DependencySyncException::forCode(
                DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED,
                ['recoveryReceiptId' => $receiptId],
            );
        }

        throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
    }

    private function discardBeforeProcessStart(
        string $projectRoot,
        string $receiptId,
        DependencySyncException $failure,
    ): never {
        if ($failure->errorCode() === DependencySyncErrorCodes::RECOVERY_REQUIRED) {
            $this->recoveryRequired(
                $receiptId,
                $failure->context()['causeCode'] ?? DependencySyncErrorCodes::PROJECT_STATE_CHANGED,
            );
        }

        try {
            $this->manifestStore->discardRecoverySnapshot(
                $projectRoot,
                $receiptId,
            );
        } catch (DependencySyncException) {
            throw DependencySyncException::forCode(
                DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED,
                ['recoveryReceiptId' => $receiptId],
            );
        }

        throw $failure;
    }

    private function recoveryRequired(
        string $receiptId,
        string $causeCode,
    ): never {
        throw DependencySyncException::forCode(
            DependencySyncErrorCodes::RECOVERY_REQUIRED,
            [
                'recoveryReceiptId' => $receiptId,
                'causeCode' => $causeCode,
            ],
        );
    }

    /**
     * @param array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null
     * }> $protectedState
     */
    private static function hasAbsentProtectedDevelopmentRoot(
        array $protectedState,
    ): bool {
        foreach ($protectedState as $state) {
            if (!$state['runtimeRequired'] && !$state['installed']) {
                return true;
            }
        }

        return false;
    }

    private static function manifestDisablesLock(\stdClass $manifest): bool
    {
        if (!\property_exists($manifest, 'config')) {
            return false;
        }

        if (!$manifest->config instanceof \stdClass) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID);
        }

        return \property_exists($manifest->config, 'lock') && $manifest->config->lock === false;
    }

    /** @return list<string> */
    private static function coretsiaUpdateTargets(\stdClass $manifest): array
    {
        if (!\property_exists($manifest, 'require') || !$manifest->require instanceof \stdClass) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID);
        }

        $targets = [];

        foreach (\get_object_vars($manifest->require) as $packageName => $_constraint) {
            if (\str_starts_with($packageName, 'coretsia/')) {
                $targets[] = $packageName;
            }
        }

        \sort($targets, \SORT_STRING);

        return $targets;
    }

    /**
     * @param array{
     *     candidateDocument: \stdClass,
     *     manifestChanged: bool,
     *     managedRequireWrites: array<string, non-empty-string>,
     *     managedRequireRemovals: list<string>,
     *     preservedSatisfiers: array<string, non-empty-string>,
     *     protectedThirdPartyRoots: array<string, 'require'|'require-dev'>
     * } $reconciliation
     */
    private static function result(
        ProjectPackagePlan $plan,
        bool $changed,
        array $reconciliation,
    ): ProjectDependencySyncResult {
        return new ProjectDependencySyncResult(
            $plan,
            $changed,
            $reconciliation['managedRequireWrites'],
            $reconciliation['managedRequireRemovals'],
            $reconciliation['preservedSatisfiers'],
        );
    }

    private static function identity(string $bytes): string
    {
        return 'sha256:' . \hash('sha256', $bytes);
    }
}
