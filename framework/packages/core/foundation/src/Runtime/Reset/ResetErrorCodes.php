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

namespace Coretsia\Foundation\Runtime\Reset;

/**
 * Foundation-owned deterministic reset error codes registry.
 *
 * Error codes are stable machine-readable identifiers for reset orchestration
 * failures. Exception messages remain separate fixed safe tokens and must not
 * include service internals, payloads, secrets, raw context values, absolute
 * paths, or environment-specific data.
 */
final class ResetErrorCodes
{
    public const string CORETSIA_RESET_META_INVALID = 'CORETSIA_RESET_META_INVALID';

    public const string CORETSIA_RESET_SERVICE_NOT_RESETTABLE = 'CORETSIA_RESET_SERVICE_NOT_RESETTABLE';

    public const string CORETSIA_RESET_SERVICE_FAILED = 'CORETSIA_RESET_SERVICE_FAILED';

    public const string CORETSIA_RESET_OBSERVABILITY_FAILED = 'CORETSIA_RESET_OBSERVABILITY_FAILED';

    /**
     * @var list<string>
     */
    private const array REGISTRY = [
        self::CORETSIA_RESET_META_INVALID,
        self::CORETSIA_RESET_SERVICE_NOT_RESETTABLE,
        self::CORETSIA_RESET_SERVICE_FAILED,
        self::CORETSIA_RESET_OBSERVABILITY_FAILED,
    ];

    /**
     * @var array<string, true>|null
     */
    private static ?array $lookup = null;

    /**
     * @var list<string>|null
     */
    private static ?array $sorted = null;

    private function __construct()
    {
    }

    public static function has(string $code): bool
    {
        self::initialize();

        return isset(self::$lookup[$code]);
    }

    /**
     * @return list<string> Sorted ascending by byte-order strcmp().
     */
    public static function all(): array
    {
        self::initialize();

        /** @var list<string> $sorted */
        $sorted = self::$sorted;

        return $sorted;
    }

    private static function initialize(): void
    {
        if (self::$lookup !== null && self::$sorted !== null) {
            return;
        }

        $codes = self::REGISTRY;

        $unique = \array_values(\array_unique($codes));
        if (\count($unique) !== \count($codes)) {
            throw new \LogicException('reset-error-codes-registry-contains-duplicates');
        }

        \usort(
            $codes,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        $lookup = [];
        foreach ($codes as $code) {
            $lookup[$code] = true;
        }

        self::$sorted = $codes;
        self::$lookup = $lookup;
    }
}
