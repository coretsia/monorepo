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
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandTagSchemaTest extends TestCase
{
    public function testAcceptsExactSixKeyMetadataWithArgumentsAndOptions(): void
    {
        $metadata = self::metadata();
        $descriptor = new CommandTagSchema()->validate(
            new TaggedService(
                id: CommandTagSchemaRichFixture::class,
                meta: $metadata,
            ),
        );

        self::assertSame(CommandTagSchemaRichFixture::class, $descriptor->serviceId());
        self::assertSame(CommandTagSchemaRichFixture::NAME, $descriptor->name());
        self::assertSame(CommandTagSchemaRichFixture::SUMMARY, $descriptor->summary());
        self::assertSame('owner-tools', $descriptor->group());
        self::assertFalse($descriptor->hidden());
        self::assertSame(
            CommandTagSchema::normalize(['arguments' => CommandTagSchemaRichFixture::ARGUMENTS])['arguments'],
            $descriptor->arguments(),
        );
        self::assertSame(
            CommandTagSchema::normalize(['options' => CommandTagSchemaRichFixture::OPTIONS])['options'],
            $descriptor->options(),
        );
        self::assertSame(
            ['name', 'summary', 'group', 'hidden', 'arguments', 'options'],
            \array_keys($metadata),
        );
        self::assertSame(['mode', 'preset', 'item', 'verbose'], \array_column($descriptor->options(), 'name'));
        self::assertSame(['required', 'optional', 'required', 'none'], \array_column($descriptor->options(), 'value'));
    }

    /** @param mixed $value */
    #[DataProvider('invalidGroupProvider')]
    public function testRejectsInvalidGroupTokens(mixed $value): void
    {
        $metadata = self::metadata();
        $metadata['group'] = $value;
        self::assertInvalid($metadata);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidGroupProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Owner'];
        yield 'whitespace' => ['owner tools'];
        yield 'newline' => ["owner\ntools"];
        yield 'tab' => ["owner\ttools"];
        yield 'nul' => ["owner\0tools"];
        yield 'colon' => ['owner:tools'];
        yield 'leading-hyphen' => ['-owner'];
        yield 'non-string' => [false];
    }

    /** @param mixed $value */
    #[DataProvider('invalidSummaryProvider')]
    public function testRejectsInvalidSummaryAndHiddenFields(mixed $value): void
    {
        $metadata = self::metadata();
        $metadata['summary'] = $value;
        self::assertInvalid($metadata);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidSummaryProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace-only' => ['   '];
        yield 'control-character' => ["Unsafe\nsummary"];
        yield 'absolute-path' => ['/private/run/worker.sock'];
        yield 'runtime-url' => ['https://example.invalid/endpoint'];
        yield 'environment-expression' => ['${APP_SECRET}'];
        yield 'source-snippet' => ['<?php echo 1;'];
        yield 'non-string' => [42];
    }

    #[DataProvider('invalidHiddenProvider')]
    public function testRejectsNonBooleanHidden(mixed $value): void
    {
        $metadata = self::metadata();
        $metadata['hidden'] = $value;
        self::assertInvalid($metadata);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidHiddenProvider(): iterable
    {
        yield 'integer' => [1];
        yield 'string' => ['false'];
        yield 'null' => [null];
    }

    /**
     * @param list<mixed>|array<string, mixed> $arguments
     */
    #[DataProvider('invalidArgumentProvider')]
    public function testRejectsInvalidArgumentDescriptors(array $arguments): void
    {
        $metadata = self::metadata();
        $metadata['arguments'] = $arguments;
        self::assertInvalid($metadata);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalidArgumentProvider(): iterable
    {
        $required = ['name' => 'first', 'summary' => 'First argument.', 'required' => true, 'variadic' => false];
        $optional = ['name' => 'second', 'summary' => 'Second argument.', 'required' => false, 'variadic' => false];
        $variadic = ['name' => 'rest', 'summary' => 'Remaining arguments.', 'required' => false, 'variadic' => true];

        yield 'argument-missing-key' => [[['name' => 'first', 'summary' => 'First.', 'required' => true]]];
        yield 'argument-unknown-key' => [[\array_merge($required, ['default' => 'file'])]];
        yield 'argument-duplicate-name' => [[$required, $required]];
        yield 'required-after-optional' => [[$optional, $required]];
        yield 'non-final-variadic' => [[$variadic, $optional]];
        yield 'invalid-name' => [[\array_replace($required, ['name' => 'Bad Name'])]];
        yield 'invalid-summary' => [[\array_replace($required, ['summary' => ''])]];
        yield 'invalid-required-type' => [[\array_replace($required, ['required' => 1])]];
        yield 'invalid-variadic-type' => [[\array_replace($required, ['variadic' => 'yes'])]];
        yield 'argument-not-map' => [['bad']];
        yield 'arguments-not-list' => [['first' => $required]];
    }

    /** @param array<mixed> $options */
    #[DataProvider('invalidOptionProvider')]
    public function testRejectsInvalidOptionDescriptors(array $options): void
    {
        $metadata = self::metadata();
        $metadata['options'] = $options;
        self::assertInvalid($metadata);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalidOptionProvider(): iterable
    {
        $required = ['name' => 'item', 'summary' => 'Item value.', 'value' => 'required', 'repeatable' => true];
        $optional = ['name' => 'mode', 'summary' => 'Mode value.', 'value' => 'optional', 'repeatable' => false];
        $none = ['name' => 'verbose', 'summary' => 'Verbose output.', 'value' => 'none', 'repeatable' => false];

        yield 'option-missing-key' => [[['name' => 'item', 'summary' => 'Item.', 'value' => 'required']]];
        yield 'option-unknown-key' => [[\array_merge($required, ['default' => 'a'])]];
        yield 'duplicate-options' => [[$required, $required]];
        yield 'unknown-value-token' => [[\array_replace($required, ['value' => 'many'])]];
        yield 'none-repeatable' => [[\array_replace($none, ['repeatable' => true])]];
        yield 'optional-repeatable' => [[\array_replace($optional, ['repeatable' => true])]];
        yield 'invalid-option-name' => [[\array_replace($required, ['name' => 'Bad Name'])]];
        yield 'reserved-format' => [[\array_replace($required, ['name' => 'format'])]];
        yield 'reserved-color' => [[\array_replace($required, ['name' => 'color'])]];
        yield 'invalid-option-summary' => [[\array_replace($required, ['summary' => ''])]];
        yield 'invalid-repeatable-type' => [[\array_replace($required, ['repeatable' => 1])]];
        yield 'non-string-value' => [[\array_replace($required, ['value' => true])]];
        yield 'option-not-map' => [['bad']];
        yield 'options-not-list' => [['item' => $required]];
    }

    public function testRejectsNonJsonLikeMetadata(): void
    {
        foreach ([new \stdClass(), static fn (): int => 1, 1.5] as $value) {
            $metadata = self::metadata();
            $metadata['summary'] = $value;
            self::assertInvalid($metadata);
        }

        $stream = \fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        try {
            $metadata = self::metadata();
            $metadata['summary'] = $stream;
            self::assertInvalid($metadata);
        } finally {
            \fclose($stream);
        }
    }

    /** @return array<string, mixed> */
    private static function metadata(): array
    {
        return [
            'name' => CommandTagSchemaRichFixture::NAME,
            'summary' => CommandTagSchemaRichFixture::SUMMARY,
            'group' => CommandTagSchemaRichFixture::GROUP,
            'hidden' => CommandTagSchemaRichFixture::HIDDEN,
            'arguments' => CommandTagSchemaRichFixture::ARGUMENTS,
            'options' => CommandTagSchemaRichFixture::OPTIONS,
        ];
    }

    /** @param array<string, mixed> $metadata */
    private static function assertInvalid(array $metadata): void
    {
        try {
            new CommandTagSchema()->validate(
                new TaggedService(
                    id: CommandTagSchemaRichFixture::class,
                    meta: $metadata,
                ),
            );
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID, $exception->reason());
            self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                InvalidCommandTagMetaException::ERROR_CODE . ': ' . InvalidCommandTagMetaException::REASON_SCHEMA_INVALID,
                $exception->getMessage(),
            );

            return;
        }

        self::fail('Expected invalid command metadata to be rejected.');
    }
}

/** Rich metadata fixture with no constructor invocation during tag validation. */
final class CommandTagSchemaRichFixture implements CommandInterface
{
    public const string NAME = 'owner:run';
    public const string SUMMARY = 'Run the owner package workflow.';
    public const string GROUP = 'owner-tools';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [
        ['name' => 'source', 'summary' => 'Required source.', 'required' => true, 'variadic' => false],
        ['name' => 'filter', 'summary' => 'Optional filter.', 'required' => false, 'variadic' => false],
        ['name' => 'paths', 'summary' => 'Optional paths.', 'required' => false, 'variadic' => true],
    ];
    public const array OPTIONS = [
        ['name' => 'mode', 'summary' => 'Owner mode.', 'value' => 'required', 'repeatable' => false],
        ['name' => 'preset', 'summary' => 'Owner preset.', 'value' => 'optional', 'repeatable' => false],
        ['name' => 'item', 'summary' => 'Ordered items.', 'value' => 'required', 'repeatable' => true],
        ['name' => 'verbose', 'summary' => 'Verbose output.', 'value' => 'none', 'repeatable' => false],
    ];

    public function __construct()
    {
        throw new \LogicException('schema-fixture-must-not-be-instantiated');
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
