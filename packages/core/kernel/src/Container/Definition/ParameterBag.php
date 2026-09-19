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

use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;

/**
 * Kernel-owned deterministic compiled-container parameter bag.
 *
 * This model represents deterministic parameter data for the `container@1`
 * compiled-container graph.
 *
 * It may be used as an in-memory compilation model and/or emitted as part of
 * the `container@1` payload schema defined by docs/ssot/compiled-container.md.
 *
 * It is a Kernel container compilation model, not a public DTO marker class,
 * and it does not imply a public package API commitment.
 *
 * This model intentionally stores only deterministic schema data:
 *
 * - null;
 * - bool;
 * - int;
 * - string;
 * - list<value>;
 * - array<string, value>.
 *
 * It intentionally does not:
 *
 * - duplicate the full compiled config@1 payload;
 * - embed raw secrets;
 * - store closures or anonymous functions;
 * - store callable objects;
 * - store raw callable arrays;
 * - store object instances;
 * - store resources;
 * - store reflection objects;
 * - store source snippets;
 * - store absolute paths;
 * - store raw env values.
 *
 * Map keys are normalized recursively by byte-order string comparison
 * (`strcmp`). Lists preserve caller-supplied order because list order may be
 * semantic for parameter values.
 *
 * @internal
 */
final readonly class ParameterBag
{
    private const int MAX_PARAMETERS = 1024;
    private const int MAX_PARAMETER_NAME_BYTES = 256;
    private const int MAX_STRING_BYTES = 512;
    private const int MAX_DEPTH = 16;
    private const int MAX_MAP_KEYS = 256;
    private const int MAX_LIST_ITEMS = 512;

    private const string PARAMETER_NAME_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]{0,255}\z/';
    private const string MAP_KEY_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]{0,127}\z/';
    private const string METHOD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/';
    private const string CLASS_LIKE_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/';

    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SOURCE_SNIPPET_PATTERN = '/<\?php|<\?=|\bfunction\s*\(|\bfn\s*\(|=>\s*\{|;\s*}/i';
    private const string ENV_LIKE_PATTERN = '/\$\{[A-Z_][A-Z0-9_]*}|%env\(|\benv\s*\(/i';
    private const string SENSITIVE_VALUE_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key)(?![A-Za-z0-9])/i';

    /**
     * @var array<string, true>
     */
    private const array RESERVED_PARAMETER_NAMES = [
        'config' => true,
        'compiledConfig' => true,
        'compiled_config' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array COMPILED_CONFIG_PAYLOAD_KEYS = [
        'config' => true,
        'configSourceFiles' => true,
        'envOverlayMappings' => true,
        'owners' => true,
        'sources' => true,
        'validation' => true,
        'validationSubjects' => true,
    ];

    /**
     * @param array<string, mixed> $parameters
     */
    private function __construct(
        private array $parameters,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Creates a deterministic parameter bag from a parameter-name map.
     *
     * @param array<string, mixed> $parameters
     *
     * @throws ContainerCompileFailedException
     */
    public static function fromArray(array $parameters): self
    {
        return new self(self::normalizeParameters($parameters));
    }

    /**
     * Returns a new bag with one deterministic parameter added or replaced.
     *
     * @throws ContainerCompileFailedException
     */
    public function with(string $name, mixed $value): self
    {
        self::assertParameterName($name);

        $parameters = $this->parameters;
        $parameters[$name] = self::normalizeValue($value, 0);

        \uksort(
            $parameters,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return new self($parameters);
    }

    public function has(string $name): bool
    {
        if (!self::isValidParameterName($name)) {
            return false;
        }

        return \array_key_exists($name, $this->parameters);
    }

    /**
     * Returns a deterministic parameter value.
     *
     * @throws ContainerCompileFailedException when the parameter name is invalid
     *                                         or unknown.
     */
    public function get(string $name): mixed
    {
        self::assertParameterName($name);

        if (!\array_key_exists($name, $this->parameters)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }

        return $this->parameters[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return \array_keys($this->parameters);
    }

    /**
     * Exports deterministic schema data for `container@1` compiled payload emission.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     *
     * @throws ContainerCompileFailedException
     */
    private static function normalizeParameters(array $parameters): array
    {
        if (!self::isMapArray($parameters)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }

        if (\count($parameters) > self::MAX_PARAMETERS) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }

        self::assertNotCompiledConfigPayload($parameters);

        $normalized = [];

        foreach ($parameters as $name => $value) {
            if (!\is_string($name)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_PARAMETER_INVALID,
                );
            }

            self::assertParameterName($name);

            $normalized[$name] = self::normalizeValue($value, 0);
        }

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     *
     * @throws ContainerCompileFailedException
     */
    private static function normalizeValue(mixed $value, int $depth): null|bool|int|string|array
    {
        if ($depth > self::MAX_DEPTH) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            self::assertSafeParameterString($value);

            return $value;
        }

        if (\is_float($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if (\is_resource($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if ($value instanceof \Closure) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_CLOSURE_DEFINITION,
            );
        }

        if (\is_object($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if (!\is_array($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }

        if (\array_is_list($value)) {
            return self::normalizeList($value, $depth + 1);
        }

        return self::normalizeMap($value, $depth + 1);
    }

    /**
     * @param list<mixed> $value
     *
     * @return list<mixed>
     *
     * @throws ContainerCompileFailedException
     */
    private static function normalizeList(array $value, int $depth): array
    {
        if (\count($value) > self::MAX_LIST_ITEMS) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }

        self::rejectRawCallableArray($value);

        $normalized = [];

        foreach ($value as $item) {
            $normalized[] = self::normalizeValue($item, $depth);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     *
     * @throws ContainerCompileFailedException
     */
    private static function normalizeMap(array $value, int $depth): array
    {
        if (\count($value) > self::MAX_MAP_KEYS) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }

        self::assertNotCompiledConfigPayload($value);

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key) || !self::isSafeMapKey($key)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_PARAMETER_INVALID,
                );
            }

            $normalized[$key] = self::normalizeValue($item, $depth);
        }

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @throws ContainerCompileFailedException
     */
    private static function assertNotCompiledConfigPayload(array $value): void
    {
        $present = 0;

        foreach (self::COMPILED_CONFIG_PAYLOAD_KEYS as $key => $_) {
            if (\array_key_exists($key, $value)) {
                ++$present;
            }
        }

        if ($present === \count(self::COMPILED_CONFIG_PAYLOAD_KEYS)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }
    }

    /**
     * @param list<mixed> $value
     *
     * @throws ContainerCompileFailedException
     */
    private static function rejectRawCallableArray(array $value): void
    {
        if (\count($value) !== 2) {
            return;
        }

        [$target, $method] = $value;

        if (!\is_string($target) || !\is_string($method)) {
            return;
        }

        if (!self::isSafeMethodName($method)) {
            return;
        }

        if (self::isClassLikeString($target)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_CALLABLE_DEFINITION,
            );
        }
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertParameterName(string $name): void
    {
        if (!self::isValidParameterName($name)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertSafeParameterString(string $value): void
    {
        if (
            \strlen($value) > self::MAX_STRING_BYTES
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $value) === 1
            || \str_contains($value, '://')
            || \str_contains($value, '::')
            || self::looksLikeAbsolutePath($value)
            || self::looksLikeSourceSnippet($value)
            || self::looksLikeEnvValue($value)
            || self::looksSensitive($value)
        ) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
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

    private static function isSafeMethodName(string $method): bool
    {
        return $method !== ''
            && \preg_match(self::METHOD_PATTERN, $method) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $method) !== 1;
    }

    private static function isClassLikeString(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= self::MAX_PARAMETER_NAME_BYTES
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

    private static function isValidParameterName(string $name): bool
    {
        return !(
            $name === ''
            || \strlen($name) > self::MAX_PARAMETER_NAME_BYTES
            || \trim($name) !== $name
            || isset(self::RESERVED_PARAMETER_NAMES[$name])
            || \preg_match(self::PARAMETER_NAME_PATTERN, $name) !== 1
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $name) === 1
            || \str_contains($name, '://')
            || \str_contains($name, '::')
            || self::looksLikeAbsolutePath($name)
            || self::looksLikeSourceSnippet($name)
            || self::looksLikeEnvValue($name)
            || self::looksSensitive($name)
        );
    }

    private static function isMapArray(array $value): bool
    {
        return $value === [] || !\array_is_list($value);
    }
}
