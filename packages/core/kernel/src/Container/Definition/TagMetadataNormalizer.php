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

namespace Coretsia\Kernel\Container\Definition;

/**
 * Canonical Kernel policy for deterministic compiled tag metadata.
 *
 * The Foundation definition boundary already normalizes declarative tag meta.
 * Kernel repeats the payload-safety law at its compiled-container boundary so
 * DefinitionGraph and artifact-runtime hydration share one exact schema.
 *
 * @internal
 */
final class TagMetadataNormalizer
{
    private const int MAX_STRING_BYTES = 1024;
    private const int MAX_DEPTH = 16;
    private const int MAX_NODES = 4096;
    private const int MAX_MAP_KEYS = 256;
    private const int MAX_LIST_ITEMS = 512;

    private const string MAP_KEY_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]{0,127}\z/';
    private const string METHOD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/';
    private const string CLASS_LIKE_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SOURCE_SNIPPET_PATTERN = '/<\?php|<\?=|\bfunction\s*\(|\bfn\s*\(|=>\s*\{|;\s*}/i';
    private const string ENV_LIKE_PATTERN = '/\$\{[A-Z_][A-Z0-9_]*}|%env\(|\benv\s*\(/i';
    private const string SENSITIVE_VALUE_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key)(?![A-Za-z0-9])/i';

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $meta): array
    {
        if ($meta !== [] && \array_is_list($meta)) {
            throw self::invalid();
        }

        $nodeCount = 0;
        $normalized = self::normalizeValue(
            value: $meta,
            depth: 0,
            nodeCount: $nodeCount,
        );

        if (
            !\is_array($normalized)
            || ($normalized !== [] && \array_is_list($normalized))
        ) {
            throw self::invalid();
        }

        /** @var array<string, mixed> $normalized */
        return $normalized;
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    private static function normalizeValue(
        mixed $value,
        int $depth,
        int &$nodeCount,
    ): null|bool|int|string|array {
        if ($depth > self::MAX_DEPTH) {
            throw self::invalid();
        }

        ++$nodeCount;

        if ($nodeCount > self::MAX_NODES) {
            throw self::invalid();
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            self::assertSafeString($value);

            return $value;
        }

        if (
            \is_float($value)
            || \is_resource($value)
            || $value instanceof \Closure
            || \is_object($value)
            || !\is_array($value)
        ) {
            throw self::invalid();
        }

        if (self::isReservedReferenceShape($value)) {
            throw self::invalid();
        }

        if (\array_is_list($value)) {
            if (\count($value) > self::MAX_LIST_ITEMS) {
                throw self::invalid();
            }

            self::rejectRawCallableArray($value);

            $normalized = [];

            foreach ($value as $item) {
                $normalized[] = self::normalizeValue(
                    value: $item,
                    depth: $depth + 1,
                    nodeCount: $nodeCount,
                );
            }

            return $normalized;
        }

        if (\count($value) > self::MAX_MAP_KEYS) {
            throw self::invalid();
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key) || !self::isSafeMapKey($key)) {
                throw self::invalid();
            }

            $normalized[$key] = self::normalizeValue(
                value: $item,
                depth: $depth + 1,
                nodeCount: $nodeCount,
            );
        }

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function isReservedReferenceShape(array $value): bool
    {
        if (\array_is_list($value)) {
            return false;
        }

        $keys = \array_keys($value);

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                return false;
            }
        }

        \sort($keys, \SORT_STRING);

        return $keys === ['class', 'type']
            || $keys === ['id', 'type']
            || $keys === ['name', 'type'];
    }

    /**
     * @param list<mixed> $value
     */
    private static function rejectRawCallableArray(array $value): void
    {
        if (\count($value) !== 2) {
            return;
        }

        [$target, $method] = $value;

        if (
            \is_string($target)
            && \is_string($method)
            && self::isSafeMethodName($method)
            && self::isClassLikeString($target)
        ) {
            throw self::invalid();
        }
    }

    private static function isSafeMapKey(string $key): bool
    {
        return $key !== ''
            && \strlen($key) <= 128
            && \preg_match(self::MAP_KEY_PATTERN, $key) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $key) !== 1
            && !self::looksLikeAbsolutePath($key)
            && !self::looksLikeSourceSnippet($key)
            && !self::looksLikeEnvValue($key)
            && !self::looksSensitive($key);
    }

    private static function assertSafeString(string $value): void
    {
        if (
            \strlen($value) > self::MAX_STRING_BYTES
            || \preg_match('//u', $value) !== 1
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $value) === 1
            || \str_contains($value, '://')
            || \str_contains($value, '::')
            || self::looksLikeAbsolutePath($value)
            || self::looksLikeSourceSnippet($value)
            || self::looksLikeEnvValue($value)
            || self::looksSensitive($value)
        ) {
            throw self::invalid();
        }
    }

    private static function isSafeMethodName(string $method): bool
    {
        return $method !== ''
            && \preg_match(self::METHOD_PATTERN, $method) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $method) !== 1;
    }

    private static function isClassLikeString(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= self::MAX_STRING_BYTES
            && \trim($value) === $value
            && \preg_match(self::CLASS_LIKE_PATTERN, $value) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $value) !== 1
            && !\str_starts_with($value, '\\')
            && !\str_contains($value, '::')
            && !self::looksLikeAbsolutePath($value)
            && !self::looksLikeSourceSnippet($value);
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $value) === 1;
    }

    private static function looksLikeSourceSnippet(string $value): bool
    {
        return \preg_match(self::SOURCE_SNIPPET_PATTERN, $value) === 1;
    }

    private static function looksLikeEnvValue(string $value): bool
    {
        return \preg_match(self::ENV_LIKE_PATTERN, $value) === 1;
    }

    private static function looksSensitive(string $value): bool
    {
        return \preg_match(self::SENSITIVE_VALUE_PATTERN, $value) === 1;
    }

    private static function invalid(): \InvalidArgumentException
    {
        return new \InvalidArgumentException('compiled-tag-metadata-invalid');
    }
}
