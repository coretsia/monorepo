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
 * - includes only safe service ids, tag names, and tag priorities.
 *
 * JSON output is byte-stable through `StableJsonEncoder` and always ends with
 * a final LF.
 */
final readonly class ContainerDiagnostics
{
    private const string SCHEMA_VERSION = 'coretsia.foundation.containerDiagnostics.v1';
    private const int MAX_READABLE_ID_BYTES = 128;
    private const string CLASS_LIKE_ID_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/';
    private const string CONSERVATIVE_ALIAS_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.:-]{0,127}\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SENSITIVE_ID_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|auth|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|sql)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';
    private const string URL_LIKE_PATTERN = '~[A-Za-z][A-Za-z0-9+.-]*://|www\.~i';

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
        if (self::isSuspiciousServiceId($id)) {
            return self::hashedDiagnosticId($id);
        }

        if (self::isReadableServiceId($id)) {
            return $id;
        }

        return self::hashedDiagnosticId($id);
    }

    private static function hashedDiagnosticId(string $id): string
    {
        return 'hash:sha256:' . \hash('sha256', $id) . ';len:' . \strlen($id);
    }

    private static function isReadableServiceId(string $id): bool
    {
        return \preg_match(self::CLASS_LIKE_ID_PATTERN, $id) === 1
            || \preg_match(self::CONSERVATIVE_ALIAS_PATTERN, $id) === 1;
    }

    private static function isSuspiciousServiceId(string $id): bool
    {
        if ($id === '') {
            return true;
        }

        if (\strlen($id) > self::MAX_READABLE_ID_BYTES) {
            return true;
        }

        if (self::looksLikeAbsolutePath($id)) {
            return true;
        }

        if (self::looksLikePath($id)) {
            return true;
        }

        if (\preg_match(self::CONTROL_CHARACTER_PATTERN, $id) === 1) {
            return true;
        }

        if (\preg_match(self::URL_LIKE_PATTERN, $id) === 1) {
            return true;
        }

        if (\preg_match(self::SENSITIVE_ID_PATTERN, $id) === 1) {
            return true;
        }

        if (\preg_match(self::SQL_LIKE_PATTERN, $id) === 1) {
            return true;
        }

        return false;
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \preg_match(
            '~\A/home/|\A/Users/|\A[A-Za-z]:[\\\\/]|\A\\\\\\\\[^\\\\/]+[\\\\/][^\\\\/]+~',
            $value,
        ) === 1;
    }

    private static function looksLikePath(string $value): bool
    {
        return \preg_match(
            '~/|\A[A-Za-z]:[\\\\/]|\A\\\\\\\\[^\\\\/]+[\\\\/][^\\\\/]+~',
            $value,
        ) === 1;
    }
}
