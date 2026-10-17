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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand\ExternalModeCommand;
use PHPUnit\Framework\TestCase;

final class CommandCatalogRejectsNonClassServiceIdTest extends TestCase
{
    public function testNonClassServiceIdFailsWithoutServiceResolution(): void
    {
        $registry = new TagRegistry();
        $registry->add(
            ReservedTags::CLI_COMMAND,
            'external.command.alias',
            meta: [
                'name' => ExternalModeCommand::NAME,
                'summary' => ExternalModeCommand::SUMMARY,
                'group' => ExternalModeCommand::GROUP,
                'hidden' => ExternalModeCommand::HIDDEN,
                'arguments' => ExternalModeCommand::ARGUMENTS,
                'options' => ExternalModeCommand::OPTIONS,
            ],
        );

        self::assertSame('external.command.alias', $registry->all(ReservedTags::CLI_COMMAND)[0]->id());

        try {
            new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_SERVICE_INVALID, $exception->reason());
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
            return;
        }

        self::fail('A non-class service id must not become a command descriptor.');
    }
}
