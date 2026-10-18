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

namespace Coretsia\Platform\Cli\Catalog;

use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;

/**
 * Stateless validated catalog-view overlay for CLI command descriptors.
 *
 * Only summary, hidden, and group can be replaced. Base descriptors, command
 * identity, arguments, options, and original tag metadata remain unchanged.
 *
 * @internal
 */
final class CommandOverrides
{
    /**
     * @param array<string, CommandDescriptor> $baseDescriptors
     * @param array<array-key, mixed> $overrides
     * @return array<string, CommandDescriptor>
     */
    public function apply(array $baseDescriptors, array $overrides): array
    {
        if ($overrides !== [] && \array_is_list($overrides)) {
            throw self::invalid();
        }

        $descriptors = $baseDescriptors;

        foreach ($overrides as $name => $fields) {
            if (
                !\is_string($name)
                || !CommandTagSchema::isValidName($name)
                || !isset($descriptors[$name])
                || !\is_array($fields)
                || ($fields !== [] && \array_is_list($fields))
            ) {
                throw self::invalid();
            }

            foreach ($fields as $field => $value) {
                if (!\in_array($field, ['summary', 'hidden', 'group'], true)) {
                    throw self::invalid();
                }

                if (
                    ($field === 'summary' && !CommandTagSchema::isSafeSummary($value))
                    || ($field === 'hidden' && !\is_bool($value))
                    || ($field === 'group' && (!\is_string($value) || !CommandTagSchema::isValidGroup($value)))
                ) {
                    throw self::invalid();
                }
            }

            $base = $descriptors[$name];
            $descriptors[$name] = $base->withCatalogView(
                summary: $fields['summary'] ?? $base->summary(),
                group: $fields['group'] ?? $base->group(),
                hidden: $fields['hidden'] ?? $base->hidden(),
            );
        }

        return $descriptors;
    }

    private static function invalid(): CliConfigInvalidException
    {
        return CliConfigInvalidException::withReason(
            CliConfigInvalidException::REASON_COMMAND_OVERRIDES_INVALID,
        );
    }
}
