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

use Coretsia\Foundation\Container\Internal\ContainerDefinitionPolicy;

/**
 * Immutable Foundation declarative service definition.
 *
 * This is a canonical in-memory source-definition model. It is not an artifact
 * envelope and does not instantiate the represented service.
 */
final readonly class ContainerServiceDefinition
{
    /**
     * @param list<mixed> $arguments
     */
    private function __construct(
        private string $id,
        private ContainerDefinitionKind $kind,
        private array $arguments,
        private bool $shared,
        private ?string $className,
        private ?string $factoryClass,
        private ?string $factoryServiceId,
        private ?string $method,
    ) {
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function classService(
        string $id,
        string $class,
        array $arguments = [],
        bool $shared = true,
    ): self {
        ContainerDefinitionPolicy::assertServiceId($id);
        ContainerDefinitionPolicy::assertClassReference($class);

        return new self(
            id: $id,
            kind: ContainerDefinitionKind::SERVICE_CLASS,
            arguments: ContainerDefinitionPolicy::normalizeArguments($arguments),
            shared: $shared,
            className: $class,
            factoryClass: null,
            factoryServiceId: null,
            method: null,
        );
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function classMethodFactory(
        string $id,
        string $factoryClass,
        string $method,
        array $arguments = [],
        bool $shared = true,
    ): self {
        ContainerDefinitionPolicy::assertServiceId($id);
        ContainerDefinitionPolicy::assertPublicStaticFactoryMethod(
            factoryClass: $factoryClass,
            method: $method,
        );

        return new self(
            id: $id,
            kind: ContainerDefinitionKind::SERVICE_FACTORY_CLASS_METHOD,
            arguments: ContainerDefinitionPolicy::normalizeArguments($arguments),
            shared: $shared,
            className: null,
            factoryClass: $factoryClass,
            factoryServiceId: null,
            method: $method,
        );
    }

    /**
     * Defines a factory method invoked on another container service.
     *
     * The method name is validated lexically at definition time. Method existence,
     * visibility, and compatibility cannot be validated until the concrete factory
     * service definition is known; that validation belongs to final container-graph
     * completeness validation.
     *
     * @param list<mixed> $arguments
     */
    public static function serviceMethodFactory(
        string $id,
        string $factoryServiceId,
        string $method,
        array $arguments = [],
        bool $shared = true,
    ): self {
        ContainerDefinitionPolicy::assertServiceId($id);
        ContainerDefinitionPolicy::assertServiceId($factoryServiceId);
        ContainerDefinitionPolicy::assertMethodName($method);

        return new self(
            id: $id,
            kind: ContainerDefinitionKind::SERVICE_FACTORY_SERVICE_METHOD,
            arguments: ContainerDefinitionPolicy::normalizeArguments($arguments),
            shared: $shared,
            className: null,
            factoryClass: null,
            factoryServiceId: $factoryServiceId,
            method: $method,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function kind(): ContainerDefinitionKind
    {
        return $this->kind;
    }

    public function shared(): bool
    {
        return $this->shared;
    }

    /**
     * @return list<mixed>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * Exports the canonical descriptor operation consumed by the Kernel's
     * low-level container compiler.
     *
     * @return array<string, mixed>
     */
    public function toDescriptor(): array
    {
        return match ($this->kind) {
            ContainerDefinitionKind::SERVICE_CLASS => [
                'arguments' => $this->arguments,
                'class' => $this->className,
                'id' => $this->id,
                'kind' => $this->kind->value,
                'shared' => $this->shared,
            ],
            ContainerDefinitionKind::SERVICE_FACTORY_CLASS_METHOD => [
                'arguments' => $this->arguments,
                'factoryClass' => $this->factoryClass,
                'id' => $this->id,
                'kind' => $this->kind->value,
                'method' => $this->method,
                'shared' => $this->shared,
            ],
            ContainerDefinitionKind::SERVICE_FACTORY_SERVICE_METHOD => [
                'arguments' => $this->arguments,
                'factoryServiceId' => $this->factoryServiceId,
                'id' => $this->id,
                'kind' => $this->kind->value,
                'method' => $this->method,
                'shared' => $this->shared,
            ],
            default => throw new \LogicException('container-service-definition-kind-invalid'),
        };
    }
}
