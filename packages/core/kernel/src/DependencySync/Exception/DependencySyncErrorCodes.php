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

namespace Coretsia\Kernel\DependencySync\Exception;

final class DependencySyncErrorCodes
{
    public const string APPLICATION_SET_INVALID = 'APPLICATION_SET_INVALID';
    public const string INSTALLATION_INTENT_INVALID = 'INSTALLATION_INTENT_INVALID';
    public const string PROJECT_ROOT_INVALID = 'PROJECT_ROOT_INVALID';
    public const string BASELINE_NOT_INSTALLED = 'BASELINE_NOT_INSTALLED';
    public const string PRESET_POLICY_INVALID = 'PRESET_POLICY_INVALID';
    public const string CATALOG_INVALID = 'CATALOG_INVALID';
    public const string CATALOG_MODULE_UNKNOWN = 'CATALOG_MODULE_UNKNOWN';
    public const string EXCLUDED_REQUIRED_DEPENDENCY = 'EXCLUDED_REQUIRED_DEPENDENCY';
    public const string MODULE_GRAPH_CONFLICT = 'MODULE_GRAPH_CONFLICT';
    public const string MANAGED_STATE_INVALID = 'MANAGED_STATE_INVALID';
    public const string MANAGED_STATE_CONFLICT = 'MANAGED_STATE_CONFLICT';
    public const string COMPOSER_MANIFEST_INVALID = 'COMPOSER_MANIFEST_INVALID';
    public const string COMPOSER_MANIFEST_WRITE_FAILED = 'COMPOSER_MANIFEST_WRITE_FAILED';
    public const string COMPOSER_LOCK_STALE = 'COMPOSER_LOCK_STALE';
    public const string COMPOSER_EXECUTION_FAILED = 'COMPOSER_EXECUTION_FAILED';
    public const string COMPOSER_UNSUPPORTED_POLICY = 'COMPOSER_UNSUPPORTED_POLICY';
    public const string INSTALLED_METADATA_INVALID = 'INSTALLED_METADATA_INVALID';
    public const string INSTALLED_VENDOR_INCOMPLETE = 'INSTALLED_VENDOR_INCOMPLETE';
    public const string INSTALLED_PLAN_MISMATCH = 'INSTALLED_PLAN_MISMATCH';
    public const string VERIFICATION_INPUT_INVALID = 'VERIFICATION_INPUT_INVALID';
    public const string VERIFICATION_EXECUTION_FAILED = 'VERIFICATION_EXECUTION_FAILED';
    public const string PROJECT_SYNC_LOCKED = 'PROJECT_SYNC_LOCKED';
    public const string PROJECT_SYNC_LOCK_FAILED = 'PROJECT_SYNC_LOCK_FAILED';
    public const string PROJECT_STATE_CHANGED = 'PROJECT_STATE_CHANGED';
    public const string PROTECTED_THIRD_PARTY_ROOT_CHANGED = 'PROTECTED_THIRD_PARTY_ROOT_CHANGED';
    public const string RECOVERY_STORAGE_FAILED = 'RECOVERY_STORAGE_FAILED';
    public const string RECOVERY_REQUIRED = 'RECOVERY_REQUIRED';

    /** @var list<string> */
    private const array REGISTRY = [
        self::APPLICATION_SET_INVALID,
        self::INSTALLATION_INTENT_INVALID,
        self::PROJECT_ROOT_INVALID,
        self::BASELINE_NOT_INSTALLED,
        self::PRESET_POLICY_INVALID,
        self::CATALOG_INVALID,
        self::CATALOG_MODULE_UNKNOWN,
        self::EXCLUDED_REQUIRED_DEPENDENCY,
        self::MODULE_GRAPH_CONFLICT,
        self::MANAGED_STATE_INVALID,
        self::MANAGED_STATE_CONFLICT,
        self::COMPOSER_MANIFEST_INVALID,
        self::COMPOSER_MANIFEST_WRITE_FAILED,
        self::COMPOSER_LOCK_STALE,
        self::COMPOSER_EXECUTION_FAILED,
        self::COMPOSER_UNSUPPORTED_POLICY,
        self::INSTALLED_METADATA_INVALID,
        self::INSTALLED_VENDOR_INCOMPLETE,
        self::INSTALLED_PLAN_MISMATCH,
        self::VERIFICATION_INPUT_INVALID,
        self::VERIFICATION_EXECUTION_FAILED,
        self::PROJECT_SYNC_LOCKED,
        self::PROJECT_SYNC_LOCK_FAILED,
        self::PROJECT_STATE_CHANGED,
        self::PROTECTED_THIRD_PARTY_ROOT_CHANGED,
        self::RECOVERY_STORAGE_FAILED,
        self::RECOVERY_REQUIRED,
    ];

    /** @var array<string, true>|null */
    private static ?array $lookup = null;

    /** @var list<string>|null */
    private static ?array $sorted = null;

    private function __construct()
    {
    }

    public static function has(string $code): bool
    {
        self::initialize();

        return isset(self::$lookup[$code]);
    }

    /** @return list<string> Sorted ascending by byte-order strcmp(). */
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
            throw new \LogicException('dependency-sync-error-codes-registry-contains-duplicates');
        }

        \usort(
            $codes,
            static fn (string $left, string $right): int => \strcmp(
                $left,
                $right,
            ),
        );

        $lookup = [];

        foreach ($codes as $code) {
            $lookup[$code] = true;
        }

        self::$sorted = $codes;
        self::$lookup = $lookup;
    }
}
