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
 * Mutable builder for one ordered declarative container-definition
 * contribution.
 *
 * The built ContainerDefinitionSet is immutable. Builder call order is
 * preserved exactly because later binding and first-tag-registration semantics
 * depend on it.
 */
final class ContainerDefinitionBuilder
{
    private const int MAX_OPERATIONS = 100_000;
    private const int MAX_REQUIRED_SERVICES = 10_000;

    /**
     * @var list<array<string, mixed>>
     */
    private array $operations = [];

    /**
     * @var array<string, true>
     */
    private array $requiredServiceIds = [];

    /**
     * @param list<mixed> $arguments
     */
    public function classService(
        string $id,
        string $class,
        array $arguments = [],
        bool $shared = true,
    ): self {
        return $this->service(
            ContainerServiceDefinition::classService(
                id: $id,
                class: $class,
                arguments: $arguments,
                shared: $shared,
            ),
        );
    }

    /**
     * @param list<mixed> $arguments
     */
    public function classMethodFactory(
        string $id,
        string $factoryClass,
        string $method,
        array $arguments = [],
        bool $shared = true,
    ): self {
        return $this->service(
            ContainerServiceDefinition::classMethodFactory(
                id: $id,
                factoryClass: $factoryClass,
                method: $method,
                arguments: $arguments,
                shared: $shared,
            ),
        );
    }

    /**
     * @param list<mixed> $arguments
     */
    public function serviceMethodFactory(
        string $id,
        string $factoryServiceId,
        string $method,
        array $arguments = [],
        bool $shared = true,
    ): self {
        return $this->service(
            ContainerServiceDefinition::serviceMethodFactory(
                id: $id,
                factoryServiceId: $factoryServiceId,
                method: $method,
                arguments: $arguments,
                shared: $shared,
            ),
        );
    }

    public function service(
        ContainerServiceDefinition $definition,
    ): self {
        return $this->append($definition->toDescriptor());
    }

    public function alias(string $alias, string $serviceId): self
    {
        ContainerDefinitionPolicy::assertServiceId($alias);
        ContainerDefinitionPolicy::assertServiceId($serviceId);

        if ($alias === $serviceId) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        return $this->append([
            'alias' => $alias,
            'kind' => ContainerDefinitionKind::ALIAS->value,
            'serviceId' => $serviceId,
        ]);
    }

    public function parameter(string $name, mixed $value): self
    {
        ContainerDefinitionPolicy::assertParameterName($name);

        return $this->append([
            'kind' => ContainerDefinitionKind::PARAMETER->value,
            'name' => $name,
            'value' => ContainerDefinitionPolicy::normalizeParameterValue($value),
        ]);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function tag(
        string $tag,
        string $serviceId,
        int $priority = 0,
        array $meta = [],
    ): self {
        ContainerDefinitionPolicy::assertTag($tag);
        ContainerDefinitionPolicy::assertServiceId($serviceId);

        return $this->append([
            'kind' => ContainerDefinitionKind::TAG->value,
            'meta' => ContainerDefinitionPolicy::normalizeTagMeta($meta),
            'priority' => $priority,
            'serviceId' => $serviceId,
            'tag' => $tag,
        ]);
    }

    public function requireService(string $serviceId): self
    {
        ContainerDefinitionPolicy::assertServiceId(
            $serviceId,
            ContainerDefinitionInvalidException::REASON_REQUIRED_SERVICE_INVALID,
        );

        $this->requiredServiceIds[$serviceId] = true;

        if (
            \count($this->requiredServiceIds)
            > self::MAX_REQUIRED_SERVICES
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_REQUIRED_SERVICE_INVALID,
            );
        }

        return $this;
    }

    public function build(): ContainerDefinitionSet
    {
        return ContainerDefinitionSet::fromValidatedState(
            operations: $this->operations,
            requiredServiceIds: \array_keys($this->requiredServiceIds),
        );
    }

    /**
     * @param array<string, mixed> $operation
     */
    private function append(array $operation): self
    {
        if (\count($this->operations) >= self::MAX_OPERATIONS) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        $this->operations[] = $operation;

        return $this;
    }
}
