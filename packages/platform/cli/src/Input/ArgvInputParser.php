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

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;

/**
 * Deterministic CLI argument parser and global-option separator.
 *
 * Only syntax is parsed here. Command-specific argument and option rules
 * belong to CommandInputValidator; global format/color semantics belong to
 * the presentation resolvers. No environment or terminal state is read.
 *
 * @internal
 */
final class ArgvInputParser
{
    private const string OPTION_NAME_PATTERN = '~\A[a-z][a-z0-9-]*\z~D';

    /**
     * @param array<array-key, mixed> $argv
     */
    public function parse(array $argv): ParsedCliInvocation
    {
        if (!\array_is_list($argv) || $argv === []) {
            throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_INVALID);
        }

        foreach ($argv as $token) {
            if (!\is_string($token)) {
                throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_INVALID);
            }
        }

        /** @var list<string> $argv */

        /** @var list<string> $commandTokens */
        $commandTokens = [];
        /** @var list<string> $arguments */
        $arguments = [];
        /** @var array<string, string|bool|list<string>|null> $options */
        $options = [];

        $commandName = null;
        $formatOverride = null;
        $colorOverride = null;
        $formatSeen = false;
        $colorSeen = false;
        $endOfOptions = false;

        foreach (\array_slice($argv, 1) as $token) {
            if ($endOfOptions) {
                $commandTokens[] = $token;
                $arguments[] = $token;
                continue;
            }

            if ($token === '--') {
                if ($commandName === null) {
                    throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_COMMAND_MISSING);
                }

                $commandTokens[] = $token;
                $endOfOptions = true;
                continue;
            }

            if (\str_starts_with($token, '--')) {
                $separator = \strpos($token, '=');
                $rawName = $separator === false
                    ? \substr($token, 2)
                    : \substr($token, 2, $separator - 2);
                $name = \strtolower($rawName);

                if (\preg_match(self::OPTION_NAME_PATTERN, $name) !== 1) {
                    throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_INVALID);
                }

                $hasValue = $separator !== false;
                $value = $hasValue ? \substr($token, $separator + 1) : null;

                if ($name === 'format' || $name === 'color') {
                    if (!$hasValue || $value === '') {
                        throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_INVALID);
                    }

                    if ($name === 'format') {
                        if ($formatSeen) {
                            throw CliInputInvalidException::withReason(
                                CliInputInvalidException::REASON_OPTION_REPEATED,
                            );
                        }

                        $formatOverride = $value;
                        $formatSeen = true;
                    } else {
                        if ($colorSeen) {
                            throw CliInputInvalidException::withReason(
                                CliInputInvalidException::REASON_OPTION_REPEATED,
                            );
                        }

                        $colorOverride = $value;
                        $colorSeen = true;
                    }

                    continue;
                }

                if ($commandName === null) {
                    throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_COMMAND_MISSING);
                }

                $commandTokens[] = $token;

                if (!\array_key_exists($name, $options)) {
                    $options[$name] = $hasValue ? $value : true;
                    continue;
                }

                if (!\is_string($value) || (!\is_string($options[$name]) && !\is_array($options[$name]))) {
                    throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_REPEATED);
                }

                if (\is_string($options[$name])) {
                    $options[$name] = [$options[$name], $value];
                } else {
                    $options[$name][] = $value;
                }

                continue;
            }

            if ($commandName === null) {
                if (\preg_match('~' . CommandInterface::COMMAND_NAME_PATTERN . '~D', $token) !== 1) {
                    throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_COMMAND_INVALID);
                }

                $commandName = $token;
            } else {
                $arguments[] = $token;
            }

            $commandTokens[] = $token;
        }

        if ($commandName === null) {
            throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_COMMAND_MISSING);
        }

        return new ParsedCliInvocation(
            commandInput: new ArgvInput(
                tokens: $commandTokens,
                commandName: $commandName,
                arguments: $arguments,
                options: $options,
            ),
            formatOverride: $formatOverride,
            colorOverride: $colorOverride,
        );
    }
}
