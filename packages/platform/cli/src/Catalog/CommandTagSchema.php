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

namespace Coretsia\Platform\Cli\Catalog;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Runner\CompositeCommandInterface;

/**
 * Validates the exact CLI command tag schema and static class-constant parity.
 *
 * Canonical Foundation tag normalization owns baseline static-safe metadata
 * constraints. This class validates only the CLI-specific command shape; it
 * neither classifies sensitive values nor resolves command services.
 *
 * @internal
 */
final class CommandTagSchema
{
    private const string CLASS_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+\z/';
    private const string TOKEN_PATTERN = '/\A[a-z][a-z0-9-]*\z/';

    /**
     * @var array<class-string, true>
     */
    private const array COMPOSITE_SERVICE_IDS = [
        WorkflowRunCommand::class => true,
    ];

    /**
     * @var list<string>
     */
    private const array META_KEYS = [
        'name',
        'summary',
        'group',
        'hidden',
        'arguments',
        'options',
    ];

    /**
     * @var list<string>
     */
    private const array ARGUMENT_KEYS = [
        'name',
        'summary',
        'required',
        'variadic',
    ];

    /**
     * @var list<string>
     */
    private const array OPTION_KEYS = [
        'name',
        'summary',
        'value',
        'repeatable',
    ];

    /**
     * @var array<string, string>
     */
    private const array CONSTANT_KEYS = [
        'name' => 'NAME',
        'summary' => 'SUMMARY',
        'group' => 'GROUP',
        'hidden' => 'HIDDEN',
        'arguments' => 'ARGUMENTS',
        'options' => 'OPTIONS',
    ];

    /**
     * Validates one tagged service without instantiating or resolving it.
     */
    public function validate(TaggedService $tagged): CommandDescriptor
    {
        return CommandDescriptor::fromValidatedMetadata($tagged->id(), $tagged->meta());
    }

    public static function isCompositeServiceId(string $serviceId): bool
    {
        return isset(self::COMPOSITE_SERVICE_IDS[$serviceId]);
    }

    /**
     * Returns canonical static metadata after full tag and class validation.
     *
     * @return array{
     *     name: string,
     *     summary: string,
     *     group: string,
     *     hidden: bool,
     *     arguments: list<array{name: string, summary: string, required: bool, variadic: bool}>,
     *     options: list<array{name: string, summary: string, value: 'none'|'required'|'optional', repeatable: bool}>,
     * }
     *
     * @internal
     */
    public function canonicalMetadata(TaggedService $tagged): array
    {
        $id = $tagged->id();

        if (\preg_match(self::CLASS_PATTERN, $id) !== 1) {
            throw self::invalid(InvalidCommandTagMetaException::REASON_SERVICE_INVALID);
        }

        try {
            $reflection = new \ReflectionClass($id);
        } catch (\Throwable) {
            throw self::invalid(InvalidCommandTagMetaException::REASON_SERVICE_INVALID);
        }

        $composite = self::isCompositeServiceId($id);

        if (
            $reflection->getName() !== $id
            || $reflection->isAbstract()
            || ($composite && (
                !$reflection->implementsInterface(CompositeCommandInterface::class)
                    || $reflection->implementsInterface(CommandInterface::class)
            ))
            || (!$composite && (
                !$reflection->implementsInterface(CommandInterface::class)
                    || $reflection->implementsInterface(CompositeCommandInterface::class)
            ))
        ) {
            throw self::invalid(InvalidCommandTagMetaException::REASON_SERVICE_INVALID);
        }

        $meta = self::normalize($tagged->meta());
        self::assertExactKeys($meta, self::META_KEYS);
        self::assertMetadata($meta);

        $classMeta = [];

        foreach (self::CONSTANT_KEYS as $key => $constantName) {
            $constant = $reflection->getReflectionConstant($constantName);

            $expectedType = match ($key) {
                'hidden' => 'bool',
                'arguments', 'options' => 'array',
                default => 'string',
            };
            $type = $constant === false ? null : $constant->getType();

            if (
                $constant === false
                || !$constant->isPublic()
                || !($type instanceof \ReflectionNamedType)
                || $type->getName() !== $expectedType
                || $type->allowsNull()
            ) {
                throw self::invalid(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID);
            }

            try {
                $classMeta[$key] = $constant->getValue();
            } catch (\Throwable) {
                throw self::invalid(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID);
            }
        }

        $classMeta = self::normalize($classMeta);
        self::assertMetadata($classMeta);

        if ($classMeta !== $meta) {
            throw self::invalid(InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH);
        }

        /** @var array{
         *     name: string,
         *     summary: string,
         *     group: string,
         *     hidden: bool,
         *     arguments: list<array{
         *         name: string,
         *         summary: string,
         *         required: bool,
         *         variadic: bool,
         *     }>,
         *     options: list<array{
         *         name: string,
         *         summary: string,
         *         value: 'none'|'required'|'optional',
         *         repeatable: bool,
         *     }>,
         * } $meta
         */
        return $meta;
    }

