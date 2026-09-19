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

namespace Coretsia\Foundation\Container\Definition;

use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Foundation\Container\Internal\ContainerDefinitionPolicy;

/**
 * Immutable ordered declarative container-definition set.
 *
 * Operation order is semantic and is preserved exactly. The set MUST NOT sort
 * service, alias, parameter, or tag operations before collision semantics are
 * applied by a source-runtime or compilation adapter.
 */
final readonly class ContainerDefinitionSet
{
    private const int MAX_OPERATIONS = 100_000;
    private const int MAX_REQUIRED_SERVICES = 10_000;

    /**
     * @param list<array<string, mixed>> $operations
     * @param list<string> $requiredServiceIds
     */
    private function __construct(
        private array $operations,
        private array $requiredServiceIds,
    ) {
    }

    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * Creates an immutable definition set from fully revalidated external state.
     *
     * Every operation is validated independently even when the caller is
     * ContainerDefinitionBuilder. The public factory MUST NOT trust its name,
     * caller, PHPDoc, or prior normalization.
     *
     * @param list<array<string, mixed>> $operations
     * @param list<string> $requiredServiceIds
     *
     * @internal
     */
    public static function fromValidatedState(
        array $operations,
        array $requiredServiceIds,
    ): self {
        if (
            !\array_is_list($operations)
            || \count($operations) > self::MAX_OPERATIONS
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        if (
            !\array_is_list($requiredServiceIds)
            || \count($requiredServiceIds)
            > self::MAX_REQUIRED_SERVICES
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_REQUIRED_SERVICE_INVALID,
            );
        }

        $normalizedOperations = [];

        foreach ($operations as $operation) {
            if (!\is_array($operation) || \array_is_list($operation)) {
                throw ContainerDefinitionInvalidException::withReason(
                    ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
                );
            }

            /** @var array<string, mixed> $operation */
            $normalizedOperations[] =
                ContainerDefinitionPolicy::normalizeOperation($operation);
        }

        $required = [];

        foreach ($requiredServiceIds as $serviceId) {
            if (!\is_string($serviceId)) {
                throw ContainerDefinitionInvalidException::withReason(
                    ContainerDefinitionInvalidException::REASON_REQUIRED_SERVICE_INVALID,
                );
            }

            ContainerDefinitionPolicy::assertServiceId(
                $serviceId,
                ContainerDefinitionInvalidException::REASON_REQUIRED_SERVICE_INVALID,
            );

            $required[$serviceId] = true;
        }

        $requiredServiceIds = \array_keys($required);

        \usort(
            $requiredServiceIds,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return new self(
            operations: $normalizedOperations,
            requiredServiceIds: $requiredServiceIds,
        );
    }

    public static function merge(self ...$sets): self
    {
        $operations = [];
        $required = [];

        foreach ($sets as $set) {
            foreach ($set->operations as $operation) {
                $operations[] = $operation;
            }

            foreach ($set->requiredServiceIds as $serviceId) {
                $required[$serviceId] = true;
            }
        }

        return self::fromValidatedState(
            operations: $operations,
            requiredServiceIds: \array_keys($required),
        );
    }

    /**
     * Returns the canonical descriptor stream in semantic operation order.
     *
     * @return list<array<string, mixed>>
     */
    public function toDescriptorStream(): array
    {
        return $this->operations;
    }

    /**
     * @return list<string>
     */
    public function requiredServiceIds(): array
    {
        return $this->requiredServiceIds;
    }

    public function isEmpty(): bool
    {
        return $this->operations === []
            && $this->requiredServiceIds === [];
    }
}
