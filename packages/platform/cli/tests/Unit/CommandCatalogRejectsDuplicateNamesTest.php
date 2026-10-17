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

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use PHPUnit\Framework\TestCase;

final class CommandCatalogRejectsDuplicateNamesTest extends TestCase
{
    public function testDistinctServiceIdsWithTheSameCommandNameFailDeterministically(): void
    {
        $registry = new TagRegistry();
        $registry->add(
            ReservedTags::CLI_COMMAND,
            DuplicateNameFirstCommand::class,
            meta: self::metadata(
                DuplicateNameFirstCommand::class,
            ),
        );
        $registry->add(
            ReservedTags::CLI_COMMAND,
            DuplicateNameSecondCommand::class,
            meta: self::metadata(
                DuplicateNameSecondCommand::class,
            ),
        );

        self::assertCount(2, $registry->all(ReservedTags::CLI_COMMAND));

        try {
            self::catalog($registry);
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_NAME_COLLISION, $exception->reason());
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
            return;
        }

        self::fail('Two distinct tagged class ids cannot own the same command name.');
    }

    public function testRepeatedTagRegistrationUsesFoundationFirstWins(): void
    {
        $registry = new TagRegistry();
        $originalMetadata = self::metadata(DuplicateNameFirstCommand::class);
        $registry->add(ReservedTags::CLI_COMMAND, DuplicateNameFirstCommand::class, meta: $originalMetadata);
        $registry->add(
            ReservedTags::CLI_COMMAND,
            DuplicateNameFirstCommand::class,
            priority: 100,
            meta: ['unrelated' => 'ignored duplicate'],
        );

        $tagged = $registry->all(ReservedTags::CLI_COMMAND);
        self::assertCount(1, $tagged);
        self::assertSame(0, $tagged[0]->priority());
        self::assertSame($originalMetadata, $tagged[0]->meta());

        $catalog = self::catalog($registry);
        self::assertSame([DuplicateNameFirstCommand::NAME], \array_keys($catalog->descriptors()));
        self::assertSame(
            DuplicateNameFirstCommand::class,
            $catalog->require(DuplicateNameFirstCommand::NAME)->serviceId(),
        );
    }

    /** @param class-string $commandClass @return array<string, mixed> */
    private static function metadata(string $commandClass): array
    {
        return [
            'name' => $commandClass::NAME,
            'summary' => $commandClass::SUMMARY,
            'group' => $commandClass::GROUP,
            'hidden' => $commandClass::HIDDEN,
            'arguments' => $commandClass::ARGUMENTS,
            'options' => $commandClass::OPTIONS,
        ];
    }

    private static function catalog(TagRegistry $registry): CommandCatalog
    {
        return new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
    }
}

final class DuplicateNameFirstCommand implements CommandInterface
{
    public const string NAME = 'external:duplicate';
    public const string SUMMARY = 'First duplicate-name fixture.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct()
    {
        throw new \LogicException('duplicate-command-construction-forbidden');
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}

final class DuplicateNameSecondCommand implements CommandInterface
{
    public const string NAME = DuplicateNameFirstCommand::NAME;
    public const string SUMMARY = 'Second duplicate-name fixture.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct()
    {
        throw new \LogicException('duplicate-command-construction-forbidden');
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}
