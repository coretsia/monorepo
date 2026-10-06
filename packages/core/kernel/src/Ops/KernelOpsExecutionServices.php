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

namespace Coretsia\Kernel\Ops;

use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;

/**
 * Immutable wiring value containing the baseline-configured services used by
 * target-aware Kernel Ops execution.
 *
 * The dedicated execution container that constructs these services is not
 * retained or exposed by this value.
 *
 * @internal Kernel source-operations-host wiring value.
 */
final readonly class KernelOpsExecutionServices
{
    /**
     * @param array<string,mixed> $kernelConfig Exact baseline `kernel` config subtree.
     */
    public function __construct(
        private array $kernelConfig,
        private BootstrapConfigResolver $bootstrapConfigResolver,
        private EnvRepositoryBuilder $envRepositoryBuilder,
        private ModuleResolutionOrchestrator $moduleResolutionOrchestrator,
        private ConfigKernel $configKernel,
        private RuntimeContainerGraphCompiler $runtimeContainerGraphCompiler,
        private ConfigFingerprintInputBuilder $configFingerprintInputBuilder,
        private FingerprintCalculator $fingerprintCalculator,
        private ConfigSourceLocationBuilder $configSourceLocationBuilder,
        private KernelArtifactOperation $kernelArtifactOperation,
    ) {
        if (!self::isStringMap($this->kernelConfig)) {
            throw new \InvalidArgumentException('kernel-ops-execution-kernel-config-invalid');
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function kernelConfig(): array
    {
        return $this->kernelConfig;
    }

    public function bootstrapConfigResolver(): BootstrapConfigResolver
    {
        return $this->bootstrapConfigResolver;
    }

    public function envRepositoryBuilder(): EnvRepositoryBuilder
    {
        return $this->envRepositoryBuilder;
    }

    public function moduleResolutionOrchestrator(): ModuleResolutionOrchestrator
    {
        return $this->moduleResolutionOrchestrator;
    }

    public function configKernel(): ConfigKernel
    {
        return $this->configKernel;
    }

    public function runtimeContainerGraphCompiler(): RuntimeContainerGraphCompiler
    {
        return $this->runtimeContainerGraphCompiler;
    }

    public function configFingerprintInputBuilder(): ConfigFingerprintInputBuilder
    {
        return $this->configFingerprintInputBuilder;
    }

    public function fingerprintCalculator(): FingerprintCalculator
    {
        return $this->fingerprintCalculator;
    }

    public function configSourceLocationBuilder(): ConfigSourceLocationBuilder
    {
        return $this->configSourceLocationBuilder;
    }

    public function kernelArtifactOperation(): KernelArtifactOperation
    {
        return $this->kernelArtifactOperation;
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private static function isStringMap(array $value): bool
    {
        if ($value !== [] && \array_is_list($value)) {
            return false;
        }

        foreach ($value as $key => $_item) {
            if (!\is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
