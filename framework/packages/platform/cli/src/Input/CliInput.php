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
 * Phase 0 CLI input adapter.
 *
 * Semantics:
 * - raw tokens are kept stable and exclude the script name;
 * - parsed accessors are derived deterministically;
 * - Application may rebuild the input after command resolution so multi-token
 *   command names can expose the resolved command name while preserving raw
 *   tokens for compatibility.
 *
 * @internal
 */
final readonly class CliInput implements InputInterface
{
    /**
     * @param list<string> $tokens
     * @param list<string> $arguments
     * @param array<string, string|bool|list<string>|null> $options
     */
    private function __construct(
        private array $tokens,
        private string $commandName,
        private array $arguments,
        private array $options,
    ) {
    }

    /**
     * Factory: build input from $_SERVER['argv'] or explicit argv.
     *
     * The script name at index 0 is excluded from tokens().
     *
     * @param array<int, mixed>|null $argv
     */
    public static function fromArgv(?array $argv = null): self
    {
        /** @var mixed $raw */
        $raw = $argv ?? ($_SERVER['argv'] ?? []);

        if (!\is_array($raw)) {
            return new self(
                tokens: [],
                commandName: '',
                arguments: [],
                options: [],
            );
        }

        $tokens = [];
        $count = \count($raw);

        for ($i = 1; $i < $count; $i++) {
            if (!\array_key_exists($i, $raw)) {
                continue;
            }

            $value = $raw[$i];

            if (!\is_string($value)) {
                continue;
            }

            $tokens[] = $value;
        }

        $commandName = $tokens[0] ?? '';

        [$arguments, $options] = self::parseTokens(
            $commandName === '' ? $tokens : \array_slice($tokens, 1),
        );

        return new self(
            tokens: $tokens,
            commandName: $commandName,
            arguments: $arguments,
            options: $options,
        );
    }

    /**
     * Builds input after Application resolved the concrete command.
     *
     * `$consumedTokenCount` is the number of raw tokens that belong to the
     * resolved command signature.
     *
     * Example:
     * - tokens: ['workspace:sync', '--dry-run', '--format=text']
     * - commandName: 'workspace:sync --dry-run'
     * - consumedTokenCount: 2
     * - arguments: []
     * - options: ['format' => 'text']
     *
     * @param list<string> $tokens
     */
    public static function forResolvedCommand(
        array $tokens,
        string $commandName,
        int $consumedTokenCount,
    ): self {
        if ($consumedTokenCount < 0) {
            $consumedTokenCount = 0;
        }

        if ($consumedTokenCount > \count($tokens)) {
            $consumedTokenCount = \count($tokens);
        }

        [$arguments, $options] = self::parseTokens(
            \array_slice($tokens, $consumedTokenCount),
        );

        return new self(
            tokens: $tokens,
            commandName: $commandName,
            arguments: $arguments,
            options: $options,
        );
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    public function commandName(): string
    {
        return $this->commandName;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, string|bool|list<string>|null>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }

    public function option(string $name): string|bool|array|null
    {
        return $this->options[$name] ?? null;
    }

    /**
     * @param list<string> $tokens
     * @return array{0: list<string>, 1: array<string, string|bool|list<string>|null>}
     */
    private static function parseTokens(array $tokens): array
    {
        $arguments = [];
        $options = [];

        $count = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '') {
                continue;
            }

            if (!\str_starts_with($token, '-')) {
                $arguments[] = $token;

                continue;
            }

            if (!\str_starts_with($token, '--')) {
                $name = \ltrim($token, '-');

                if ($name !== '') {
                    self::addOption($options, $name, true);
                }

                continue;
            }

            $raw = \substr($token, 2);

            if ($raw === '') {
                continue;
            }

            if (\str_contains($raw, '=')) {
                [$name, $value] = \explode('=', $raw, 2);

                if ($name !== '') {
                    self::addOption($options, $name, $value);
                }

                continue;
            }

            $next = $tokens[$i + 1] ?? null;

            if (\is_string($next) && $next !== '' && !\str_starts_with($next, '-')) {
                self::addOption($options, $raw, $next);
                $i++;

                continue;
            }

            self::addOption($options, $raw, true);
        }

        return [$arguments, $options];
    }

    /**
     * @param array<string, string|bool|list<string>|null> $options
     */
    private static function addOption(
        array &$options,
        string $name,
        string|bool|null $value,
    ): void {
        if (!\array_key_exists($name, $options)) {
            $options[$name] = $value;

            return;
        }

        $current = $options[$name];

        if (\is_array($current)) {
            $current[] = self::stringifyOptionValue($value);
            $options[$name] = $current;

            return;
        }

        $options[$name] = [
            self::stringifyOptionValue($current),
            self::stringifyOptionValue($value),
        ];
    }

    private static function stringifyOptionValue(string|bool|null $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if ($value === true) {
            return 'true';
        }

        if ($value === false) {
            return 'false';
        }

        return '';
    }
}
