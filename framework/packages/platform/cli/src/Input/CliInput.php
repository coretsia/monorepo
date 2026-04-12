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

namespace Coretsia\Platform\Cli\Input;

use Coretsia\Contracts\Cli\Input\InputInterface;

/**
 * Phase 0 CLI input adapter: exposes raw stable tokens only.
 *
 * Semantics:
 * - tokens are taken as-is from argv (excluding script name)
 * - no parsing / normalization is performed (flags/options remain impl concern)
 *
 * @internal
 */
final readonly class CliInput implements InputInterface
{
    /** @var list<string> */
    private array $tokens;

    /**
     * @param list<string> $tokens
     */
    private function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * Factory: build input from $_SERVER['argv'] (stable for same invocation).
     */
    public static function fromArgv(?array $argv = null): self
    {
        /** @var mixed $raw */
        $raw = $argv ?? ($_SERVER['argv'] ?? []);

        if (!\is_array($raw)) {
            return new self([]);
        }

        // Ensure list<string>, excluding script name at index 0.
        $tokens = [];
        $count = \count($raw);

        for ($i = 1; $i < $count; $i++) {
            if (!\array_key_exists($i, $raw)) {
                continue;
            }

            $value = $raw[$i];

            if (!\is_string($value)) {
                // Ignore non-string tokens deterministically (no exceptions; Phase 0 safety).
                continue;
            }

            $tokens[] = $value;
        }

        return new self($tokens);
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }
}
