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

namespace Coretsia\Kernel\Provider;

use Coretsia\Foundation\Container\Exception\ContainerException;

/**
 * Stateless Kernel service factory.
 *
 * This helper centralizes Kernel runtime wiring/validation that needs both DI
 * services and already-merged Kernel config.
 *
 * It intentionally keeps no mutable runtime state:
 *
 * - no static snapshots;
 * - no caches;
 * - no buffers;
 * - no retained container instance;
 * - no retained config payload.
 *
 * The caller owns when this factory is invoked and which config snapshot is
 * supplied. The factory only validates the small Kernel-owned subset it needs
 * for constructing or validating services.
 *
 * 1.270.0 intentionally does not build a UnitOfWork lifecycle executor here.
 * This epic owns stable UnitOfWork shapes and policy locks only. Runtime
 * lifecycle execution is introduced by later Kernel runtime epics.
 *
 * @internal Kernel provider wiring helper. Not part of the public Kernel API.
 */
final class KernelServiceFactory
{
    private const int DEFAULT_UOW_ATTRIBUTES_MAX_DEPTH = 10;

    private const int DEFAULT_UOW_ATTRIBUTES_MAX_KEYS = 200;

    private function __construct()
    {
    }

    /**
     * Resolves UnitOfWorkContext.attributes defensive limits.
     *
     * The values are read from the supplied Kernel config subtree:
     *
     *     kernel.uow.attributes.max_depth
     *     kernel.uow.attributes.max_keys
     *
     * If the keys are absent, the 1.270.0 defaults are used.
     *
     * @param array<string, mixed> $kernelConfig
     *
     * @return array{maxDepth: int<1, max>, maxKeys: int<1, max>}
     */
    public static function unitOfWorkAttributeLimits(array $kernelConfig): array
    {
        return [
            'maxDepth' => self::unitOfWorkAttributesMaxDepth($kernelConfig),
            'maxKeys' => self::unitOfWorkAttributesMaxKeys($kernelConfig),
        ];
    }

    /**
     * Resolves the maximum allowed depth for UnitOfWorkContext.attributes.
     *
     * @param array<string, mixed> $kernelConfig
     *
     * @return int<1, max>
     */
    public static function unitOfWorkAttributesMaxDepth(array $kernelConfig): int
    {
        $attributesConfig = self::uowAttributesConfig($kernelConfig);

        $maxDepth = $attributesConfig['max_depth'] ?? self::DEFAULT_UOW_ATTRIBUTES_MAX_DEPTH;

        if (!\is_int($maxDepth) || $maxDepth < 1) {
            throw new ContainerException('kernel-uow-attributes-max-depth-invalid');
        }

        return $maxDepth;
    }

    /**
     * Resolves the maximum allowed key count for UnitOfWorkContext.attributes.
     *
     * @param array<string, mixed> $kernelConfig
     *
     * @return int<1, max>
     */
    public static function unitOfWorkAttributesMaxKeys(array $kernelConfig): int
    {
        $attributesConfig = self::uowAttributesConfig($kernelConfig);

        $maxKeys = $attributesConfig['max_keys'] ?? self::DEFAULT_UOW_ATTRIBUTES_MAX_KEYS;

        if (!\is_int($maxKeys) || $maxKeys < 1) {
            throw new ContainerException('kernel-uow-attributes-max-keys-invalid');
        }

        return $maxKeys;
    }

    /**
     * @param array<string, mixed> $kernelConfig
     *
     * @return array<string, mixed>
     */
    private static function uowAttributesConfig(array $kernelConfig): array
    {
        $uowConfig = $kernelConfig['uow'] ?? [];

        if (!\is_array($uowConfig)) {
            throw new ContainerException('kernel-uow-config-invalid');
        }

        $attributesConfig = $uowConfig['attributes'] ?? [];

        if (!\is_array($attributesConfig)) {
            throw new ContainerException('kernel-uow-attributes-config-invalid');
        }

        return $attributesConfig;
    }
}
