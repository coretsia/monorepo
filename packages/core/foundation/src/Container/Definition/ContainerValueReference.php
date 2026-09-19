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
 * Typed declarative reference used inside constructor/factory arguments.
 *
 * The reference stores only deterministic schema identity. It never stores a
 * resolved service, object instance, callable, Closure, config source, env
 * source, filesystem path, or source snippet.
 */
final readonly class ContainerValueReference
{
    public const string TYPE_SERVICE = 'service';
    public const string TYPE_PARAMETER = 'parameter';
    public const string TYPE_CLASS = 'class';

    private function __construct(
        private string $type,
        private string $identifier,
    ) {
    }

    public static function service(string $serviceId): self
    {
        ContainerDefinitionPolicy::assertServiceId(
            $serviceId,
            ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );

        return new self(
            self::TYPE_SERVICE,
            $serviceId,
        );
    }

    public static function parameter(string $parameterName): self
    {
        ContainerDefinitionPolicy::assertParameterName(
            $parameterName,
            ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );

        return new self(
            self::TYPE_PARAMETER,
            $parameterName,
        );
    }

    public static function class(string $className): self
    {
        ContainerDefinitionPolicy::assertClassReference(
            $className,
            ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );

        return new self(
            self::TYPE_CLASS,
            $className,
        );
    }

    public function type(): string
    {
        return $this->type;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return array{class: string, type: string}|array{id: string, type: string}|array{name: string, type: string}
     */
    public function toArray(): array
    {
        return match ($this->type) {
            self::TYPE_SERVICE => [
                'id' => $this->identifier,
                'type' => self::TYPE_SERVICE,
            ],
            self::TYPE_PARAMETER => [
                'name' => $this->identifier,
                'type' => self::TYPE_PARAMETER,
            ],
            self::TYPE_CLASS => [
                'class' => $this->identifier,
                'type' => self::TYPE_CLASS,
            ],
            default => throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
            ),
        };
    }
}
