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
 * This is the only runtime reset executor that `core/kernel` is allowed to use.
 *
 * Legacy/base mode semantics before epic `1.250.0`:
 *
 * - uses the effective reset discovery tag supplied by Foundation wiring/config;
 * - default effective reset discovery tag is `kernel.reset`;
 * - obtains the reset discovery list only through `TagRegistry::all($tag)`;
 * - executes services in the exact order returned by `TagRegistry::all($tag)`;
 * - does not parse tag meta;
 * - does not apply additional sorting;
 * - does not apply additional dedupe rules;
 * - calls `ResetInterface::reset()` once per tagged service entry;
 * - hard-fails deterministically when a tagged service is not resettable.
 *
 * This class must not emit stdout/stderr and must not dump service instances,
 * constructor arguments, raw config payloads, environment values, tokens, or
 * absolute local paths.
 */
final readonly class ResetOrchestrator
{
    private const string TAG_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/';

    public function __construct(
        private ContainerInterface $container,
        private TagRegistry $tagRegistry,
        private string $effectiveResetTag = Tags::KERNEL_RESET,
    ) {
        self::assertValidResetTag($effectiveResetTag);
    }

    /**
     * Executes reset for all services registered under the effective reset tag.
     *
     * Empty discovery list is a deterministic noop.
     */
    public function resetAll(): void
    {
        foreach ($this->tagRegistry->all($this->effectiveResetTag) as $taggedService) {
            $service = $this->container->get($taggedService->id());

            if (!$service instanceof ResetInterface) {
                throw new \RuntimeException('reset-not-resettable');
            }

            $service->reset();
        }
    }

    /**
     * Returns the effective reset discovery tag resolved by Foundation wiring.
     */
    public function effectiveResetTag(): string
    {
        return $this->effectiveResetTag;
    }

    private static function assertValidResetTag(string $tag): void
    {
        if (\preg_match(self::TAG_PATTERN, $tag) !== 1) {
            throw new \InvalidArgumentException('reset-orchestrator-reset-tag-invalid');
        }
    }
}
