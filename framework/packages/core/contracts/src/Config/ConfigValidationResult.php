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

namespace Coretsia\Contracts\Config;

/**
 * Immutable config validation result.
 */
final readonly class ConfigValidationResult
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @var list<ConfigValidationViolation>
     */
    private array $violations;

    /**
     * @param list<ConfigValidationViolation> $violations
     */
    public function __construct(array $violations = [])
    {
        $this->violations = self::normalizeViolations($violations);
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public static function success(): self
    {
        return new self();
    }

    /**
     * @param list<ConfigValidationViolation> $violations
     */
    public static function failure(array $violations): self
    {
        if ($violations === []) {
            throw new \InvalidArgumentException('Failed config validation result must contain at least one violation.');
        }

        return new self($violations);
    }

    public function isSuccess(): bool
    {
        return $this->violations === [];
    }

    public function isFailure(): bool
    {
        return $this->violations !== [];
    }

    /**
     * @return list<ConfigValidationViolation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @return array{
     *     schemaVersion: int,
     *     success: bool,
     *     violations: list<array{
     *         actualType?: string,
     *         expected?: string,
     *         path: string,
     *         reason: string,
     *         root: string,
     *         schemaVersion: int
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'success' => $this->isSuccess(),
            'violations' => array_map(
                static fn (ConfigValidationViolation $violation): array => $violation->toArray(),
                $this->violations,
            ),
        ];
    }

    /**
     * @param list<ConfigValidationViolation> $violations
     *
     * @return list<ConfigValidationViolation>
     */
    private static function normalizeViolations(array $violations): array
    {
        if (!array_is_list($violations)) {
            throw new \InvalidArgumentException('Config validation violations must be a list.');
        }

        foreach ($violations as $violation) {
            if (!$violation instanceof ConfigValidationViolation) {
                throw new \InvalidArgumentException(
                    'Config validation violations must contain only ConfigValidationViolation instances.'
                );
            }
        }

        usort(
            $violations,
            static function (ConfigValidationViolation $a, ConfigValidationViolation $b): int {
                return strcmp($a->root(), $b->root())
                    ?: strcmp($a->path(), $b->path())
                        ?: strcmp($a->reason(), $b->reason())
                            ?: strcmp($a->expected() ?? '', $b->expected() ?? '')
                                ?: strcmp($a->actualType() ?? '', $b->actualType() ?? '');
            },
        );

        /** @var list<ConfigValidationViolation> $violations */
        return $violations;
    }
}