    /**
     * Reuses Foundation static tag-metadata normalization (including its
     * JSON-like shape, resource limits, and static-safe string policy).
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function normalize(array $metadata): array
    {
        try {
            $definitions = new ContainerDefinitionBuilder()
                ->tag(ReservedTags::CLI_COMMAND, self::class, meta: $metadata)
                ->build()
                ->toDescriptorStream();

            /** @var array<string, mixed> $normalized */
            $normalized = $definitions[0]['meta'];

            return $normalized;
        } catch (\Throwable) {
            throw self::invalid(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID);
        }
    }

    /**
     * Checks a non-empty static-safe text value through the same Foundation
     * policy used for command tag metadata.
     */
    public static function isSafeSummary(mixed $value): bool
    {
        if (!\is_string($value) || \trim($value) === '') {
            return false;
        }

        try {
            self::normalize(['summary' => $value]);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public static function isValidName(string $name): bool
    {
        return \preg_match('~' . CommandInterface::COMMAND_NAME_PATTERN . '~D', $name) === 1;
    }

    public static function isValidGroup(string $group): bool
    {
        if (\preg_match(self::TOKEN_PATTERN, $group) !== 1) {
            return false;
        }

        try {
            self::normalize(['group' => $group]);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function assertMetadata(array $meta): void
    {
        self::assertExactKeys($meta, self::META_KEYS);

        if (
            !\is_string($meta['name'])
            || !self::isValidName($meta['name'])
            || !self::isSafeSummary($meta['summary'])
            || !\is_string($meta['group'])
            || !self::isValidGroup($meta['group'])
            || !\is_bool($meta['hidden'])
            || !\is_array($meta['arguments'])
            || !\array_is_list($meta['arguments'])
            || !\is_array($meta['options'])
            || !\array_is_list($meta['options'])
        ) {
            throw self::invalid(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID);
        }

        self::assertArguments($meta['arguments']);
        self::assertOptions($meta['options']);
    }

    /**
     * @param array<mixed> $arguments
     */
    private static function assertArguments(array $arguments): void
    {
        $seen = [];
        $optionalSeen = false;
        $lastIndex = \count($arguments) - 1;

        foreach ($arguments as $index => $argument) {
            if (!\is_array($argument)) {
                throw self::invalid(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID);
            }

            self::assertExactKeys($argument, self::ARGUMENT_KEYS);

            if (
                !\is_string($argument['name'])
                || !self::isValidGroup($argument['name'])
                || isset($seen[$argument['name']])
                || !self::isSafeSummary($argument['summary'])
                || !\is_bool($argument['required'])
                || !\is_bool($argument['variadic'])
                || ($optionalSeen && $argument['required'])
                || ($argument['variadic'] && $index !== $lastIndex)
            ) {
                throw self::invalid(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID);
            }

            $seen[$argument['name']] = true;

            if (!$argument['required']) {
                $optionalSeen = true;
            }
        }
    }

    /**
     * @param array<mixed> $options
     */
    private static function assertOptions(array $options): void
    {
        $seen = [];

        foreach ($options as $option) {
            if (!\is_array($option)) {
                throw self::invalid(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID);
            }

            self::assertExactKeys($option, self::OPTION_KEYS);

            if (
                !\is_string($option['name'])
                || !self::isValidGroup($option['name'])
                || isset($seen[$option['name']])
                || $option['name'] === 'format'
                || $option['name'] === 'color'
                || !self::isSafeSummary($option['summary'])
                || !\is_string($option['value'])
                || !\in_array($option['value'], ['none', 'required', 'optional'], true)
                || !\is_bool($option['repeatable'])
                || ($option['repeatable'] && $option['value'] !== 'required')
            ) {
                throw self::invalid(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID);
            }

            $seen[$option['name']] = true;
        }
    }

    /**
     * @param array<array-key, mixed> $actual
     * @param list<string> $expected
     */
    private static function assertExactKeys(array $actual, array $expected): void
    {
        $actualKeys = \array_keys($actual);

        \sort($actualKeys, \SORT_STRING);
        \sort($expected, \SORT_STRING);

        if ($actualKeys !== $expected) {
            throw self::invalid(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID);
        }
    }

    private static function invalid(string $reason): InvalidCommandTagMetaException
    {
        return InvalidCommandTagMetaException::withReason($reason);
    }
}
