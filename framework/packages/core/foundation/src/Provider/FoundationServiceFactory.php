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

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Id\UuidGenerator;
use Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator;
use Coretsia\Foundation\Runtime\Reset\ResetException;
use Coretsia\Foundation\Runtime\Reset\ResetGroup;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

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

    private const bool DEFAULT_RESET_PRIORITY_ENABLED = true;

    private const string DEFAULT_RESET_GROUP = 'default';

    private function __construct()
    {
    }

    /**
     * Creates the stable Foundation reset orchestrator public entrypoint.
     *
     * The effective reset discovery tag is read from the supplied Foundation
     * config subtree:
     *
     *     foundation.reset.tag
     *
     * If the key is absent, the reserved default `kernel.reset` is used.
     *
     * Enhanced priority/group planning is controlled only by:
     *
     *     foundation.reset.priority.enabled
     *
     * This flag does not disable reset discovery or reset orchestration.
     *
     * @param array<string, mixed> $foundationConfig
     */
    public static function resetOrchestrator(
        ContainerInterface $container,
        TagRegistry $tagRegistry,
        array $foundationConfig,
        Stopwatch $stopwatch,
        ?TracerPortInterface $tracer = null,
        ?MeterPortInterface $meter = null,
        ?LoggerInterface $logger = null,
    ): ResetOrchestrator {
        $priorityEnabled = self::resetPriorityEnabled($foundationConfig);

        return new ResetOrchestrator(
            container: $container,
            tagRegistry: $tagRegistry,
            effectiveResetTag: self::effectiveResetTag($foundationConfig),
            priorityEnabled: $priorityEnabled,
            priorityResetOrchestrator: $priorityEnabled
                ? self::priorityResetOrchestrator(
                    container: $container,
                    tagRegistry: $tagRegistry,
                    foundationConfig: $foundationConfig,
                    stopwatch: $stopwatch,
                    tracer: $tracer,
                    meter: $meter,
                    logger: $logger,
                )
                : null,
        );
    }

    /**
     * Creates the enhanced deterministic reset planner/executor.
     *
     * This helper does not inspect `foundation.reset.priority.enabled`.
     * Mode selection is owned by `resetOrchestrator()`.
     *
     * @param array<string, mixed> $foundationConfig
     */
    public static function priorityResetOrchestrator(
        ContainerInterface $container,
        TagRegistry $tagRegistry,
        array $foundationConfig,
        Stopwatch $stopwatch,
        ?TracerPortInterface $tracer = null,
        ?MeterPortInterface $meter = null,
        ?LoggerInterface $logger = null,
    ): PriorityResetOrchestrator {
        return new PriorityResetOrchestrator(
            container: $container,
            tagRegistry: $tagRegistry,
            defaultGroup: self::defaultResetGroup($foundationConfig),
            stopwatch: $stopwatch,
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
        );
    }

    /**
     * Resolves whether enhanced reset priority/group planning is enabled.
     *
     * The value is read from the supplied Foundation config subtree:
     *
     *     foundation.reset.priority.enabled
     *
     * If the key is absent, the 1.250.0 default `true` is used.
     *
     * This controls only enhanced reset ordering/meta planning. It MUST NOT be
     * interpreted as a feature-disable switch for reset orchestration itself.
     *
     * @param array<string, mixed> $foundationConfig
     */
    public static function resetPriorityEnabled(array $foundationConfig): bool
    {
        $resetConfig = self::resetConfig($foundationConfig);

        $priorityConfig = $resetConfig['priority'] ?? [];

        if (!\is_array($priorityConfig)) {
            throw new ContainerException('foundation-reset-priority-config-invalid');
        }

        $enabled = $priorityConfig['enabled'] ?? self::DEFAULT_RESET_PRIORITY_ENABLED;

        if (!\is_bool($enabled)) {
            throw new ContainerException('foundation-reset-priority-enabled-invalid');
        }

        return $enabled;
    }

    /**
     * Resolves the default enhanced reset group id.
     *
     * The value is read from the supplied Foundation config subtree:
     *
     *     foundation.reset.group.default
     *
     * If the key is absent, the 1.250.0 default `default` is used.
     *
     * The returned string is already validated against the same normalized group
     * id rules used by runtime reset tag meta.
     *
     * @param array<string, mixed> $foundationConfig
     */
    public static function defaultResetGroup(array $foundationConfig): string
    {
        $resetConfig = self::resetConfig($foundationConfig);

        $groupConfig = $resetConfig['group'] ?? [];

        if (!\is_array($groupConfig)) {
            throw new ContainerException('foundation-reset-group-config-invalid');
        }

        $defaultGroup = $groupConfig['default'] ?? self::DEFAULT_RESET_GROUP;

        if (!\is_string($defaultGroup)) {
            throw new ContainerException('foundation-reset-group-default-invalid');
        }

        try {
            return ResetGroup::fromString($defaultGroup)->value();
        } catch (ResetException) {
            throw new ContainerException('foundation-reset-group-default-invalid');
        }
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
        $resetConfig = self::resetConfig($foundationConfig);

        $tag = $resetConfig['tag'] ?? ReservedTags::KERNEL_RESET;

        if (!\is_string($tag)) {
            throw new ContainerException('foundation-reset-tag-invalid');
        }

        if (\preg_match(self::TAG_PATTERN, $tag) !== 1) {
            throw new ContainerException('foundation-reset-tag-invalid');
        }

        return $tag;
    }

    /**
     * @param array<string, mixed> $foundationConfig
     *
     * @return array<string, mixed>
     */
    private static function resetConfig(array $foundationConfig): array
    {
        $resetConfig = $foundationConfig['reset'] ?? [];

        if (!\is_array($resetConfig)) {
            throw new ContainerException('foundation-reset-config-invalid');
        }

        return $resetConfig;
    }
}
