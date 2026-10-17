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
use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Command\HelpCommand;
use Coretsia\Platform\Cli\Command\ListCommand;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandCatalogRejectsReservedExternalNamesTest extends TestCase
{
    #[DataProvider('externalReservedNames')]
    public function testExternalServiceCannotOwnAReservedName(string $serviceId): void
    {
        $registry = new TagRegistry();
        $registry->add(ReservedTags::CLI_COMMAND, $serviceId, meta: self::metadata($serviceId));

        try {
            self::catalog($registry);
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_RESERVED_NAME, $exception->reason());
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
            return;
        }

        self::fail('An external class must not own a reserved built-in command name.');
    }

    /** @return iterable<string, array{class-string}> */
    public static function externalReservedNames(): iterable
    {
        yield 'doctor' => [ExternalDoctorIdentity::class];
        yield 'help' => [ExternalHelpIdentity::class];
        yield 'list' => [ExternalListIdentity::class];
    }

    public function testExactlyCanonicalBuiltinIdsAreAccepted(): void
    {
        $registry = new TagRegistry();
        foreach ([ListCommand::class, DoctorCommand::class, HelpCommand::class] as $class) {
            $registry->add(ReservedTags::CLI_COMMAND, $class, meta: self::metadata($class));
        }

        $catalog = self::catalog($registry);
        self::assertSame(['doctor', 'help', 'list'], \array_keys($catalog->descriptors()));
        self::assertSame(DoctorCommand::class, $catalog->require('doctor')->serviceId());
        self::assertSame(HelpCommand::class, $catalog->require('help')->serviceId());
        self::assertSame(ListCommand::class, $catalog->require('list')->serviceId());
    }

    public function testNonCanonicalReservedServiceIdMapIsRejected(): void
    {
        $registry = new TagRegistry();
        foreach (
            [
                ['doctor' => ExternalDoctorIdentity::class],
                ['help' => ExternalHelpIdentity::class],
                ['list' => ExternalListIdentity::class],
                ['unknown' => DoctorCommand::class],
            ] as $invalidMap
        ) {
            try {
                new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], $invalidMap);
            } catch (InvalidCommandTagMetaException $exception) {
                self::assertSame(InvalidCommandTagMetaException::REASON_RESERVED_NAME, $exception->reason());
                continue;
            }
            self::fail('The reserved built-in identity map must be immutable and canonical.');
        }
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

    private static function catalog(TagRegistry $registry): CommandCatalog
    {
        return new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], [
            'doctor' => DoctorCommand::class,
            'help' => HelpCommand::class,
            'list' => ListCommand::class,
        ]);
    }
}

abstract class ExternalReservedIdentity implements CommandInterface
{
    public const string SUMMARY = 'External reserved identity fixture.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct()
    {
        throw new \LogicException('reserved-command-construction-forbidden');
    }

    public function name(): string
    {
        return static::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}

final class ExternalDoctorIdentity extends ExternalReservedIdentity
{
    public const string NAME = 'doctor';
}

final class ExternalHelpIdentity extends ExternalReservedIdentity
{
    public const string NAME = 'help';
}

final class ExternalListIdentity extends ExternalReservedIdentity
{
    public const string NAME = 'list';
}
