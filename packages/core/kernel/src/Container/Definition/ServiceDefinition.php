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
 * Kernel-owned deterministic compiled service definition.
 *
 * This model represents one service definition in the `container@1`
 * compiled-container graph.
 *
 * It is a Kernel container compilation model, not a public DTO marker class,
 * and it does not imply a public package API commitment.
 *
 * The definition exposes only deterministic schema data suitable for compiled
 * container artifact payload emission.
 *
 * Supported runtime construction forms:
 *
 * - deterministic class reference;
 * - deterministic factory class + method reference;
 * - deterministic factory service id + method reference;
 * - deterministic service references;
 * - deterministic parameter references;
 * - scalar/list/map arguments.
 *
 * This model intentionally does not:
 *
 * - instantiate the represented service;
 * - store closures or anonymous functions;
 * - store callable objects;
 * - store raw PHP callable arrays;
 * - store object instances;
 * - store resources;
 * - store reflection objects;
 * - store source snippets;
 * - store absolute paths;
 * - store raw config/env values or secrets.
 *
 * Map keys are normalized recursively by byte-order string comparison
 * (`strcmp`). Lists preserve caller-supplied order because list order is
 * semantic for constructor/factory arguments.
 *
 * @internal
 */
final readonly class ServiceDefinition
{
    private const string TYPE_CLASS = 'class';
    private const string TYPE_FACTORY = 'factory';

    private const string FACTORY_CLASS_METHOD = 'class-method';
    private const string FACTORY_SERVICE_METHOD = 'service-method';

    private const string REF_SERVICE = 'service';
    private const string REF_PARAMETER = 'parameter';
    private const string REF_CLASS = 'class';

    private const int MAX_ID_BYTES = 256;
    private const int MAX_STRING_BYTES = 512;
    private const int MAX_DEPTH = 16;
    private const int MAX_MAP_KEYS = 256;
    private const int MAX_LIST_ITEMS = 512;

    private const string SERVICE_ID_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.:\\\\-]{0,255}\z/';
    private const string CLASS_REFERENCE_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/';
    private const string METHOD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/';
    private const string PARAMETER_NAME_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]{0,255}\z/';
    private const string MAP_KEY_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]{0,127}\z/';

    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SOURCE_SNIPPET_PATTERN = '/<\?php|<\?=|\bfunction\s*\(|\bfn\s*\(|=>\s*\{|;\s*}/i';
    private const string ENV_LIKE_PATTERN = '/\$\{[A-Z_][A-Z0-9_]*}|%env\(|\benv\s*\(/i';
    private const string SENSITIVE_VALUE_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key)(?![A-Za-z0-9])/i';

    /**
     * @param array<string, mixed> $construction
     * @param list<mixed> $arguments
     */
    private function __construct(
        private string $id,
        private string $type,
        private array $construction,
        private array $arguments,
        private bool $shared,
    ) {
    }

    /**
     * Creates a service definition constructed by deterministic class reference.
     *
     * @param list<mixed> $arguments
     *
     * @throws ContainerCompileFailedException
     */
    public static function class(
        string $id,
        string $class,
        array $arguments = [],
        bool $shared = true,
    ): self {
        self::assertServiceId($id);
        self::assertClassReference($class);

        return new self(
            id: $id,
            type: self::TYPE_CLASS,
            construction: [
                'class' => $class,
            ],
            arguments: self::normalizeArguments($arguments),
            shared: $shared,
        );
    }

    /**
     * Creates a service definition constructed by deterministic factory class
     * + method reference.
     *
     * The factory is represented as schema data, not as a PHP callable.
     *
     * @param list<mixed> $arguments
     *
     * @throws ContainerCompileFailedException
     */
    public static function factoryClassMethod(
        string $id,
        string $factoryClass,
        string $method,
        array $arguments = [],
        bool $shared = true,
    ): self {
        self::assertServiceId($id);
        self::assertClassReference($factoryClass);
        self::assertMethodName($method);

        return new self(
            id: $id,
            type: self::TYPE_FACTORY,
            construction: [
                'factory' => [
                    'class' => $factoryClass,
                    'kind' => self::FACTORY_CLASS_METHOD,
                    'method' => $method,
                ],
            ],
            arguments: self::normalizeArguments($arguments),
            shared: $shared,
        );
    }

    /**
     * Creates a service definition constructed by deterministic factory service
     * id + method reference.
     *
     * The factory is represented as schema data, not as a PHP callable array.
     *
     * @param list<mixed> $arguments
     *
     * @throws ContainerCompileFailedException
     */
    public static function factoryServiceMethod(
        string $id,
        string $factoryServiceId,
        string $method,
        array $arguments = [],
        bool $shared = true,
    ): self {
        self::assertServiceId($id);
        self::assertServiceId($factoryServiceId);
        self::assertMethodName($method);

        return new self(
            id: $id,
            type: self::TYPE_FACTORY,
            construction: [
                'factory' => [
                    'kind' => self::FACTORY_SERVICE_METHOD,
                    'method' => $method,
                    'service' => $factoryServiceId,
                ],
            ],
            arguments: self::normalizeArguments($arguments),
            shared: $shared,
        );
    }

    /**
     * Deterministic service reference argument.
     *
     * @return array{id: string, type: string}
     *
     * @throws ContainerCompileFailedException
     */
    public static function serviceReference(string $id): array
    {
        self::assertServiceId($id);

        return [
            'id' => $id,
            'type' => self::REF_SERVICE,
        ];
    }

    /**
     * Deterministic parameter reference argument.
     *
     * @return array{name: string, type: string}
     *
     * @throws ContainerCompileFailedException
     */
    public static function parameterReference(string $name): array
    {
        self::assertParameterName($name);

        return [
            'name' => $name,
            'type' => self::REF_PARAMETER,
        ];
    }

    /**
     * Deterministic class reference argument.
     *
     * @return array{class: string, type: string}
     *
     * @throws ContainerCompileFailedException
     */
    public static function classReference(string $class): array
    {
        self::assertClassReference($class);

        return [
            'class' => $class,
            'type' => self::REF_CLASS,
        ];
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function shared(): bool
    {
        return $this->shared;
    }

    /**
     * Exports deterministic schema data for `container@1` compiled payload emission.
     *
     * @return array{
     *     arguments: list<mixed>,
     *     construction: array<string, mixed>,
     *     id: string,
     *     shared: bool,
     *     type: string
     * }
     */
    public function toArray(): array
    {
        return [
            'arguments' => $this->arguments,
            'construction' => $this->construction,
            'id' => $this->id,
            'shared' => $this->shared,
            'type' => $this->type,
        ];
    }

    /**
     * @return list<mixed>
     *
     * @throws ContainerCompileFailedException
     */
    private static function normalizeArguments(array $arguments): array
    {
        if (!\array_is_list($arguments)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
            );
        }

        if (\count($arguments) > self::MAX_LIST_ITEMS) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
            );
        }

        $normalized = [];

        foreach ($arguments as $argument) {
            $normalized[] = self::normalizeValue($argument, 0);
        }

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
                ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
            );
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            self::assertSafeSchemaString($value);

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
                ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
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
                ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
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
                ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
            );
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key) || !self::isSafeMapKey($key)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
                );
            }

            $normalized[$key] = self::normalizeValue($item, $depth);
        }

        self::validateKnownReferenceMap($normalized);

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @throws ContainerCompileFailedException
     */
    private static function validateKnownReferenceMap(array $value): void
    {
        $type = $value['type'] ?? null;

        if (!\is_string($type)) {
            return;
        }

        $keys = \array_keys($value);
        \sort($keys, \SORT_STRING);

        if ($type === self::REF_SERVICE) {
            if ($keys !== ['id', 'type'] || !\is_string($value['id'] ?? null)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
                );
            }

            self::assertServiceId($value['id']);

            return;
        }

        if ($type === self::REF_PARAMETER) {
            if ($keys !== ['name', 'type'] || !\is_string($value['name'] ?? null)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
                );
            }

            self::assertParameterName($value['name']);

            return;
        }

        if ($type === self::REF_CLASS) {
            if ($keys !== ['class', 'type'] || !\is_string($value['class'] ?? null)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
                );
            }

            self::assertClassReference($value['class']);
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

        if (self::isSafeServiceId($target) || self::isSafeClassReference($target)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_CALLABLE_DEFINITION,
            );
        }
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertServiceId(string $id): void
    {
        if (!self::isSafeServiceId($id)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_DEFINITION_INVALID,
            );
        }
    }

    private static function isSafeServiceId(string $id): bool
    {
        return $id !== ''
            && \strlen($id) <= self::MAX_ID_BYTES
            && \trim($id) === $id
            && \preg_match(self::SERVICE_ID_PATTERN, $id) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $id) !== 1
            && !\str_contains($id, '://')
            && !\str_contains($id, '::')
            && !self::looksLikeAbsolutePath($id)
            && !self::looksLikeSourceSnippet($id);
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertClassReference(string $class): void
    {
        if (!self::isSafeClassReference($class)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_DEFINITION_INVALID,
            );
        }
    }

    private static function isSafeClassReference(string $class): bool
    {
        return $class !== ''
            && \strlen($class) <= self::MAX_ID_BYTES
            && \trim($class) === $class
            && \preg_match(self::CLASS_REFERENCE_PATTERN, $class) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $class) !== 1
            && !\str_starts_with($class, '\\')
            && !\str_contains($class, '::')
            && !self::looksLikeAbsolutePath($class)
            && !self::looksLikeSourceSnippet($class);
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertMethodName(string $method): void
    {
        if (!self::isSafeMethodName($method)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_FACTORY_INVALID,
            );
        }
    }

    private static function isSafeMethodName(string $method): bool
    {
        return $method !== ''
            && \preg_match(self::METHOD_PATTERN, $method) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $method) !== 1;
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertParameterName(string $name): void
    {
        if (
            $name === ''
            || \strlen($name) > self::MAX_ID_BYTES
            || \trim($name) !== $name
            || \preg_match(self::PARAMETER_NAME_PATTERN, $name) !== 1
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $name) === 1
            || \str_contains($name, '://')
            || \str_contains($name, '::')
            || self::looksLikeAbsolutePath($name)
            || self::looksLikeSourceSnippet($name)
            || self::looksSensitive($name)
        ) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertSafeSchemaString(string $value): void
    {
        if (
            $value === ''
            || \strlen($value) > self::MAX_STRING_BYTES
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $value) === 1
            || \str_contains($value, '://')
            || \str_contains($value, '::')
            || self::looksLikeAbsolutePath($value)
            || self::looksLikeSourceSnippet($value)
            || self::looksLikeEnvValue($value)
            || self::looksSensitive($value)
        ) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
            );
        }
    }

    private static function isSafeMapKey(string $key): bool
    {
        return $key !== ''
            && \strlen($key) <= 128
            && \preg_match(self::MAP_KEY_PATTERN, $key) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $key) !== 1
            && !self::looksSensitive($key);
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
}
