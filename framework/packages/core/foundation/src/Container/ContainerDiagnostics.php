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

namespace Coretsia\Foundation\Container;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Foundation\Tag\TagRegistry;

/**
 * Deterministic container diagnostics snapshot.
 *
 * This snapshot is safe by construction:
 *
 * - does not dump service instances;
 * - does not dump constructor arguments;
 * - does not dump reflection data;
 * - does not include tag meta values;
 * - includes only service ids, tag names, and tag priorities.
 *
 * JSON output is byte-stable through `StableJsonEncoder` and always ends with
 * a final LF.
 */
final readonly class ContainerDiagnostics
{
    private const string SCHEMA_VERSION = 'coretsia.foundation.containerDiagnostics.v1';

    /**
     * @param list<string> $serviceIds
     * @param array<string, list<array{id: string, priority: int}>> $tags
     */
    private function __construct(
        private array $serviceIds,
        private array $tags,
    ) {
    }

    public static function fromContainer(Container $container, TagRegistry $tagRegistry): self
    {
        return new self(
            serviceIds: self::normalizeServiceIds($container->serviceIds()),
            tags: self::normalizeTags($tagRegistry),
        );
    }

    public static function fromBuilder(ContainerBuilder $builder): self
    {
        return new self(
            serviceIds: self::normalizeServiceIds($builder->serviceIds()),
            tags: self::normalizeTags($builder->tagRegistry()),
        );
    }

    /**
     * Returns a normalized deterministic diagnostics structure.
     *
     * @return array{
     *     schemaVersion: string,
     *     services: list<string>,
     *     tags: array<string, list<array{id: string, priority: int}>>
     * }
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'services' => $this->serviceIds,
            'tags' => $this->tags,
        ];
    }

    public function toJson(): string
    {
        return StableJsonEncoder::encodeStable($this->toArray());
    }

    /**
     * @param list<string> $serviceIds
     *
     * @return list<string>
     */
    private static function normalizeServiceIds(array $serviceIds): array
    {
        $normalized = [];

        foreach ($serviceIds as $id) {
            $normalized[] = self::diagnosticSafeId($id);
        }

        $normalized = \array_values(\array_unique($normalized));

        \usort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    /**
     * @return array<string, list<array{id: string, priority: int}>>
     */
    private static function normalizeTags(TagRegistry $tagRegistry): array
    {
        $tags = [];

        foreach ($tagRegistry->tagNames() as $tag) {
            $items = [];

            foreach ($tagRegistry->all($tag) as $service) {
                $items[] = self::taggedServiceToDiagnostics($service);
            }

            $tags[$tag] = $items;
        }

        \uksort(
            $tags,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $tags;
    }

    /**
     * @return array{id: string, priority: int}
     */
    private static function taggedServiceToDiagnostics(TaggedService $service): array
    {
        return [
            'id' => self::diagnosticSafeId($service->id()),
            'priority' => $service->priority(),
        ];
    }

    private static function diagnosticSafeId(string $id): string
    {
        if (!self::looksLikeAbsolutePath($id)) {
            return $id;
        }

        return 'hash:sha256:' . \hash('sha256', $id) . ';len:' . \strlen($id);
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \preg_match(
            '~\A/home/|\A/Users/|\A[A-Za-z]:[\\\\/]|\A\\\\\\\\[^\\\\/]+[\\\\/][^\\\\/]+~',
            $value,
        ) === 1;
    }
}
