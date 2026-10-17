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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandCatalogRejectsMetadataConstantMismatchTest extends TestCase
{
    #[DataProvider('mismatchedMetadata')]
    public function testRejectsValidButMismatchedClassMetadata(string $field, mixed $replacement): void
    {
        $metadata = [
            'name' => ExternalModeCommand::NAME,
            'summary' => ExternalModeCommand::SUMMARY,
            'group' => ExternalModeCommand::GROUP,
            'hidden' => ExternalModeCommand::HIDDEN,
            'arguments' => ExternalModeCommand::ARGUMENTS,
            'options' => ExternalModeCommand::OPTIONS,
        ];
        $metadata[$field] = $replacement;

        $registry = new TagRegistry();
        $registry->add(ReservedTags::CLI_COMMAND, ExternalModeCommand::class, meta: $metadata);

        try {
            new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH, $exception->reason());
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
            return;
        }

        self::fail('Tagged metadata must match static command constants before service instantiation.');
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function mismatchedMetadata(): iterable
    {
        yield 'name' => ['name', 'external:changed'];
        yield 'summary' => ['summary', 'An alternative safe summary.'];
        yield 'group' => ['group', 'other'];
        yield 'hidden' => ['hidden', true];
        yield 'arguments' => [
            'arguments',
            [
                ['name' => 'source', 'summary' => 'Source argument.', 'required' => true, 'variadic' => false],
            ],
        ];
        yield 'options' => ['options', []];
    }
}
