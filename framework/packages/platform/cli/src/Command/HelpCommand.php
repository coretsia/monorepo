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
use Coretsia\Platform\Cli\Error\ErrorCodes;

/**
 * Built-in help command (Phase 0).
 *
 * Semantics (Phase 0):
 * - `help` (no args): prints generic usage + available commands list
 * - `help <command>`: prints a small built-in help for `help`/`list`,
 *   otherwise prints a generic "no detailed help" message for known commands.
 *
 * NOTE:
 * - This command is intentionally kernel-free and deterministic.
 * - It must not print directly (uses OutputInterface only).
 *
 * @internal
 */
final class HelpCommand implements CommandInterface
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
        return 'help';
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $tokens = $input->tokens();

        // Be tolerant to whether Application passes full tokens or already-trimmed tokens.
        if (isset($tokens[0]) && $tokens[0] === $this->name()) {
            \array_shift($tokens);
        }

        $subject = $tokens[0] ?? null;
        if ($subject === null || $subject === '') {
            $this->renderGeneralHelp($output);
            return 0;
        }

        if ($subject === 'help') {
            $this->renderHelpHelp($output);
            return 0;
        }

        if ($subject === 'list') {
            $this->renderListHelp($output);
            return 0;
        }

        if ($this->isKnownCommand($subject)) {
            $this->renderGenericCommandHelp($output, $subject);
            return 0;
        }

        // Deterministic domain-ish failure (command name not found).
        $output->error(ErrorCodes::CORETSIA_CLI_COMMAND_INVALID, 'unknown-command');

        return 1;
    }

    private function renderGeneralHelp(OutputInterface $output): void
    {
        $output->text('coretsia (Phase 0)');
        $output->text('');
        $output->text('Usage:');
        $output->text('  php coretsia <command> [args...]');
        $output->text('  php coretsia help [command]');
        $output->text('  php coretsia list');
        $output->text('');
        $output->text('Commands:');

        foreach ($this->availableCommands() as $name) {
            $output->text('  ' . $name);
        }

        $output->text('');
        $output->text('Notes:');
        $output->text('  - Phase 0 CLI is kernel-free (no container/autowiring).');
        $output->text('  - Commands must use OutputInterface (no direct stdout/stderr).');
    }

    private function renderHelpHelp(OutputInterface $output): void
    {
        $output->text('help');
        $output->text('');
        $output->text('Usage:');
        $output->text('  php coretsia help');
        $output->text('  php coretsia help <command>');
        $output->text('');
        $output->text('Description:');
        $output->text('  Show general usage, or help for a specific command.');
    }

    private function renderListHelp(OutputInterface $output): void
    {
        $output->text('list');
        $output->text('');
        $output->text('Usage:');
        $output->text('  php coretsia list');
        $output->text('');
        $output->text('Description:');
        $output->text('  List available commands.');
    }

    private function renderGenericCommandHelp(OutputInterface $output, string $commandName): void
    {
        $output->text($commandName);
        $output->text('');
        $output->text('Description:');
        $output->text('  Detailed help is not available in Phase 0.');
        $output->text('  Use `php coretsia list` to see available commands.');
    }

    private function isKnownCommand(string $name): bool
    {
        if ($name === 'help' || $name === 'list') {
            return true;
        }

        foreach ($this->extraCommands as $cmd) {
            if ($cmd === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function availableCommands(): array
    {
        // Built-ins first, then injected (already normalized & de-duplicated).
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

            // Built-ins are implicitly present; do not repeat.
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
