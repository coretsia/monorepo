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
 * Immutable command-facing parsed CLI input.
 *
 * The launcher and recognized CLI-global options are stripped by
 * ArgvInputParser before this value is constructed. Positional arguments
 * after `--` retain their literal bytes and ordering.
 *
 * @phpstan-type CliOptionValue string|bool|list<string>|null
 * @phpstan-type CliOptions array<string, CliOptionValue>
 *
 * @internal
 */
final readonly class ArgvInput implements InputInterface
{
    /**
     * @param list<string> $tokens
     * @param list<string> $arguments
     * @param CliOptions $options
     */
    public function __construct(
        private array $tokens,
        private string $commandName,
        private array $arguments,
        private array $options,
    ) {
    }

    /** @return list<string> */
    public function tokens(): array
    {
        return $this->tokens;
    }

    public function commandName(): string
    {
        return $this->commandName;
    }

    /** @return list<string> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** @return CliOptions */
    public function options(): array
    {
        return $this->options;
    }

    public function hasOption(string $name): bool
    {
        return \array_key_exists(\strtolower($name), $this->options);
    }

    /** @return CliOptionValue */
    public function option(string $name): string|bool|array|null
    {
        return $this->options[\strtolower($name)] ?? null;
    }
}
