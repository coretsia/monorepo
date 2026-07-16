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

/**
 * Immutable input context for declarative container-definition production.
 *
 * The context contains only an already-compiled Phase-B config snapshot. It
 * intentionally does not expose BootstrapConfig, env repositories, filesystem
 * paths, a container instance, source config locations, or generated artifacts.
 */
final readonly class ContainerDefinitionContext
{
    /**
     * @var array<string, mixed>
     */
    private array $compiledConfig;

    /**
     * @param array<string, mixed> $compiledConfig
     */
    public function __construct(array $compiledConfig)
    {
        if ($compiledConfig !== [] && \array_is_list($compiledConfig)) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        self::assertStringMap($compiledConfig);

        $this->compiledConfig = $compiledConfig;
    }

    /**
     * Returns one strict global config root from the compiled Phase-B snapshot.
     *
     * @return array<string, mixed>
     */
    public function configRoot(string $root): array
    {
        if (
            $root === ''
            || \trim($root) !== $root
            || \preg_match('/\s/u', $root) !== 0
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        $value = $this->compiledConfig[$root] ?? null;

        if (
            !\is_array($value)
            || ($value !== [] && \array_is_list($value))
        ) {
            throw ContainerDefinitionInvalidException::withReason(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
            );
        }

        self::assertStringMap($value);

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function assertStringMap(array $value): void
    {
        foreach ($value as $key => $_item) {
            if (!\is_string($key)) {
                throw ContainerDefinitionInvalidException::withReason(
                    ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
                );
            }
        }
    }
}
