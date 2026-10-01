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

use Composer\InstalledVersions;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Config\Source\ComposerPackageInstallPathResolver;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectPackagePlan;
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;
use Coretsia\Kernel\Module\Exception\ModuleResolutionException;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;

/** @internal */
final readonly class InstalledProjectVerifier
{
    /** @param array<string, mixed> $kernelConfig */
    public function __construct(
        private BootstrapConfigResolver $bootstrapConfigResolver,
        private ModuleResolutionOrchestrator $moduleResolutionOrchestrator,
        private ComposerManifestStore $manifestStore,
        private ComposerPackageInstallPathResolver $installPathResolver,
        private array $kernelConfig,
    ) {
    }

    /**
     * @param array<string, 'require'|'require-dev'> $protectedThirdPartyRoots
     *
     * @return array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null,
     * }>
     */
    public function captureProtectedThirdPartyRootState(
        \stdClass $lockDocument,
        array $protectedThirdPartyRoots,
    ): array {
        $allPackages = $this->manifestStore->allLockPackages($lockDocument);
        $state = [];
        $keys = \array_keys($protectedThirdPartyRoots);
        $sortedKeys = $keys;
        \sort($sortedKeys, \SORT_STRING);

        if ($keys !== $sortedKeys) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_LOCK_STALE);
        }

        foreach ($protectedThirdPartyRoots as $packageName => $section) {
            if (
                ($section !== 'require' && $section !== 'require-dev')
                || !isset($allPackages[$packageName])
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_LOCK_STALE);
            }

            $lockRecord = $allPackages[$packageName];
            $runtimeRequired = $section === 'require';
            $installed = self::isInstalled(
                $packageName,
                DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE,
            );

            if ($runtimeRequired && !$installed) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE);
            }

            $installedReference = null;

            if ($installed) {
                $installedReference = $this->assertInstalledIdentity(
                    $packageName,
                    $lockRecord,
                    DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE,
                );
            }

            $state[$packageName] = [
                'runtimeRequired' => $runtimeRequired,
                'installed' => $installed,
                'type' => $lockRecord['type'],
                'version' => $lockRecord['version'],
                'sourceReference' => $lockRecord['sourceReference'],
                'distReference' => $lockRecord['distReference'],
                'installedReference' => $installedReference,
            ];
        }

        return $state;
    }

    /**
     * @param array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null,
     * }> $protectedThirdPartyRootState
     */
    public function verify(
        string $projectRoot,
        ProjectPackagePlan $approvedPlan,
        string $expectedComposerJsonIdentity,
        string $expectedComposerLockIdentity,
        array $protectedThirdPartyRootState,
    ): void {
        $manifestState = $this->manifestStore->readManifest($projectRoot);

        if ($manifestState['identity'] !== $expectedComposerJsonIdentity) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_STATE_CHANGED);
        }

        $lockState = $this->manifestStore->readLockState($projectRoot);

        if (
            $lockState === null
            || $lockState['identity'] !== $expectedComposerLockIdentity
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_STATE_CHANGED);
        }

        $this->assertProtectedThirdPartyRootState(
            $lockState['document'],
            $protectedThirdPartyRootState,
        );

        $runtimePackages = $this->assertComposerRuntimeState(
            $projectRoot,
            $expectedComposerJsonIdentity,
            $expectedComposerLockIdentity,
        );

        foreach ($approvedPlan->expectedModuleMetadataById() as $metadata) {
            if (!isset($runtimePackages[$metadata['composerName']])) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE);
            }
        }

        foreach ($approvedPlan->applications() as $targetName) {
            try {
                $target = AppTarget::fromString($targetName);
                $verificationInput = new BootstrapInput(
                    applicationRoot: $projectRoot,
                    appTarget: $target,
                    preset: $approvedPlan->fixedPresetByTarget()[$targetName] ?? null,
                );
                $verificationConfig = $this->bootstrapConfigResolver->resolve(
                    $verificationInput,
                    $this->kernelConfig,
                );

                if ($verificationConfig->preset() !== $approvedPlan->effectivePresetsByTarget()[$targetName]) {
                    self::planMismatch();
                }

                $resolution = $this->moduleResolutionOrchestrator->resolve($verificationConfig);
                $installedManifest = $resolution->manifest();
                $installedPlan = $resolution->plan();
            } catch (ModuleManifestInvalidException $exception) {
                throw DependencySyncException::forCode(
                    DependencySyncErrorCodes::INSTALLED_METADATA_INVALID,
                    self::sourceContext($exception),
                );
            } catch (ModuleResolutionException $exception) {
                throw DependencySyncException::forCode(
                    DependencySyncErrorCodes::INSTALLED_PLAN_MISMATCH,
                    self::sourceContext($exception),
                );
            } catch (BootstrapException) {
                self::planMismatch();
            }

            $expectedEnabled = $approvedPlan->enabledModuleIdsByTarget()[$targetName];
            $expectedExcluded = $approvedPlan->excludedModuleIdsByTarget()[$targetName];
            $actualEnabled = self::moduleIdsToStrings($installedPlan->enabled());
            $actualExcluded = self::moduleIdsToStrings($installedPlan->excluded());

            if ($actualEnabled !== $expectedEnabled || $actualExcluded !== $expectedExcluded) {
                self::planMismatch();
            }

            foreach ($expectedEnabled as $moduleId) {
                if (!$installedManifest->has($moduleId)) {
                    self::planMismatch();
                }
            }

            $modules = $installedPlan->modules();
            $expectedMetadata = $approvedPlan->expectedModuleMetadataById();

            if (\array_keys($modules) !== $expectedEnabled) {
                self::planMismatch();
            }

            foreach ($modules as $moduleId => $entry) {
                $expected = $expectedMetadata[$moduleId] ?? null;

                if (
                    $expected === null
                    || $entry->composerName() !== $expected['composerName']
                    || self::moduleIdsToStrings($entry->requires()) !== $expected['requires']
                    || self::moduleIdsToStrings($entry->conflicts()) !== $expected['conflicts']
                ) {
                    self::planMismatch();
                }
            }
        }

        $this->assertComposerRuntimeState(
            $projectRoot,
            $expectedComposerJsonIdentity,
            $expectedComposerLockIdentity,
        );
    }

    /**
     * @return array<string, array{
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     * }>
     */
    private function assertComposerRuntimeState(
        string $projectRoot,
        string $expectedComposerJsonIdentity,
        string $expectedComposerLockIdentity,
    ): array {
        $manifestState = $this->manifestStore->readManifest($projectRoot);

        if ($manifestState['identity'] !== $expectedComposerJsonIdentity) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_STATE_CHANGED);
        }

        $lockState = $this->manifestStore->readLockState($projectRoot);

        if (
            $lockState === null
            || $lockState['identity'] !== $expectedComposerLockIdentity
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_STATE_CHANGED);
        }

        $runtimePackages = $this->manifestStore->runtimeLockPackages($lockState['document']);

        foreach ($runtimePackages as $packageName => $record) {
            if (!self::isInstalled($packageName, DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE)) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE);
            }

            $this->assertInstalledIdentity(
                $packageName,
                $record,
                DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE,
            );
        }

        return $runtimePackages;
    }

    /**
     * @param array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null,
     * }> $frozenState
     */
    private function assertProtectedThirdPartyRootState(
        \stdClass $lockDocument,
        array $frozenState,
    ): void {
        $allPackages = $this->manifestStore->allLockPackages($lockDocument);

        foreach ($frozenState as $packageName => $frozen) {
            $current = $allPackages[$packageName] ?? null;

            if (
                $current === null
                || $current['type'] !== $frozen['type']
                || $current['version'] !== $frozen['version']
                || $current['sourceReference'] !== $frozen['sourceReference']
                || $current['distReference'] !== $frozen['distReference']
            ) {
                self::protectedRootChanged();
            }

            $installed = self::isInstalled(
                $packageName,
                DependencySyncErrorCodes::PROTECTED_THIRD_PARTY_ROOT_CHANGED,
            );

            if ($installed !== $frozen['installed']) {
                self::protectedRootChanged();
            }

            if (!$installed) {
                if ($frozen['installedReference'] !== null) {
                    self::protectedRootChanged();
                }
                continue;
            }

            $installedReference = $this->assertInstalledIdentity(
                $packageName,
                $current,
                DependencySyncErrorCodes::PROTECTED_THIRD_PARTY_ROOT_CHANGED,
            );

            if ($installedReference !== $frozen['installedReference']) {
                self::protectedRootChanged();
            }
        }
    }

    /**
     * @param array{
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     * } $record
     */
    private function assertInstalledIdentity(
        string $packageName,
        array $record,
        string $failureCode,
    ): ?string {
        try {
            $prettyVersion = InstalledVersions::getPrettyVersion($packageName);
            $installedReference = InstalledVersions::getReference($packageName);
        } catch (\Throwable) {
            throw DependencySyncException::forCode($failureCode);
        }

        if ($prettyVersion !== $record['version']) {
            throw DependencySyncException::forCode($failureCode);
        }

        $references = [];

        foreach ([$record['sourceReference'], $record['distReference']] as $reference) {
            if ($reference !== null) {
                $references[$reference] = true;
            }
        }

        if (
            ($references === [] && $installedReference !== null)
            || ($references !== [] && (!\is_string($installedReference) || !isset($references[$installedReference])))
        ) {
            throw DependencySyncException::forCode($failureCode);
        }

        if ($record['type'] !== 'metapackage') {
            try {
                $this->installPathResolver->resolve($packageName);
            } catch (ConfigInvalidException) {
                throw DependencySyncException::forCode($failureCode);
            }
        }

        return $installedReference;
    }

    private static function isInstalled(string $packageName, string $failureCode): bool
    {
        try {
            return InstalledVersions::isInstalled($packageName);
        } catch (\Throwable) {
            throw DependencySyncException::forCode($failureCode);
        }
    }

    /**
     * @param list<ModuleId> $moduleIds
     * @return list<string>
     */
    private static function moduleIdsToStrings(array $moduleIds): array
    {
        return \array_map(
            static fn (ModuleId $moduleId): string => $moduleId->value(),
            $moduleIds,
        );
    }

    /** @return array<string, non-empty-string> */
    private static function sourceContext(ModuleResolutionException $exception): array
    {
        return [
            'sourceErrorCode' => $exception->errorCode(),
            'sourceReason' => $exception->reason(),
        ];
    }

    private static function planMismatch(): never
    {
        throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLED_PLAN_MISMATCH);
    }

    private static function protectedRootChanged(): never
    {
        throw DependencySyncException::forCode(DependencySyncErrorCodes::PROTECTED_THIRD_PARTY_ROOT_CHANGED);
    }
}
