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

use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;

/**
 * Immutable CLI catalog view of one validated tagged command.
 *
 * Built only by CommandTagSchema from canonical static tag metadata. Catalog
 * presentation overrides create a new instance without changing command
 * identity, argument/option definitions, or the original tag registration.
 *
 * @phpstan-type CliArgument array{
 *     name: string,
 *     summary: string,
 *     required: bool,
 *     variadic: bool,
 * }
 * @phpstan-type CliOption array{
 *     name: string,
 *     summary: string,
 *     value: 'none'|'required'|'optional',
 *     repeatable: bool,
 * }
 *
 * @internal
 */
final readonly class CommandDescriptor
{
    /**
     * @param list<CliArgument> $arguments
     * @param list<CliOption> $options
     */
    private function __construct(
        private string $serviceId,
        private string $name,
        private string $summary,
        private string $group,
        private bool $hidden,
        private array $arguments,
        private array $options,
        private string $executionKind,
    ) {
    }

    /**
     * Creates a descriptor from metadata already verified by CommandTagSchema.
     *
     * @param array{
     *     name: string,
     *     summary: string,
     *     group: string,
     *     hidden: bool,
     *     arguments: list<CliArgument>,
     *     options: list<CliOption>,
     * } $metadata
     */
    public static function fromValidatedMetadata(string $serviceId, array $metadata): self
    {
        try {
            $tagged = new TaggedService(id: $serviceId, meta: $metadata);
        } catch (\Throwable) {
            throw InvalidCommandTagMetaException::withReason(
                InvalidCommandTagMetaException::REASON_SCHEMA_INVALID,
            );
        }

        $metadata = new CommandTagSchema()->canonicalMetadata($tagged);

        return new self(
            serviceId: $serviceId,
            name: $metadata['name'],
            summary: $metadata['summary'],
            group: $metadata['group'],
            hidden: $metadata['hidden'],
            arguments: $metadata['arguments'],
            options: $metadata['options'],
            executionKind: CommandTagSchema::isCompositeServiceId($serviceId) ? 'composite' : 'normal',
        );
    }

    /**
     * Creates an immutable catalog presentation overlay.
     *
     * All supplied values must have been validated by CommandOverrides.
     */
    public function withCatalogView(string $summary, string $group, bool $hidden): self
    {
        if (!CommandTagSchema::isSafeSummary($summary) || !CommandTagSchema::isValidGroup($group)) {
            throw CliConfigInvalidException::withReason(
                CliConfigInvalidException::REASON_COMMAND_OVERRIDES_INVALID,
            );
        }

        return new self(
            serviceId: $this->serviceId,
            name: $this->name,
            summary: $summary,
            group: $group,
            hidden: $hidden,
            arguments: $this->arguments,
            options: $this->options,
            executionKind: $this->executionKind,
        );
    }

    /** @return 'normal'|'composite' */
    public function executionKind(): string
    {
        return $this->executionKind;
    }

    public function serviceId(): string
    {
        return $this->serviceId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function hidden(): bool
    {
        return $this->hidden;
    }

    /** @return list<CliArgument> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** @return list<CliOption> */
    public function options(): array
    {
        return $this->options;
    }
}
