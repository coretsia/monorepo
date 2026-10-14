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

use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;

/**
 * Frozen catalog built once from canonical CLI tag discovery output.
 *
 * TagRegistry owns ordering and first-wins registration semantics. This class
 * never resolves command services or sorts/re-deduplicates tagged services.
 *
 * @internal
 */
final readonly class CommandCatalog
{
    /**
     * @var array<string, string>
     */
    private const array BUILT_IN_SERVICE_IDS = [
        'doctor' => 'Coretsia\\Platform\\Cli\\Command\\DoctorCommand',
        'help' => 'Coretsia\\Platform\\Cli\\Command\\HelpCommand',
        'list' => 'Coretsia\\Platform\\Cli\\Command\\ListCommand',
    ];

    /**
     * @var array<string, CommandDescriptor>
     */
    private array $descriptors;

    /**
     * @param array<array-key, mixed> $overrides Validated cli.commands.overrides map
     * @param array<string, string> $reservedBuiltInServiceIds Reserved command name => exact built-in class service id
     */
    public function __construct(
        TagRegistry $registry,
        CommandTagSchema $schema,
        CommandOverrides $overlay,
        array $overrides,
        array $reservedBuiltInServiceIds,
    ) {
        $reserved = [];

        foreach ($reservedBuiltInServiceIds as $name => $serviceId) {
            if (
                !\is_string($name)
                || !\in_array($name, ['doctor', 'help', 'list'], true)
                || !\is_string($serviceId)
                || $serviceId === ''
                || self::BUILT_IN_SERVICE_IDS[$name] !== $serviceId
            ) {
                throw InvalidCommandTagMetaException::withReason(
                    InvalidCommandTagMetaException::REASON_RESERVED_NAME,
                );
            }

            $reserved[$name] = $serviceId;
        }

        /** @var array<string, CommandDescriptor> $base */
        $base = [];

        foreach ($registry->all(ReservedTags::CLI_COMMAND) as $tagged) {
            if ($tagged->priority() !== 0) {
                throw InvalidCommandTagMetaException::withReason(
                    InvalidCommandTagMetaException::REASON_PRIORITY_INVALID,
                );
            }

            $descriptor = $schema->validate($tagged);
            $name = $descriptor->name();

            if (isset($base[$name])) {
                throw InvalidCommandTagMetaException::withReason(
                    InvalidCommandTagMetaException::REASON_NAME_COLLISION,
                );
            }

            if (
                \in_array($name, ['doctor', 'help', 'list'], true)
                && ($reserved[$name] ?? null) !== $descriptor->serviceId()
            ) {
                throw InvalidCommandTagMetaException::withReason(
                    InvalidCommandTagMetaException::REASON_RESERVED_NAME,
                );
            }

            $base[$name] = $descriptor;
        }

        $this->descriptors = $overlay->apply($base, $overrides);
    }

    /**
     * @return array<string, CommandDescriptor>
     */
    public function descriptors(): array
    {
        return $this->descriptors;
    }

    public function require(string $name): CommandDescriptor
    {
        return $this->descriptors[$name] ?? throw CliInputInvalidException::withReason(
            CliInputInvalidException::REASON_COMMAND_UNKNOWN,
        );
    }
}
