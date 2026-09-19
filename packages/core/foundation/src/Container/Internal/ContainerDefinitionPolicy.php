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

namespace Coretsia\Foundation\Container\Internal;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionKind;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Foundation\Tag\Internal\TagNamePolicy;

/**
 * Shared validation and normalization policy for Foundation declarative
 * container definitions.
 *
 * @internal
 */
final class ContainerDefinitionPolicy
{
    private const int MAX_ID_BYTES = 256;
    private const int MAX_STRING_BYTES = 1024;
    private const int MAX_DEPTH = 16;
    private const int MAX_NODES = 4096;
    private const int MAX_MAP_KEYS = 256;
    private const int MAX_LIST_ITEMS = 512;

    private const string CLASS_REFERENCE_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/';
    private const string METHOD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/';
    private const string PARAMETER_NAME_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]{0,255}\z/';
    private const string MAP_KEY_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]{0,127}\z/';
    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SOURCE_SNIPPET_PATTERN = '/<\?php|<\?=|\bfunction\s*\(|\bfn\s*\(|=>\s*\{|;\s*}/i';
    private const string ENV_LIKE_PATTERN = '/\$\{[A-Z_][A-Z0-9_]*}|%env\(|\benv\s*\(/i';
    private const string SENSITIVE_VALUE_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key)(?![A-Za-z0-9])/i';

    /**
     * @var array<string, true>
     */
    private const array RESERVED_CLASS_REFERENCES = [
        'array' => true,
        'bool' => true,
        'callable' => true,
        'class' => true,
        'enum' => true,
        'false' => true,
        'float' => true,
        'int' => true,
        'interface' => true,
        'iterable' => true,
        'mixed' => true,
        'never' => true,
        'null' => true,
        'object' => true,
        'parent' => true,
        'resource' => true,
        'self' => true,
        'static' => true,
        'string' => true,
        'trait' => true,
        'true' => true,
        'void' => true,
    ];

    private function __construct()
    {
    }

    /**
     * Fully validates and normalizes one declarative container operation.
     *
     * This method is intentionally safe for externally supplied descriptor state.
     * It validates the exact operation shape and reconstructs the result in one
     * canonical key order.
     *
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    public static function normalizeOperation(array $operation): array
    {
        if ($operation === [] || \array_is_list($operation)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        $kind = $operation['kind'] ?? null;

        if (!\is_string($kind)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        return match (ContainerDefinitionKind::tryFrom($kind)) {
            ContainerDefinitionKind::SERVICE_CLASS => self::normalizeClassServiceOperation($operation),
            ContainerDefinitionKind::SERVICE_FACTORY_CLASS_METHOD => self::normalizeClassMethodFactoryOperation($operation),
            ContainerDefinitionKind::SERVICE_FACTORY_SERVICE_METHOD => self::normalizeServiceMethodFactoryOperation($operation),
            ContainerDefinitionKind::ALIAS => self::normalizeAliasOperation($operation),
            ContainerDefinitionKind::PARAMETER => self::normalizeParameterOperation($operation),
            ContainerDefinitionKind::TAG => self::normalizeTagOperation($operation),

            default => throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            ),
        };
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private static function normalizeClassServiceOperation(
        array $operation,
    ): array {
        self::assertExactKeys(
            $operation,
            [
                'arguments',
                'class',
                'id',
                'kind',
                'shared',
            ],
        );

        $id = $operation['id'];
        $class = $operation['class'];
        $arguments = $operation['arguments'];
        $shared = $operation['shared'];

        if (
            !\is_string($id)
            || !\is_string($class)
            || !\is_array($arguments)
            || !\is_bool($shared)
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        self::assertServiceId($id);
        self::assertClassReference($class);

        return [
            'arguments' => self::normalizeDescriptorArguments($arguments),
            'class' => $class,
            'id' => $id,
            'kind' => ContainerDefinitionKind::SERVICE_CLASS->value,
            'shared' => $shared,
        ];
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private static function normalizeClassMethodFactoryOperation(
        array $operation,
    ): array {
        self::assertExactKeys(
            $operation,
            [
                'arguments',
                'factoryClass',
                'id',
                'kind',
                'method',
                'shared',
            ],
        );

        $id = $operation['id'];
        $factoryClass = $operation['factoryClass'];
        $method = $operation['method'];
        $arguments = $operation['arguments'];
        $shared = $operation['shared'];

        if (
            !\is_string($id)
            || !\is_string($factoryClass)
            || !\is_string($method)
            || !\is_array($arguments)
            || !\is_bool($shared)
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        self::assertServiceId($id);
        self::assertPublicStaticFactoryMethod(
            factoryClass: $factoryClass,
            method: $method,
        );

        return [
            'arguments' => self::normalizeDescriptorArguments($arguments),
            'factoryClass' => $factoryClass,
            'id' => $id,
            'kind' => ContainerDefinitionKind::SERVICE_FACTORY_CLASS_METHOD->value,
            'method' => $method,
            'shared' => $shared,
        ];
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private static function normalizeServiceMethodFactoryOperation(
        array $operation,
    ): array {
        self::assertExactKeys(
            $operation,
            [
                'arguments',
                'factoryServiceId',
                'id',
                'kind',
                'method',
                'shared',
            ],
        );

        $id = $operation['id'];
        $factoryServiceId = $operation['factoryServiceId'];
        $method = $operation['method'];
        $arguments = $operation['arguments'];
        $shared = $operation['shared'];

        if (
            !\is_string($id)
            || !\is_string($factoryServiceId)
            || !\is_string($method)
            || !\is_array($arguments)
            || !\is_bool($shared)
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        self::assertServiceId($id);
        self::assertServiceId($factoryServiceId);
        self::assertMethodName($method);

        return [
            'arguments' => self::normalizeDescriptorArguments($arguments),
            'factoryServiceId' => $factoryServiceId,
            'id' => $id,
            'kind' => ContainerDefinitionKind::SERVICE_FACTORY_SERVICE_METHOD->value,
            'method' => $method,
            'shared' => $shared,
        ];
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private static function normalizeAliasOperation(
        array $operation,
    ): array {
        self::assertExactKeys(
            $operation,
            [
                'alias',
                'kind',
                'serviceId',
            ],
        );

        $alias = $operation['alias'];
        $serviceId = $operation['serviceId'];

        if (!\is_string($alias) || !\is_string($serviceId)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        self::assertServiceId($alias);
        self::assertServiceId($serviceId);

        if ($alias === $serviceId) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        return [
            'alias' => $alias,
            'kind' => ContainerDefinitionKind::ALIAS->value,
            'serviceId' => $serviceId,
        ];
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private static function normalizeParameterOperation(
        array $operation,
    ): array {
        self::assertExactKeys(
            $operation,
            [
                'kind',
                'name',
                'value',
            ],
        );

        $name = $operation['name'];

        if (!\is_string($name)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        self::assertParameterName($name);

        return [
            'kind' => ContainerDefinitionKind::PARAMETER->value,
            'name' => $name,
            'value' => self::normalizeParameterValue($operation['value']),
        ];
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private static function normalizeTagOperation(
        array $operation,
    ): array {
        self::assertExactKeys(
            $operation,
            [
                'kind',
                'meta',
                'priority',
                'serviceId',
                'tag',
            ],
        );

        $tag = $operation['tag'];
        $serviceId = $operation['serviceId'];
        $priority = $operation['priority'];
        $meta = $operation['meta'];

        if (
            !\is_string($tag)
            || !\is_string($serviceId)
            || !\is_int($priority)
            || !\is_array($meta)
            || ($meta !== [] && \array_is_list($meta))
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        self::assertTag($tag);
        self::assertServiceId($serviceId);

        return [
            'kind' => ContainerDefinitionKind::TAG->value,
            'meta' => self::normalizeTagMeta($meta),
            'priority' => $priority,
            'serviceId' => $serviceId,
            'tag' => $tag,
        ];
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string> $expectedKeys
     */
    private static function assertExactKeys(
        array $value,
        array $expectedKeys,
        string $reason = ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
    ): void {
        $actualKeys = \array_keys($value);

        foreach ($actualKeys as $key) {
            if (!\is_string($key)) {
                throw ContainerDefinitionInvalidException::withReason($reason);
            }
        }

        /** @var list<string> $actualKeys */
        \usort(
            $actualKeys,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        \usort(
            $expectedKeys,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        if ($actualKeys !== $expectedKeys) {
            throw ContainerDefinitionInvalidException::withReason($reason);
        }
    }

    /**
     * @param list<mixed> $arguments
     *
     * @return list<mixed>
     */
    public static function normalizeArguments(array $arguments): array
    {
        if (
            !\array_is_list($arguments)
            || \count($arguments) > self::MAX_LIST_ITEMS
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        $nodeCount = 0;
        $normalized = [];

        foreach ($arguments as $argument) {
            $normalized[] = self::normalizeValue(
                value: $argument,
                depth: 0,
                nodeCount: $nodeCount,
                referenceObjectsAllowed: true,
                referenceMapsAllowed: false,
                emptyStringAllowed: true,
            );
        }

        return $normalized;
    }

    /**
     * Normalizes an already exported descriptor argument list.
     *
     * Descriptor input MUST use exact typed reference maps and MUST NOT contain
     * ContainerValueReference objects or any other objects.
     *
     * @param list<mixed> $arguments
     *
     * @return list<mixed>
     */
    private static function normalizeDescriptorArguments(
        array $arguments,
    ): array {
        if (
            !\array_is_list($arguments)
            || \count($arguments) > self::MAX_LIST_ITEMS
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        $nodeCount = 0;
        $normalized = [];

        foreach ($arguments as $argument) {
            $normalized[] = self::normalizeValue(
                value: $argument,
                depth: 0,
                nodeCount: $nodeCount,
                referenceObjectsAllowed: false,
                referenceMapsAllowed: true,
                emptyStringAllowed: true,
            );
        }

        return $normalized;
    }

    public static function normalizeParameterValue(mixed $value): mixed
    {
        $nodeCount = 0;

        return self::normalizeValue(
            value: $value,
            depth: 0,
            nodeCount: $nodeCount,
            referenceObjectsAllowed: false,
            referenceMapsAllowed: false,
            emptyStringAllowed: true,
        );
    }

    /**
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    public static function normalizeTagMeta(array $meta): array
    {
        if ($meta !== [] && \array_is_list($meta)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        $nodeCount = 0;

        $normalized = self::normalizeValue(
            value: $meta,
            depth: 0,
            nodeCount: $nodeCount,
            referenceObjectsAllowed: false,
            referenceMapsAllowed: false,
            emptyStringAllowed: true,
        );

        if (
            !\is_array($normalized)
            || ($normalized !== [] && \array_is_list($normalized))
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        /** @var array<string, mixed> $normalized */
        return $normalized;
    }

    public static function assertServiceId(
        string $id,
        string $reason = ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
    ): void {
        if (!ContainerServiceIdPolicy::isValid($id)) {
            throw ContainerDefinitionInvalidException::withReason($reason);
        }
    }

    public static function assertParameterName(
        string $name,
        string $reason = ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
    ): void {
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
            || self::looksLikeEnvValue($name)
            || self::looksSensitive($name)
        ) {
            throw ContainerDefinitionInvalidException::withReason($reason);
        }
    }

    public static function assertClassReference(
        string $class,
        string $reason = ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
    ): void {
        if (!self::isSafeClassReference($class)) {
            throw ContainerDefinitionInvalidException::withReason($reason);
        }
    }

    public static function assertMethodName(string $method): void
    {
        if (!self::isSafeMethodName($method)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }
    }

    public static function assertPublicStaticFactoryMethod(
        string $factoryClass,
        string $method,
    ): void {
        self::assertClassReference($factoryClass);
        self::assertMethodName($method);

        try {
            $reflection = new \ReflectionMethod(
                $factoryClass,
                $method,
            );
        } catch (\Throwable $exception) {
            throw ContainerDefinitionInvalidException::withReason(
                reason: ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
                previous: $exception,
            );
        }

        if (
            !$reflection->isPublic()
            || !$reflection->isStatic()
            || $reflection->isAbstract()
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }
    }

    public static function assertTag(string $tag): void
    {
        if (!TagNamePolicy::isValid($tag)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    private static function normalizeValue(
        mixed $value,
        int $depth,
        int &$nodeCount,
        bool $referenceObjectsAllowed,
        bool $referenceMapsAllowed,
        bool $emptyStringAllowed,
    ): null|bool|int|string|array {
        if ($depth > self::MAX_DEPTH) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        ++$nodeCount;

        if ($nodeCount > self::MAX_NODES) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        if ($value instanceof ContainerValueReference) {
            if (!$referenceObjectsAllowed) {
                throw ContainerDefinitionInvalidException::withReason(
                    ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
                );
            }

            return $value->toArray();
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            self::assertSafeSchemaString(
                value: $value,
                emptyStringAllowed: $emptyStringAllowed,
            );

            return $value;
        }

        if (
            \is_float($value)
            || \is_resource($value)
            || $value instanceof \Closure
            || \is_object($value)
            || !\is_array($value)
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        if (\array_is_list($value)) {
            if (\count($value) > self::MAX_LIST_ITEMS) {
                throw ContainerDefinitionInvalidException::withReason(
                    ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
                );
            }

            $normalized = [];

            foreach ($value as $item) {
                $normalized[] = self::normalizeValue(
                    value: $item,
                    depth: $depth + 1,
                    nodeCount: $nodeCount,
                    referenceObjectsAllowed: $referenceObjectsAllowed,
                    referenceMapsAllowed: $referenceMapsAllowed,
                    emptyStringAllowed: $emptyStringAllowed,
                );
            }

            return $normalized;
        }

        $reference = self::normalizeTypedReferenceMap(
            value: $value,
            allowed: $referenceMapsAllowed,
        );

        if ($reference !== null) {
            return $reference;
        }

        if (\count($value) > self::MAX_MAP_KEYS) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key) || !self::isSafeMapKey($key)) {
                throw ContainerDefinitionInvalidException::withReason(
                    ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
                );
            }

            $normalized[$key] = self::normalizeValue(
                value: $item,
                depth: $depth + 1,
                nodeCount: $nodeCount,
                referenceObjectsAllowed: $referenceObjectsAllowed,
                referenceMapsAllowed: $referenceMapsAllowed,
                emptyStringAllowed: $emptyStringAllowed,
            );
        }

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return null|array{class: string, type: string}|array{id: string, type: string}|array{name: string, type: string}
     */
    private static function normalizeTypedReferenceMap(
        array $value,
        bool $allowed,
    ): ?array {
        $type = $value['type'] ?? null;

        if (
            $type !== ContainerValueReference::TYPE_SERVICE
            && $type !== ContainerValueReference::TYPE_PARAMETER
            && $type !== ContainerValueReference::TYPE_CLASS
        ) {
            if (self::hasExactReferenceShape($value)) {
                throw ContainerDefinitionInvalidException::withReason(
                    ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
                );
            }

            return null;
        }

        if (!$allowed) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
            );
        }

        return match ($type) {
            ContainerValueReference::TYPE_SERVICE => self::normalizeServiceReferenceMap($value),
            ContainerValueReference::TYPE_PARAMETER => self::normalizeParameterReferenceMap($value),
            ContainerValueReference::TYPE_CLASS => self::normalizeClassReferenceMap($value),
        };
    }

    /**
     * Returns whether a map has one of the reserved typed-reference shapes.
     *
     * @param array<string, mixed> $value
     */
    private static function hasExactReferenceShape(
        array $value,
    ): bool {
        $keys = \array_keys($value);

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                return false;
            }
        }

        /** @var list<string> $keys */
        \usort(
            $keys,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $keys === ['id', 'type']
            || $keys === ['name', 'type']
            || $keys === ['class', 'type'];
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array{id: string, type: string}
     */
    private static function normalizeServiceReferenceMap(
        array $value,
    ): array {
        self::assertExactKeys(
            $value,
            [
                'id',
                'type',
            ],
            ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );

        $id = $value['id'];

        if (!\is_string($id)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
            );
        }

        self::assertServiceId(
            $id,
            ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );

        return [
            'id' => $id,
            'type' => ContainerValueReference::TYPE_SERVICE,
        ];
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array{name: string, type: string}
     */
    private static function normalizeParameterReferenceMap(
        array $value,
    ): array {
        self::assertExactKeys(
            $value,
            [
                'name',
                'type',
            ],
            ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );

        $name = $value['name'];

        if (!\is_string($name)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
            );
        }

        self::assertParameterName(
            $name,
            ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );

        return [
            'name' => $name,
            'type' => ContainerValueReference::TYPE_PARAMETER,
        ];
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array{class: string, type: string}
     */
    private static function normalizeClassReferenceMap(
        array $value,
    ): array {
        self::assertExactKeys(
            $value,
            [
                'class',
                'type',
            ],
            ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );

        $class = $value['class'];

        if (!\is_string($class)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
            );
        }

        self::assertClassReference(
            $class,
            ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );

        return [
            'class' => $class,
            'type' => ContainerValueReference::TYPE_CLASS,
        ];
    }

    private static function isSafeClassReference(string $class): bool
    {
        return $class !== ''
            && \strlen($class) <= self::MAX_ID_BYTES
            && \trim($class) === $class
            && \preg_match(self::CLASS_REFERENCE_PATTERN, $class) === 1
            && !isset(
                self::RESERVED_CLASS_REFERENCES[\strtolower($class)],
            )
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $class) !== 1
            && !\str_starts_with($class, '\\')
            && !\str_contains($class, '::')
            && !self::looksLikeAbsolutePath($class)
            && !self::looksLikeSourceSnippet($class);
    }

    private static function isSafeMethodName(string $method): bool
    {
        return $method !== ''
            && \preg_match(self::METHOD_PATTERN, $method) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $method) !== 1;
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

    private static function assertSafeSchemaString(
        string $value,
        bool $emptyStringAllowed,
    ): void {
        if (
            (!$emptyStringAllowed && $value === '')
            || \strlen($value) > self::MAX_STRING_BYTES
            || \preg_match('//u', $value) !== 1
            || \preg_match(
                self::CONTROL_CHARACTER_PATTERN,
                $value,
            ) === 1
            || \str_contains($value, '://')
            || \str_contains($value, '::')
            || self::looksLikeAbsolutePath($value)
            || self::looksLikeSourceSnippet($value)
            || self::looksLikeEnvValue($value)
            || self::looksSensitive($value)
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }
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
