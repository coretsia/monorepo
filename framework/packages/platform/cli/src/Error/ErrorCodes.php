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

namespace Coretsia\Platform\Cli\Error;

/**
 * CLI-owned deterministic error codes registry (SSoT).
 *
 * Invariant:
 * - CORETSIA_CLI_UNCAUGHT_EXCEPTION is launcher-only (catch-all).
 * - Domain logic SHOULD NOT throw/emit CORETSIA_CLI_UNCAUGHT_EXCEPTION.
 */
final class ErrorCodes
{
    public const string CORETSIA_CLI_COMMAND_CLASS_MISSING = 'CORETSIA_CLI_COMMAND_CLASS_MISSING';
    public const string CORETSIA_CLI_COMMAND_FAILED = 'CORETSIA_CLI_COMMAND_FAILED';
    public const string CORETSIA_CLI_COMMAND_INVALID = 'CORETSIA_CLI_COMMAND_INVALID';
    public const string CORETSIA_CLI_CONFIG_INVALID = 'CORETSIA_CLI_CONFIG_INVALID';
    public const string CORETSIA_CLI_UNCAUGHT_EXCEPTION = 'CORETSIA_CLI_UNCAUGHT_EXCEPTION';

    /**
     * @var list<string>
     */
    private const array REGISTRY = [
        self::CORETSIA_CLI_COMMAND_CLASS_MISSING,
        self::CORETSIA_CLI_COMMAND_FAILED,
        self::CORETSIA_CLI_COMMAND_INVALID,
        self::CORETSIA_CLI_CONFIG_INVALID,
        self::CORETSIA_CLI_UNCAUGHT_EXCEPTION,
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
     * @return list<string> Codes sorted deterministically by strcmp() byte-order.
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
            throw new \LogicException('cli-error-codes-registry-contains-duplicates');
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
