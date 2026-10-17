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

use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandTagSchemaValidatesNameRegexTest extends TestCase
{
    #[DataProvider('validNames')]
    public function testCanonicalNamePatternAcceptsValidNames(string $name): void
    {
        self::assertTrue(CommandTagSchema::isValidName($name));
    }

    /** @return iterable<string, array{string}> */
    public static function validNames(): iterable
    {
        yield 'single-token' => ['build'];
        yield 'colon-separated' => ['worker:start'];
        yield 'multiple-colons' => ['cache:verify:current'];
        yield 'hyphenated' => ['project-sync:dry-run'];
        yield 'digits-after-letter' => ['v2:config3'];
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNamesHardFailWithSchemaReason(string $name): void
    {
        self::assertFalse(CommandTagSchema::isValidName($name));

        $metadata = [
            'name' => $name,
            'summary' => DoctorCommand::SUMMARY,
            'group' => DoctorCommand::GROUP,
            'hidden' => DoctorCommand::HIDDEN,
            'arguments' => DoctorCommand::ARGUMENTS,
            'options' => DoctorCommand::OPTIONS,
        ];

        try {
            new CommandTagSchema()->validate(new TaggedService(DoctorCommand::class, meta: $metadata));
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID, $exception->reason());
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());

            return;
        }

        self::fail('Invalid command name must not produce a descriptor.');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Worker:Start'];
        yield 'leading-digit' => ['9worker'];
        yield 'leading-hyphen' => ['-worker'];
        yield 'trailing-colon' => ['worker:'];
        yield 'double-colon' => ['worker::start'];
        yield 'segment-leading-digit' => ['worker:2start'];
        yield 'underscores' => ['worker_start'];
        yield 'space' => ['worker start'];
        yield 'newline' => ["worker\nstart"];
        yield 'nul' => ["worker\0start"];
        yield 'dot' => ['worker.start'];
        yield 'slash' => ['worker/start'];
    }
}
