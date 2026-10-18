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

use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;

/**
 * Descriptor-driven structural validation of parsed CLI command input.
 *
 * Receives only the selected, previously validated catalog descriptor and
 * the immutable parsed input. Command services are never resolved here;
 * owner-package and Kernel operation domain validation remain elsewhere.
 *
 * @internal
 */
final class CommandInputValidator
{
    public function validate(CommandDescriptor $descriptor, ArgvInput $input): void
    {
        $this->validateArguments($descriptor->arguments(), $input->arguments());
        $this->validateOptions($descriptor->options(), $input->options());
    }

    /**
     * @param list<array{
     *     name: string,
     *     summary: string,
     *     required: bool,
     *     variadic: bool,
     * }> $definitions
     * @param list<string> $arguments
     */
    private function validateArguments(array $definitions, array $arguments): void
    {
        $minimum = 0;
        $maximum = \count($definitions);
        $lastIndex = $maximum - 1;

        foreach ($definitions as $index => $definition) {
            if ($definition['required']) {
                ++$minimum;
            }

            if ($definition['variadic'] && $index === $lastIndex) {
                $maximum = null;
            }
        }

        $actual = \count($arguments);

        if ($actual < $minimum || ($maximum !== null && $actual > $maximum)) {
            throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_ARGUMENT_COUNT_INVALID);
        }
    }

    /**
     * @param list<array{
     *     name: string,
     *     summary: string,
     *     value: 'none'|'required'|'optional',
     *     repeatable: bool,
     * }> $definitions
     * @param array<string, string|bool|list<string>|null> $options
     */
    private function validateOptions(array $definitions, array $options): void
    {
        /** @var array<string, array{value: 'none'|'required'|'optional', repeatable: bool}> $allowed */
        $allowed = [];

        foreach ($definitions as $definition) {
            $allowed[$definition['name']] = [
                'value' => $definition['value'],
                'repeatable' => $definition['repeatable'],
            ];
        }

        foreach ($options as $name => $value) {
            if (!isset($allowed[$name])) {
                throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_UNKNOWN);
            }

            $rule = $allowed[$name];

            if (\is_array($value) && !$rule['repeatable']) {
                throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_REPEATED);
            }

            switch ($rule['value']) {
                case 'none':
                    if ($value !== true) {
                        throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_INVALID);
                    }
                    break;

                case 'required':
                    if (\is_string($value) && !$rule['repeatable']) {
                        if ($value === '') {
                            throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_INVALID);
                        }
                        break;
                    }

                    if ($rule['repeatable'] && \is_array($value) && \array_is_list($value) && $value !== []) {
                        foreach ($value as $item) {
                            if (!\is_string($item) || $item === '') {
                                throw CliInputInvalidException::withReason(
                                    CliInputInvalidException::REASON_OPTION_INVALID,
                                );
                            }
                        }
                        break;
                    }

                    throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_INVALID);

                case 'optional':
                    if ($value !== true && !\is_string($value)) {
                        throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_INVALID);
                    }
                    break;
            }
        }
    }
}
