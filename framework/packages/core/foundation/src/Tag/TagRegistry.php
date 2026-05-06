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

namespace Coretsia\Foundation\Tag;

use Coretsia\Foundation\Discovery\DeterministicOrder;

/**
 * Canonical Foundation registry for tagged service discovery.
 *
 * Dedupe policy:
 *
 * - per `(tag, serviceId)`, the first registration wins;
 * - later duplicate registrations are ignored deterministically.
 *
 * Discovery order:
 *
 * - priority DESC;
 * - id ASC using `strcmp`;
 * - consumers must treat `all($tag)` output as canonical and must not re-sort.
 */
final class TagRegistry
{
    private const string TAG_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/';

    /**
     * @var array<string, array<string, TaggedService>>
     */
    private array $servicesByTag = [];

    /**
     * @param array<string, mixed> $meta
     */
    public function add(string $tag, string $serviceId, int $priority = 0, array $meta = []): void
    {
        self::assertValidTag($tag);

        if ($serviceId === '') {
            throw new \InvalidArgumentException('tag-registry-service-id-empty');
        }

        if (isset($this->servicesByTag[$tag][$serviceId])) {
            return;
        }

        $this->servicesByTag[$tag][$serviceId] = new TaggedService(
            id: $serviceId,
            priority: $priority,
            meta: $meta,
        );
    }

    /**
     * Returns registered tag names in deterministic byte-order.
     *
     * This is intended for diagnostics and introspection only. Consumers must
     * still use `all($tag)` as the canonical discovery-list authority.
     *
     * @return list<string>
     */
    public function tagNames(): array
    {
        $names = \array_keys($this->servicesByTag);

        \usort(
            $names,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $names;
    }

    /**
     * Returns the canonical discovery list for the given tag.
     *
     * @return list<TaggedService>
     */
    public function all(string $tag): array
    {
        self::assertValidTag($tag);

        $services = \array_values($this->servicesByTag[$tag] ?? []);

        return DeterministicOrder::sort(
            $services,
            static fn (TaggedService $service): string => $service->id,
            static fn (TaggedService $service): int => $service->priority,
        );
    }

    private static function assertValidTag(string $tag): void
    {
        if (\preg_match(self::TAG_PATTERN, $tag) !== 1) {
            throw new \InvalidArgumentException('tag-registry-tag-invalid');
        }
    }
}
