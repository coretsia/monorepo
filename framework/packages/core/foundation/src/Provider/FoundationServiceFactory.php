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
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Id\UuidGenerator;
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

    private const string DEFAULT_ID_ULID = 'ulid';

    private const string DEFAULT_ID_UUID = 'uuid';

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
     * Resolves the default generic Foundation runtime id generator.
     *
     * The value is read from the supplied Foundation config subtree:
     *
     *     foundation.ids.default
     *
     * If the key is absent, the canonical default `ulid` is used.
     *
     * This selects only Coretsia\Foundation\Id\IdGeneratorInterface.
     *
     * It MUST NOT affect CorrelationIdGenerator or CorrelationIdProvider:
     * correlation_id remains ULID-backed according to epic 1.210.0.
     *
     * @param array<string, mixed> $foundationConfig
     */
    public static function defaultIdGenerator(
        array $foundationConfig,
        UlidGenerator $ulids,
        UuidGenerator $uuids,
    ): IdGeneratorInterface {
        $idsConfig = $foundationConfig['ids'] ?? [];

        if (!\is_array($idsConfig)) {
            throw new ContainerException('foundation-ids-config-invalid');
        }

        $default = $idsConfig['default'] ?? self::DEFAULT_ID_ULID;

        if (!\is_string($default)) {
            throw new ContainerException('foundation-ids-default-invalid');
        }

        return match ($default) {
            self::DEFAULT_ID_ULID => $ulids,
            self::DEFAULT_ID_UUID => $uuids,
            default => throw new ContainerException('foundation-ids-default-invalid'),
        };
    }

    /**
     * Resolves the effective Foundation reset discovery tag.
     *
     * The value is read from the supplied Foundation config subtree:
     *
     *     foundation.reset.tag
     *
     * If the key is absent, the reserved default `kernel.reset` is used.
     *
     * This method is intentionally public so provider wiring and reset
     * orchestrator construction use exactly the same validation and fallback
     * semantics.
     *
     * @param array<string, mixed> $foundationConfig
     */
    public static function effectiveResetTag(array $foundationConfig): string
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
