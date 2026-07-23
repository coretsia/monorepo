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

namespace Coretsia\Kernel\Boot;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanArtifactHydrator;
use Coretsia\Kernel\Runtime\RuntimePathContext;

/**
 * Creates the exact entrypoint-owned runtime seed set from validated artifact
 * payloads and explicit artifact-runtime input.
 *
 * This factory does not run Bootstrap Phase A, ConfigKernel Phase B, Composer
 * discovery, preset loading, providers, graph compilation, or filesystem
 * discovery.
 *
 * @internal Kernel artifact-runtime boundary.
 */
final readonly class ArtifactRuntimeSeedFactory
{
    /**
     * @var list<string>
     */
    private const array CONFIG_PAYLOAD_KEYS = [
        'config',
        'configSourceFiles',
        'envOverlayMappings',
        'owners',
        'sources',
        'validation',
        'validationSubjects',
    ];

    public function __construct(
        private ModulePlanArtifactHydrator $modulePlanHydrator = new ModulePlanArtifactHydrator(),
    ) {
    }

    /**
     * @param array<string, mixed> $configPayload Already-read and validated
     *     `config@1` payload.
     * @param array<string, mixed> $moduleManifestPayload Already-read and
     *     validated `module-manifest@1` payload.
     */
    public function create(
        ArtifactRuntimeInput $input,
        array $configPayload,
        array $moduleManifestPayload,
    ): RuntimeContainerSeedSet {
        $config = self::configFromPayload($configPayload);
        $configRepository = new ArrayConfigRepository($config);
        $modulePlan = $this->modulePlanHydrator->hydrate($moduleManifestPayload);
        $runtimePathContext = new RuntimePathContext(
            skeletonRoot: $input->skeletonRoot(),
            artifactRoot: $input->artifactRoot(),
        );

        return new RuntimeContainerSeedSet([
            ConfigRepositoryInterface::class => $configRepository,
            ModulePlan::class => $modulePlan,
            RuntimePathContext::class => $runtimePathContext,
        ]);
    }

    /**
     * @param array<string, mixed> $configPayload
     *
     * @return array<string, mixed>
     */
    private static function configFromPayload(array $configPayload): array
    {
        self::assertExactKeys(
            map: $configPayload,
            expectedKeys: self::CONFIG_PAYLOAD_KEYS,
        );

        $config = $configPayload['config'] ?? null;

        if (!\is_array($config) || \array_is_list($config)) {
            throw new \InvalidArgumentException('artifact-runtime-config-payload-invalid');
        }

        /** @var array<string, mixed> $config */
        return $config;
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $expectedKeys
     */
    private static function assertExactKeys(
        array $map,
        array $expectedKeys,
    ): void {
        $actual = \array_keys($map);
        \sort($actual, \SORT_STRING);

        $expected = $expectedKeys;
        \sort($expected, \SORT_STRING);

        if ($actual !== $expected) {
            throw new \InvalidArgumentException('artifact-runtime-config-payload-invalid');
        }
    }
}
