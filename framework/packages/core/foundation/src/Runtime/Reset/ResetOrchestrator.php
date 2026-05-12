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

namespace Coretsia\Foundation\Runtime\Reset;

use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Provider\Tags;
use Coretsia\Foundation\Tag\TagRegistry;
use Psr\Container\ContainerInterface;

/**
 * Foundation reset orchestrator.
 *
 * This is the stable public reset entrypoint used by Kernel runtime.
 *
 * Kernel runtime MUST call this orchestrator and MUST NOT enumerate reset tags
 * directly.
 *
 * Reset orchestration is baseline runtime safety infrastructure and cannot be
 * disabled through config. The priority flag controls only enhanced
 * priority/group planning behavior.
 *
 * Legacy/base mode semantics:
 *
 * - uses the effective reset discovery tag supplied by Foundation wiring/config;
 * - default effective reset discovery tag is `kernel.reset`;
 * - obtains the reset discovery list only through `TagRegistry::all($tag)`;
 * - executes services in the exact order returned by `TagRegistry::all($tag)`;
 * - does not parse tag meta;
 * - does not validate tag meta;
 * - does not apply additional sorting;
 * - does not apply additional dedupe rules;
 * - calls `ResetInterface::reset()` once per tagged service entry;
 * - hard-fails deterministically when a tagged service is not resettable.
 *
 * Enhanced mode semantics:
 *
 * - delegates planning and execution to `PriorityResetOrchestrator`;
 * - ordering is priority DESC, group ASC by strcmp(), serviceId ASC by strcmp();
 * - tag meta parsing is owned by `PriorityResetOrchestrator`.
 *
 * This class must not emit stdout/stderr and must not dump service instances,
 * constructor arguments, raw config payloads, environment values, tokens,
 * headers, cookies, Authorization values, session ids, payloads, or absolute
 * local paths.
 */
final readonly class ResetOrchestrator
{
    private const string TAG_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/';

    public function __construct(
        private ContainerInterface $container,
        private TagRegistry $tagRegistry,
        private string $effectiveResetTag = Tags::KERNEL_RESET,
        private bool $priorityEnabled = false,
        private ?PriorityResetOrchestrator $priorityResetOrchestrator = null,
    ) {
        self::assertValidResetTag($effectiveResetTag);

        if ($priorityEnabled && $priorityResetOrchestrator === null) {
            throw new \InvalidArgumentException('reset-orchestrator-priority-orchestrator-missing');
        }
    }

    /**
     * Executes reset for all services registered under the effective reset tag.
     *
     * Empty discovery list is a deterministic noop.
     */
    public function resetAll(): void
    {
        if ($this->priorityEnabled) {
            /** @var PriorityResetOrchestrator $priorityResetOrchestrator */
            $priorityResetOrchestrator = $this->priorityResetOrchestrator;
            $priorityResetOrchestrator->resetAll($this->effectiveResetTag);

            return;
        }

        $this->resetAllLegacy();
    }

    /**
     * Returns the effective reset discovery tag resolved by Foundation wiring.
     */
    public function effectiveResetTag(): string
    {
        return $this->effectiveResetTag;
    }

    /**
     * Returns whether enhanced priority/group planning is enabled.
     */
    public function priorityEnabled(): bool
    {
        return $this->priorityEnabled;
    }

    /**
     * Legacy/base execution mode.
     *
     * This method intentionally does not read or validate TaggedService meta.
     */
    private function resetAllLegacy(): void
    {
        foreach ($this->tagRegistry->all($this->effectiveResetTag) as $taggedService) {
            try {
                $service = $this->container->get($taggedService->id());
            } catch (\Throwable $exception) {
                throw ResetException::serviceFailed($exception);
            }

            if (!$service instanceof ResetInterface) {
                throw ResetException::serviceNotResettable();
            }

            try {
                $service->reset();
            } catch (\Throwable $exception) {
                throw ResetException::serviceFailed($exception);
            }
        }
    }

    private static function assertValidResetTag(string $tag): void
    {
        if (\preg_match(self::TAG_PATTERN, $tag) !== 1) {
            throw new \InvalidArgumentException('reset-orchestrator-reset-tag-invalid');
        }
    }
}
