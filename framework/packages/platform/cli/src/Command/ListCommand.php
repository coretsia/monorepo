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

namespace Coretsia\Platform\Cli\Command;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

/**
 * Built-in list command (Phase 0).
 *
 * Deterministic behavior:
 * - prints the list of available commands (built-ins + injected registry commands)
 * - ignores extra tokens (Phase 0: parsing semantics are Application concern)
 *
 * @internal
 */
final class ListCommand implements CommandInterface
{
    /** @var list<string> */
    private array $extraCommands;

    /**
     * @param list<string> $extraCommands Command names (already known by Application).
     *                                   Built-ins `help` and `list` are always included implicitly.
     */
    public function __construct(array $extraCommands = [])
    {
        $this->extraCommands = self::normalizeCommandNames($extraCommands);
    }

    public function name(): string
    {
        return 'list';
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        // Input tokens are intentionally ignored (Phase 0).
        $output->text('Available commands:');

        foreach ($this->availableCommands() as $name) {
            $output->text('  ' . $name);
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function availableCommands(): array
    {
        return \array_merge(['help', 'list'], $this->extraCommands);
    }

    /**
     * @param list<string> $names
     * @return list<string> append-unique, preserving first occurrence order, excluding built-ins
     */
    private static function normalizeCommandNames(array $names): array
    {
        $out = [];
        $seen = [];

        foreach ($names as $name) {
            if (!\is_string($name) || $name === '') {
                continue;
            }

            if ($name === 'help' || $name === 'list') {
                continue;
            }

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $out[] = $name;
        }

        return $out;
    }
}
