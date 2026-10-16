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
use Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand\ExternalModeCommand;
use PHPUnit\Framework\TestCase;

final class CommandCatalogDeterminismTest extends TestCase
{
    public function testSameTagInputProducesIdenticalOrderedDescriptorsAndStableLookups(): void
    {
        $overrides = [
            'list' => ['summary' => 'Canonical list override.', 'hidden' => true, 'group' => 'custom-group'],
            'external:mode' => ['summary' => 'External summary override.', 'hidden' => true],
        ];
        $firstRegistry = self::registry();
        $secondRegistry = self::registry();
        $first = self::catalog($firstRegistry, $overrides);
        $second = self::catalog($secondRegistry, $overrides);

        $expectedIds = \array_map(
            static fn ($tagged): string => $tagged->id(),
            $firstRegistry->all(ReservedTags::CLI_COMMAND),
        );
        self::assertSame([ListCommand::class, ExternalModeCommand::class], $expectedIds);
        self::assertSame(['list', 'external:mode'], \array_keys($first->descriptors()));
        self::assertSame(
            $expectedIds,
            \array_map(
                static fn ($descriptor): string => $descriptor->serviceId(),
                \array_values($first->descriptors()),
            ),
        );
        self::assertSame(self::snapshot($first), self::snapshot($second));

        $list = $first->require('list');
        self::assertSame('Canonical list override.', $list->summary());
        self::assertSame('custom-group', $list->group());
        self::assertTrue($list->hidden());
        self::assertSame(ListCommand::ARGUMENTS, $list->arguments());
        self::assertSame(ListCommand::OPTIONS, $list->options());
        self::assertSame(ListCommand::NAME, $list->name());
        self::assertSame(ListCommand::class, $list->serviceId());

        $external = $first->require('external:mode');
        self::assertSame('External summary override.', $external->summary());
        self::assertSame(ExternalModeCommand::GROUP, $external->group());
        self::assertTrue($external->hidden());
        self::assertSame(
            CommandTagSchema::normalize(['options' => ExternalModeCommand::OPTIONS])['options'],
            $external->options(),
        );

        $snapshot = self::snapshot($first);
        for ($iteration = 0; $iteration < 3; ++$iteration) {
            self::assertSame($list, $first->require('list'));
            self::assertSame($external, $first->require('external:mode'));
            self::assertSame($snapshot, self::snapshot($first));
        }
        self::assertSame(ListCommand::SUMMARY, self::metadata(ListCommand::class)['summary']);
        self::assertSame(ExternalModeCommand::SUMMARY, self::metadata(ExternalModeCommand::class)['summary']);
    }

    public function testRepeatedRegistrationUsesFoundationFirstWinsBeforeCatalogConstruction(): void
    {
        $registry = self::registry();
        $registry->add(
            ReservedTags::CLI_COMMAND,
            ListCommand::class,
            priority: 100,
            meta: ['unknown' => 'must not replace original metadata'],
        );
        $tagged = $registry->all(ReservedTags::CLI_COMMAND);
        self::assertCount(2, $tagged);
        self::assertSame(0, $tagged[0]->priority());
        self::assertSame(self::metadata(ListCommand::class), $tagged[0]->meta());
        self::assertSame(['list', 'external:mode'], \array_keys(self::catalog($registry)->descriptors()));
    }

    public function testCatalogAppliesCommandOverridesExactlyOnceDuringConstruction(): void
    {
        $constructor = new \ReflectionMethod(CommandCatalog::class, '__construct');
        $sourcePath = $constructor->getFileName();
        self::assertTrue(\is_string($sourcePath));

        $sourceLines = \file($sourcePath);
        self::assertTrue(\is_array($sourceLines));

        $constructorSource = \implode(
            '',
            \array_slice(
                $sourceLines,
                $constructor->getStartLine() - 1,
                $constructor->getEndLine() - $constructor->getStartLine() + 1,
            ),
        );

        self::assertSame(
            1,
            \preg_match_all('/\$overlay\s*->\s*apply\s*\(/', $constructorSource),
        );
    }

    private static function registry(): TagRegistry
    {
        $registry = new TagRegistry();
        // Reverse registration order: TagRegistry owns class-ID ordering; command-name sorting differs.
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

        return $registry;
    }

    /** @param class-string $class @return array<string, mixed> */
    private static function metadata(string $class): array
    {
        return [
            'name' => $class::NAME,
            'summary' => $class::SUMMARY,
            'group' => $class::GROUP,
            'hidden' => $class::HIDDEN,
            'arguments' => $class::ARGUMENTS,
            'options' => $class::OPTIONS,
        ];
    }

    /** @param array<string, mixed> $overrides */
    private static function catalog(TagRegistry $registry, array $overrides = []): CommandCatalog
    {
        return new CommandCatalog(
            $registry,
            new CommandTagSchema(),
            new CommandOverrides(),
            $overrides,
            ['list' => ListCommand::class],
        );
    }

    /** @return list<array<string, mixed>> */
    private static function snapshot(CommandCatalog $catalog): array
    {
        $result = [];
        foreach ($catalog->descriptors() as $descriptor) {
            $result[] = [
                'id' => $descriptor->serviceId(),
                'name' => $descriptor->name(),
                'summary' => $descriptor->summary(),
                'group' => $descriptor->group(),
                'hidden' => $descriptor->hidden(),
                'arguments' => $descriptor->arguments(),
                'options' => $descriptor->options(),
            ];
        }

        return $result;
    }
}
