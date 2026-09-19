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

namespace Coretsia\Kernel\Module\Exception;

/**
 * Kernel-owned deterministic module resolution error/warning codes registry.
 *
 * These constants are stable machine-readable identifiers for Kernel module
 * plan resolution failures and non-fatal warnings.
 *
 * This class is intentionally owned by `core/kernel`.
 *
 * It is not a contracts port, must not be moved to `core/contracts`, and must
 * not be treated as a cross-package interface. Future adapters may read these
 * codes through Kernel exceptions/warnings, but ownership of the codes remains
 * with Kernel module resolution policy.
 *
 * Exception messages remain separate fixed safe tokens and must not include
 * Composer raw payloads, preset raw payloads, service internals, secrets,
 * absolute paths, filesystem layout, environment-specific data, stack traces,
 * or previous throwable messages.
 */
final class ModuleErrorCodes
{
    public const string CORETSIA_MODE_PRESET_NOT_FOUND = 'CORETSIA_MODE_PRESET_NOT_FOUND';
    public const string CORETSIA_MODE_PRESET_INVALID = 'CORETSIA_MODE_PRESET_INVALID';
    public const string CORETSIA_MODULE_MANIFEST_INVALID = 'CORETSIA_MODULE_MANIFEST_INVALID';
    public const string CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED = 'CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED';
    public const string CORETSIA_MODULE_CYCLE_DETECTED = 'CORETSIA_MODULE_CYCLE_DETECTED';
    public const string CORETSIA_MODULE_CONFLICT = 'CORETSIA_MODULE_CONFLICT';
    public const string CORETSIA_MODULE_REQUIRED_MISSING = 'CORETSIA_MODULE_REQUIRED_MISSING';
    public const string CORETSIA_MODULE_OPTIONAL_MISSING = 'CORETSIA_MODULE_OPTIONAL_MISSING';

    /**
     * @var list<string>
     */
    private const array REGISTRY = [
        self::CORETSIA_MODE_PRESET_NOT_FOUND,
        self::CORETSIA_MODE_PRESET_INVALID,
        self::CORETSIA_MODULE_MANIFEST_INVALID,
        self::CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED,
        self::CORETSIA_MODULE_CYCLE_DETECTED,
        self::CORETSIA_MODULE_CONFLICT,
        self::CORETSIA_MODULE_REQUIRED_MISSING,
        self::CORETSIA_MODULE_OPTIONAL_MISSING,
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
            throw new \LogicException('module-error-codes-registry-contains-duplicates');
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
