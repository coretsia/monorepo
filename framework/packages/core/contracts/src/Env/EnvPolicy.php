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

namespace Coretsia\Contracts\Env;

/**
 * Canonical env policy vocabulary.
 *
 * This enum describes missing-value semantics. It does not implement env
 * loading, default resolution, validation execution, or config merge behavior.
 */
enum EnvPolicy: string
{
    case Required = 'required';
    case Optional = 'optional';
    case Defaulted = 'defaulted';

    public function missingIsViolation(): bool
    {
        return $this === self::Required;
    }

    public function missingMayUseDefault(): bool
    {
        return $this === self::Defaulted;
    }

    public function missingRemainsMissing(): bool
    {
        return $this === self::Optional;
    }

    public function presentValueWins(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Required->value,
            self::Optional->value,
            self::Defaulted->value,
        ];
    }

    public static function isKnown(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
