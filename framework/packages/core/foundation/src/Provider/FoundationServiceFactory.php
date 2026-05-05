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

namespace Coretsia\Foundation\Provider;

use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\TagRegistry;
use Psr\Container\ContainerInterface;

/**
 * Stateless Foundation service factory.
 *
 * This helper centralizes Foundation runtime wiring that needs both DI services
 * and already-merged Foundation config.
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
 * supplied. The factory only validates the small Foundation-owned subset it
 * needs for constructing services.
 */
final class FoundationServiceFactory
{
    private const string TAG_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/';

    private function __construct()
    {
    }

    /**
     * Creates the Foundation reset orchestrator.
     *
     * The effective reset discovery tag is read from the supplied Foundation
     * config subtree:
     *
     *     foundation.reset.tag
     *
     * If the key is absent, the reserved default `kernel.reset` is used.
     *
     * @param array<string, mixed> $foundationConfig
     */
    public static function resetOrchestrator(
        ContainerInterface $container,
        TagRegistry $tagRegistry,
        array $foundationConfig,
    ): ResetOrchestrator {
        return new ResetOrchestrator(
            container: $container,
            tagRegistry: $tagRegistry,
            effectiveResetTag: self::effectiveResetTag($foundationConfig),
        );
    }

    /**
     * @param array<string, mixed> $foundationConfig
     */
    private static function effectiveResetTag(array $foundationConfig): string
    {
        $resetConfig = $foundationConfig['reset'] ?? [];

        if (!\is_array($resetConfig)) {
            throw new ContainerException('foundation-reset-config-invalid');
        }

        $tag = $resetConfig['tag'] ?? Tags::KERNEL_RESET;

        if (!\is_string($tag)) {
            throw new ContainerException('foundation-reset-tag-invalid');
        }

        if (\preg_match(self::TAG_PATTERN, $tag) !== 1) {
            throw new ContainerException('foundation-reset-tag-invalid');
        }

        return $tag;
    }
}
