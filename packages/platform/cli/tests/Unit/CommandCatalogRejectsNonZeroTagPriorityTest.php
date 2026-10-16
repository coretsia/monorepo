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
use Coretsia\Platform\Cli\Command\ListCommand;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand\ExternalModeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandCatalogRejectsNonZeroTagPriorityTest extends TestCase
{
    #[DataProvider('nonZeroPriorities')]
    public function testRejectsActualTagPriorityEvenWithoutPriorityMetadataKey(int $priority): void
    {
        $registry = new TagRegistry();
        $registry->add(
            ReservedTags::CLI_COMMAND,
            ExternalModeCommand::class,
            priority: $priority,
            meta: self::metadata(ExternalModeCommand::class),
        );

        self::assertSame($priority, $registry->all(ReservedTags::CLI_COMMAND)[0]->priority());
        self::assertArrayNotHasKey('priority', $registry->all(ReservedTags::CLI_COMMAND)[0]->meta());

        try {
            self::catalog($registry);
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_PRIORITY_INVALID, $exception->reason());
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
            self::assertSame($priority, $registry->all(ReservedTags::CLI_COMMAND)[0]->priority());
            return;
        }

        self::fail('Non-zero actual tag priority must fail catalog construction.');
    }

    /** @return iterable<string, array{int}> */
    public static function nonZeroPriorities(): iterable
    {
        yield 'positive' => [50];
        yield 'negative' => [-10];
    }

    public function testZeroPriorityPreservesCanonicalRegistryOrderWithoutResolvingCommands(): void
    {
        $registry = new TagRegistry();
        $registry->add(
            ReservedTags::CLI_COMMAND,
            ExternalModeCommand::class,
            meta: self::metadata(ExternalModeCommand::class),
        );
        $registry->add(
            ReservedTags::CLI_COMMAND,
            ListCommand::class,
            meta: self::metadata(ListCommand::class),
        );

        $tagged = $registry->all(ReservedTags::CLI_COMMAND);
        $expectedIds = \array_map(static fn ($entry): string => $entry->id(), $tagged);
        $catalog = self::catalog($registry);

        self::assertSame([ListCommand::class, ExternalModeCommand::class], $expectedIds);
        self::assertSame(
            [ListCommand::NAME, ExternalModeCommand::NAME],
            \array_keys($catalog->descriptors()),
        );
        self::assertSame(
            $expectedIds,
            \array_map(
                static fn ($descriptor): string => $descriptor->serviceId(),
                \array_values($catalog->descriptors()),
            ),
        );
        self::assertSame([0, 0], \array_map(static fn ($entry): int => $entry->priority(), $tagged));
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
        return new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], [
            'list' => ListCommand::class,
        ]);
    }
}
