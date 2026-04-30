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
 * Contracts-level env lookup result.
 *
 * Missing and present-empty-string are distinct states. An empty string is a
 * present env value.
 */
final readonly class EnvValue
{
    private bool $present;

    private ?string $value;

    private function __construct(bool $present, ?string $value)
    {
        if (!$present && $value !== null) {
            throw new \InvalidArgumentException('Missing env value must not contain a raw value.');
        }

        if ($present && $value === null) {
            throw new \InvalidArgumentException('Present env value must contain a string value.');
        }

        $this->present = $present;
        $this->value = $value;
    }

    public static function missing(): self
    {
        return new self(false, null);
    }

    public static function present(string $value): self
    {
        return new self(true, $value);
    }

    public function isPresent(): bool
    {
        return $this->present;
    }

    public function isMissing(): bool
    {
        return !$this->present;
    }

    public function isEmptyString(): bool
    {
        return $this->present && $this->value === '';
    }

    public function value(): string
    {
        if (!$this->present) {
            throw new \LogicException('Missing env value has no string value.');
        }

        return $this->value;
    }
}
