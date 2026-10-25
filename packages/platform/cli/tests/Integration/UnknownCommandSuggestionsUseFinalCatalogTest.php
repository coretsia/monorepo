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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand\ExternalModeCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\TaggedCommandApplicationFixture;
use Coretsia\Platform\Cli\UX\SmartSuggestor;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class UnknownCommandSuggestionsUseFinalCatalogTest extends TestCase
{
    public function testSuggestionsUseFinalVisibleCatalogOrderWithoutResolvingCommandServices(): void
    {
        [$fixture, $catalog, $resolved, $observed] = self::fixture();
        $descriptors = $catalog->descriptors();
        self::assertSame('utilities', $descriptors[SuggestionAlpcaCommand::NAME]->group());
        self::assertTrue($descriptors[SuggestionAlpbaCommand::NAME]->hidden());
        self::assertSame([
            SuggestionAlpcaCommand::NAME,
            SuggestionAlpdaCommand::NAME,
            SuggestionAlphaCommand::NAME,
        ], new SmartSuggestor()->suggest('tool:alpfa', $catalog));

        $result = $fixture->run(['tool:alpfa', '--format=json']);
        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stderr']);
        $document = TaggedCommandApplicationFixture::document($result);
        self::assertSame('coretsia.cli-output@1', $document['schema']);
        self::assertSame(['command' => null, 'exit_code' => 1, 'outcome' => 'failure'], $document['meta']);
        $records = $document['data']['records'];
        self::assertCount(2, $records);
        self::assertSame('error', $records[0]['type']);
        self::assertSame(CliInputInvalidException::ERROR_CODE, $records[0]['code']);
        self::assertSame(CliInputInvalidException::REASON_COMMAND_UNKNOWN, $records[0]['message']);
        self::assertSame([
            'suggestions' => [
                SuggestionAlpcaCommand::NAME,
                SuggestionAlpdaCommand::NAME,
                SuggestionAlphaCommand::NAME,
            ],
        ], $records[1]['payload']);
        self::assertSame('json', $records[1]['type']);
        self::assertSame([], $resolved());
        self::assertSame(0, $fixture->runtime->calls);
        self::assertCount(3, $observed());
        self::assertSame([
            'suggestions' => [
                SuggestionAlpcaCommand::NAME,
                SuggestionAlpdaCommand::NAME,
                SuggestionAlphaCommand::NAME,
            ],
        ], $observed()[2]);

        $ranked = $fixture->run(['tool:alpdz', '--format=json']);
        self::assertSame(1, $ranked['exit']);
        self::assertSame('', $ranked['stderr']);
        self::assertSame([
            SuggestionAlpdaCommand::NAME,
            SuggestionAlpcaCommand::NAME,
            SuggestionAlphaCommand::NAME,
        ], TaggedCommandApplicationFixture::document($ranked)['data']['records'][1]['payload']['suggestions']);
        self::assertSame([], $resolved());
        self::assertSame(0, $fixture->runtime->calls);
    }

    public function testInvalidOutOfBoundsAndDistantTokensDoNotEmitSuggestionRecords(): void
    {
        [$fixture, $catalog, $resolved] = self::fixture();

        foreach (['tool:zzzzz', 'tool:alpfa!', \str_repeat('a', 129)] as $unknown) {
            $expected = new SmartSuggestor()->suggest($unknown, $catalog);
            self::assertSame([], $expected);

            $result = $fixture->run([$unknown, '--format=json']);
            self::assertSame(1, $result['exit']);
            if ($unknown === 'tool:alpfa!') {
                self::assertSame('', $result['stdout']);
                self::assertSame(
                    CliInputInvalidException::ERROR_CODE . ': ' . CliInputInvalidException::REASON_COMMAND_INVALID . "\n",
                    $result['stderr'],
                );
                continue;
            }
            self::assertSame('', $result['stderr']);
            $document = TaggedCommandApplicationFixture::document($result);
            $records = $document['data']['records'];
            self::assertCount(1, $records);
            self::assertSame(CliInputInvalidException::ERROR_CODE, $records[0]['code']);
            self::assertSame(
                $unknown === 'tool:alpfa!'
                    ? CliInputInvalidException::REASON_COMMAND_INVALID
                    : CliInputInvalidException::REASON_COMMAND_UNKNOWN,
                $records[0]['message'],
            );
        }

        self::assertSame([], $resolved());
        self::assertSame(0, $fixture->runtime->calls);
    }

    public function testSuggestionsKeepNormalStderrOwnershipAndDoNotTreatTokensAsPathsOrWorkflows(): void
    {
        [$fixture, , $resolved] = self::fixture();
        $table = $fixture->run(['tool:alpfa', '--format=table']);
        self::assertSame(1, $table['exit']);
        self::assertStringContainsString(CliInputInvalidException::ERROR_CODE, $table['stderr']);
        self::assertStringContainsString(CliInputInvalidException::REASON_COMMAND_UNKNOWN, $table['stderr']);
        self::assertStringContainsString(SuggestionAlphaCommand::NAME, $table['stdout']);
        self::assertStringNotContainsString('coretsia.cli-output@1', $table['stdout']);
        self::assertSame([], $resolved());

        $sourcePath = new \ReflectionClass(CliApplication::class)->getFileName();
        self::assertIsString($sourcePath);
        $source = \file_get_contents($sourcePath);
        self::assertIsString($source);
        self::assertStringContainsString('$this->catalog->find($input->commandName())', $source);
        self::assertStringContainsString('$this->suggestions->suggest($input->commandName(), $this->catalog)', $source);
        foreach (
            [
                'WorkflowCatalog',
                'realpath(',
                'glob(',
                'scandir(',
                '->log(',
                '->increment(',
                '->observe(',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    /** @return array{TaggedCommandApplicationFixture, CommandCatalog, \Closure(): array, \Closure(): array} */
    private static function fixture(): array
    {
        $registry = new TagRegistry();
        foreach (
            [
                SuggestionAlphaCommand::class,
                SuggestionAlpbaCommand::class,
                SuggestionAlpcaCommand::class,
                SuggestionAlpdaCommand::class,
                SuggestionAlpzaCommand::class,
                ExternalModeCommand::class,
            ] as $class
        ) {
            $registry->add(ReservedTags::CLI_COMMAND, $class, meta: [
                'name' => $class::NAME,
                'summary' => $class::SUMMARY,
                'group' => $class::GROUP,
                'hidden' => $class::HIDDEN,
                'arguments' => $class::ARGUMENTS,
                'options' => $class::OPTIONS,
            ]);
        }
        $catalog = new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [
            SuggestionAlpbaCommand::NAME => ['hidden' => true],
            SuggestionAlpcaCommand::NAME => ['group' => 'utilities'],
        ], []);
        $container = new class() implements ContainerInterface {
            /** @var list<string> */
            public array $resolved = [];

            public function get(string $id): mixed
            {
                $this->resolved[] = $id;
                throw new \LogicException('Unknown-command suggestions must not resolve a service.');
            }

            public function has(string $id): bool
            {
                throw new \LogicException('Unknown-command suggestions must not enumerate services.');
            }
        };
        $delegate = new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
        $redactor = new class($delegate) implements SensitiveDataRedactorInterface {
            /** @var list<mixed> */
            public array $observed = [];

            public function __construct(private readonly SensitiveDataRedactorInterface $delegate)
            {
            }

            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                return $this->delegate->redactValue($value, $kind, $context);
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                $this->observed[] = $value;
                return $this->delegate->redactJsonLike($value, $context);
            }
        };
        return [
            new TaggedCommandApplicationFixture($catalog, $container, $redactor),
            $catalog,
            static fn (): array => $container->resolved,
            static fn (): array => $redactor->observed,
        ];
    }
}

/** Test-only metadata candidates; services must never be instantiated. */
abstract class SuggestionCandidateCommand implements CommandInterface
{
    public const string NAME = 'tool:base';
    public const string SUMMARY = 'Candidate for deterministic CLI suggestions.';
    public const string GROUP = 'tool';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function name(): string
    {
        return static::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        throw new \LogicException('Suggestion candidates may not be dispatched.');
    }
}

final class SuggestionAlphaCommand extends SuggestionCandidateCommand
{
    public const string NAME = 'tool:alpha';
}

final class SuggestionAlpbaCommand extends SuggestionCandidateCommand
{
    public const string NAME = 'tool:alpba';
}

final class SuggestionAlpcaCommand extends SuggestionCandidateCommand
{
    public const string NAME = 'tool:alpca';
}

final class SuggestionAlpdaCommand extends SuggestionCandidateCommand
{
    public const string NAME = 'tool:alpda';
}

final class SuggestionAlpzaCommand extends SuggestionCandidateCommand
{
    public const string NAME = 'tool:alpza';
}
