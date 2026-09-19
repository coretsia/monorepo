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

namespace Coretsia\Kernel\Boot;

use Coretsia\Kernel\Boot\Exception\BootstrapException;

/**
 * Kernel-owned Bootstrap Phase A env source precedence policy.
 *
 * This enum controls precedence between parsed dotenv values and system/process
 * environment values while building the bootstrap env snapshot.
 *
 * It is intentionally separate from:
 *
 * Coretsia\Contracts\Env\EnvPolicy
 *
 * EnvPolicy is a contracts-level missing-value policy only:
 *
 * - required
 * - optional
 * - defaulted
 *
 * BootstrapEnvSourcePolicy is a Kernel bootstrap source precedence policy only:
 *
 * - strict_dotenv
 * - allow_system
 */
enum BootstrapEnvSourcePolicy: string
{
    case StrictDotenv = 'strict_dotenv';
    case AllowSystem = 'allow_system';

    /**
     * Returns whether parsed dotenv values must win over system env values.
     */
    public function dotenvWins(): bool
    {
        return $this === self::StrictDotenv;
    }

    /**
     * Returns whether system env values may override parsed dotenv values.
     */
    public function systemEnvMayOverrideDotenv(): bool
    {
        return $this === self::AllowSystem;
    }

    /**
     * Returns canonical Bootstrap Phase A env source precedence tokens.
     *
     * @return list<non-empty-string>
     */
    public static function values(): array
    {
        return [
            self::StrictDotenv->value,
            self::AllowSystem->value,
        ];
    }

    /**
     * Checks whether the given string is a canonical Bootstrap Phase A env
     * source precedence token.
     */
    public static function isKnown(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    /**
     * Resolves a canonical Bootstrap Phase A env source precedence token.
     *
     * @throws BootstrapException when the value is not a known policy token.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw BootstrapException::withReason(
                BootstrapException::REASON_ENV_SOURCE_POLICY_INVALID,
            );
    }
}
