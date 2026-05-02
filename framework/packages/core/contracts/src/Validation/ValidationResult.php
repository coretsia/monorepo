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

namespace Coretsia\Contracts\Validation;

/**
 * Immutable validation result with an ordered list of violations.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts shape policy.
 *
 * The result preserves the owner-provided violation list order. Stable
 * violation ordering is a runtime concern; the Violation shape exposes
 * deterministic sort keys for runtime owners.
 *
 * ValidationResult MUST NOT contain raw input payload values, request objects,
 * response objects, PSR-7 objects, service instances, closures, resources,
 * executable validators, or runtime wiring objects.
 */
final readonly class ValidationResult
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @var list<Violation>
     */
    private array $violations;

    /**
     * @param list<Violation> $violations
     */
    private function __construct(
        private bool $success,
        array $violations,
    ) {
        $this->violations = self::normalizeViolations($violations);

        if ($this->success && $this->violations !== []) {
            throw new \InvalidArgumentException('Successful validation result must not contain violations.');
        }

        if (!$this->success && $this->violations === []) {
            throw new \InvalidArgumentException('Failed validation result must contain violations.');
        }
    }

    public static function success(): self
    {
        return new self(true, []);
    }

    /**
     * @param array<array-key,Violation> $violations
     */
    public static function failure(array $violations): self
    {
        if ($violations === []) {
            throw new \InvalidArgumentException('Failed validation result must contain violations.');
        }

        return new self(false, $violations);
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * Returns the ordered owner-provided violation list.
     *
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @return array{
     *     schemaVersion: int,
     *     success: bool,
     *     violations: list<array<string,mixed>>
     * }
     */
    public function toArray(): array
    {
        $violations = [];

        foreach ($this->violations as $violation) {
            $violations[] = $violation->toArray();
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'success' => $this->success,
            'violations' => $violations,
        ];
    }

    /**
     * @param array<array-key,Violation> $violations
     *
     * @return list<Violation>
     */
    private static function normalizeViolations(array $violations): array
    {
        if (!array_is_list($violations)) {
            throw new \InvalidArgumentException('Validation result violations must be a list.');
        }

        $out = [];

        foreach ($violations as $violation) {
            if (!$violation instanceof Violation) {
                throw new \InvalidArgumentException(
                    'Validation result violations must contain only Violation instances.'
                );
            }

            $out[] = $violation;
        }

        return $out;
    }
}
