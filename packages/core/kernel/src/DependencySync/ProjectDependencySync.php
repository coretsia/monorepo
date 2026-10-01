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

namespace Coretsia\Kernel\DependencySync;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Module\FoundationModule;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\DependencySync\Catalog\ReleaseInstallationCatalogLoader;
use Coretsia\Kernel\DependencySync\Composer\ComposerManifestStore;
use Coretsia\Kernel\DependencySync\Composer\ComposerRootReconciler;
use Coretsia\Kernel\DependencySync\Composer\ComposerSyncCoordinator;
use Coretsia\Kernel\DependencySync\Composer\InstalledProjectVerifier;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\Process\DependencySyncProcessRunner;
use Coretsia\Kernel\DependencySync\Verification\DependencySyncVerificationCodec;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Provider\KernelServiceFactory;

final readonly class ProjectDependencySync
{
    public const int MAX_VERIFICATION_INPUT_BYTES = 1_048_576;

    private const array BASELINE_CONFIG_RELATIVE_PATHS = [
        'config/foundation.php' => true,
        'config/kernel.php' => true,
    ];

    private function __construct(
        private ProjectPackagePlanner $planner,
        private ComposerManifestStore $manifestStore,
        private ComposerSyncCoordinator $syncCoordinator,
        private InstalledProjectVerifier $installedProjectVerifier,
    ) {
    }

    public static function create(
        string $composerExecutable = 'composer',
    ): self {
        try {
            $installPathResolver = KernelServiceFactory::composerPackageInstallPathResolver();
            $foundationRoot = $installPathResolver->resolve(FoundationModule::COMPOSER_PACKAGE);
            $kernelRoot = $installPathResolver->resolve(KernelModule::COMPOSER_PACKAGE);

            $foundationConfig = self::loadRequiredPackageConfig(
                $foundationRoot,
                'config/foundation.php',
            );
            $kernelConfig = self::loadRequiredPackageConfig(
                $kernelRoot,
                'config/kernel.php',
            );

            $builder = new ContainerBuilder(
                config: [
                    FoundationModule::CONFIG_ROOT => $foundationConfig,
                    KernelModule::CONFIG_ROOT => $kernelConfig,
                ],
            );
            $builder->registerProviders(
                self::baselineSourceProviders(),
            );
            $container = $builder->build();

            $bootstrapConfigResolver = $container->get(BootstrapConfigResolver::class);
            $moduleResolutionOrchestrator = $container->get(ModuleResolutionOrchestrator::class);
            $moduleIdSetNormalizer = $container->get(ModuleIdSetNormalizer::class);
            $moduleGraphResolver = $container->get(ModuleGraphResolver::class);
            $idGenerator = $container->get(IdGeneratorInterface::class);

            if (
                !$bootstrapConfigResolver instanceof BootstrapConfigResolver
                || !$moduleResolutionOrchestrator instanceof ModuleResolutionOrchestrator
                || !$moduleIdSetNormalizer instanceof ModuleIdSetNormalizer
                || !$moduleGraphResolver instanceof ModuleGraphResolver
                || !$idGenerator instanceof IdGeneratorInterface
            ) {
                throw new \UnexpectedValueException('dependency-sync-baseline-service-invalid');
            }

            $catalogLoader = new ReleaseInstallationCatalogLoader();
            $planner = new ProjectPackagePlanner(
                $bootstrapConfigResolver,
                $moduleResolutionOrchestrator,
                $moduleGraphResolver,
                $moduleIdSetNormalizer,
                $catalogLoader,
                $kernelConfig,
            );
            $manifestStore = new ComposerManifestStore();
            $installedProjectVerifier = new InstalledProjectVerifier(
                $bootstrapConfigResolver,
                $moduleResolutionOrchestrator,
                $manifestStore,
                $installPathResolver,
                $kernelConfig,
            );
            $syncCoordinator = new ComposerSyncCoordinator(
                $planner,
                new ComposerRootReconciler(),
                $manifestStore,
                new DependencySyncProcessRunner($composerExecutable),
                $installedProjectVerifier,
                new ScopedFileLock(),
                $idGenerator,
                $kernelRoot,
            );

            return new self(
                $planner,
                $manifestStore,
                $syncCoordinator,
                $installedProjectVerifier,
            );
        } catch (DependencySyncException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::BASELINE_NOT_INSTALLED);
        }
    }

    public function plan(
        string $projectRoot,
        ProjectInstallationIntent $intent,
    ): ProjectPackagePlan {
        return $this->planner->plan(
            $this->requireActiveConsumerProjectRoot($projectRoot),
            $intent,
        );
    }

    public function synchronize(
        string $projectRoot,
        ProjectInstallationIntent $intent,
        DependencySyncExecutionPolicy $executionPolicy,
    ): ProjectDependencySyncResult {
        $projectRoot = $this->requireActiveConsumerProjectRoot($projectRoot);
        $approvedPlan = $this->planner->plan(
            $projectRoot,
            $intent,
        );

        return $this->syncCoordinator->synchronize(
            $projectRoot,
            $intent,
            $approvedPlan,
            $executionPolicy,
        );
    }

    public function verifyInstalled(
        string $projectRoot,
        string $verificationInput,
    ): void {
        $projectRoot = $this->requireActiveConsumerProjectRoot($projectRoot);

        if (\strlen($verificationInput) > self::MAX_VERIFICATION_INPUT_BYTES) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID);
        }

        $decoded = DependencySyncVerificationCodec::decodeEnvelope($verificationInput);

        $this->installedProjectVerifier->verify(
            $projectRoot,
            $decoded['plan'],
            $decoded['composerJsonCandidateIdentity'],
            $decoded['composerLockIdentity'],
            $decoded['protectedThirdPartyRootState'],
        );
    }

    private function requireActiveConsumerProjectRoot(
        string $projectRoot,
    ): string {
        $canonical = @\realpath($projectRoot);

        if (!\is_string($canonical) || !\is_dir($canonical)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_ROOT_INVALID);
        }

        $composerJson = $canonical . \DIRECTORY_SEPARATOR . 'composer.json';
        $autoload = $canonical
            . \DIRECTORY_SEPARATOR
            . 'vendor'
            . \DIRECTORY_SEPARATOR
            . 'autoload.php';
        $composerRuntime = $canonical
            . \DIRECTORY_SEPARATOR
            . 'vendor'
            . \DIRECTORY_SEPARATOR
            . 'composer';

        if (!\is_file($composerJson) || !\is_file($autoload)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::BASELINE_NOT_INSTALLED);
        }

        try {
            $activeRuntime = ComposerInstalledMetadataProvider::activeComposerRuntimeDirectory();
            $projectRuntime = @\realpath($composerRuntime);
        } catch (\Throwable) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::BASELINE_NOT_INSTALLED);
        }

        if (
            !\is_string($projectRuntime)
            || !\is_dir($projectRuntime)
            || $projectRuntime !== $activeRuntime
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_ROOT_INVALID);
        }

        $manifest = $this->manifestStore->readManifest($canonical);
        $document = $manifest['document'];

        if (
            \property_exists($document, 'name')
            && \is_string($document->name)
            && \strtolower($document->name) === 'coretsia/monorepo'
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_ROOT_INVALID);
        }

        return $canonical;
    }

    /** @return array<string, mixed> */
    private static function loadRequiredPackageConfig(
        string $packageRoot,
        string $relativePath,
    ): array {
        if (!isset(self::BASELINE_CONFIG_RELATIVE_PATHS[$relativePath])) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::BASELINE_NOT_INSTALLED);
        }

        $configPath = $packageRoot
            . \DIRECTORY_SEPARATOR
            . \str_replace('/', \DIRECTORY_SEPARATOR, $relativePath);

        \set_error_handler(
            static function (
                int $severity,
                string $_message,
                string $_file,
                int $_line,
            ): never {
                throw new \ErrorException(
                    'dependency-sync-baseline-config-read-failed',
                    0,
                    $severity,
                );
            },
        );

        try {
            if (
                !\is_file($configPath)
                || !\is_readable($configPath)
            ) {
                throw new \UnexpectedValueException('dependency-sync-baseline-config-unavailable');
            }

            $config = require $configPath;

            if (!\is_array($config)) {
                throw new \UnexpectedValueException('dependency-sync-baseline-config-return-invalid');
            }
        } catch (\Throwable) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::BASELINE_NOT_INSTALLED);
        } finally {
            \restore_error_handler();
        }

        return $config;
    }

    /** @return list<ServiceProviderInterface> */
    private static function baselineSourceProviders(): array
    {
        try {
            $providerClasses = [
                ...new FoundationModule()->providers(),
                ...new KernelModule()->providers(),
            ];

            $providers = [];

            foreach ($providerClasses as $providerClass) {
                $provider = new $providerClass();

                if (!$provider instanceof ServiceProviderInterface) {
                    throw new \UnexpectedValueException('dependency-sync-baseline-provider-invalid');
                }

                $providers[] = $provider;
            }

            return $providers;
        } catch (\Throwable) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::BASELINE_NOT_INSTALLED);
        }
    }
}
