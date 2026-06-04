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
 * Canonical explicit application target for Kernel Bootstrap Phase A.
 *
 * The app target selects the deterministic application root under:
 *
 * skeleton/apps/<target>
 *
 * Bootstrap Phase A MUST receive the target explicitly. It MUST NOT discover
 * the target by scanning skeleton/apps/<app>.
 *
 * Invalid targets fail with BootstrapException. The exception message is safe
 * and MUST NOT include the rejected raw input.
 */
enum AppTarget: string
{
    case Web = 'web';
    case Api = 'api';
    case Console = 'console';
    case Worker = 'worker';

    /**
     * Returns canonical application target tokens.
     *
     * @return list<non-empty-string>
     */
    public static function values(): array
    {
        return [
            self::Web->value,
            self::Api->value,
            self::Console->value,
            self::Worker->value,
        ];
    }

    /**
     * Checks whether the given string is a canonical application target token.
     */
    public static function isKnown(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    /**
     * Resolves a canonical application target token.
     *
     * @throws BootstrapException when the value is not a known app target.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw BootstrapException::withReason(
                BootstrapException::REASON_INVALID_APP_TARGET,
            );
    }
}
