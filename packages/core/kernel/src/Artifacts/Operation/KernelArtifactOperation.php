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

namespace Coretsia\Kernel\Artifacts\Operation;

use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler;
use Coretsia\Kernel\Artifacts\Verifier\CacheVerifier;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Config\Source\ConfigSourceSet;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\ModuleResolution;

/**
 * Canonical Kernel compile-host input preparation and routing operation.
 *
 * @internal
 */
final readonly class KernelArtifactOperation
{
    /**
     * @param array<string,mixed> $kernelConfig Strict `kernel` config subtree.
     */
    public function __construct(
        private BootstrapConfigResolver $bootstrapConfigResolver,
        private EnvRepositoryBuilder $envRepositoryBuilder,
        private ModulePlanResolver $modulePlanResolver,
        private ConfigSourceLocationBuilder $configSourceLocationBuilder,
        private ArtifactCompiler $artifactCompiler,
        private CacheVerifier $cacheVerifier,
        private array $kernelConfig,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function compile(
        BootstrapInput $input,
    ): array {
        $prepared = $this->prepare($input);

        return $this->artifactCompiler->compile(
            bootstrapConfig: $prepared['bootstrapConfig'],
            moduleResolution: $prepared['moduleResolution'],
            env: $prepared['env'],
            kernelConfig: $this->kernelConfig,
            configSources: $prepared['configSources'],
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function verify(
        BootstrapInput $input,
    ): array {
        $prepared = $this->prepare($input);

        return $this->cacheVerifier->verify(
            bootstrapConfig: $prepared['bootstrapConfig'],
            moduleResolution: $prepared['moduleResolution'],
            env: $prepared['env'],
            kernelConfig: $this->kernelConfig,
            configSources: $prepared['configSources'],
        );
    }

    /**
     * @return array{
     *     bootstrapConfig: BootstrapConfig,
     *     env: EnvRepositoryInterface,
     *     moduleResolution: ModuleResolution,
     *     configSources: ConfigSourceSet
     * }
     */
    private function prepare(
        BootstrapInput $input,
    ): array {
        $bootstrapConfig = $this->bootstrapConfigResolver->resolve(
            $input,
            $this->kernelConfig,
        );

        $env = $this->envRepositoryBuilder->build(
            $bootstrapConfig,
            $this->kernelConfig,
        );

        $moduleResolution = $this->modulePlanResolver->resolveResolution(
            $bootstrapConfig,
        );

        $configSources = $this->configSourceLocationBuilder->build(
            $bootstrapConfig,
            $moduleResolution,
        );

        return [
            'bootstrapConfig' => $bootstrapConfig,
            'env' => $env,
            'moduleResolution' => $moduleResolution,
            'configSources' => $configSources,
        ];
    }
}
