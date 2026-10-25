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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\HelpCommand;
use Coretsia\Platform\Cli\Diagnostics\ExceptionRenderer;
use Coretsia\Platform\Cli\Exception\CliOutputFormatException;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Mapper\CliExceptionMapper;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CliOutputPolicy;
use Coretsia\Platform\Cli\Output\ColorResolver;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Output\FormatResolver;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use Coretsia\Platform\Cli\UX\SmartSuggestor;
use PHPUnit\Framework\TestCase;

final class CliCommandOutputBufferIsInvocationLocalContractTest extends TestCase
{
    public function testSourceAndRuntimeContainerDefinitionsDoNotRegisterOutputBuffer(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(new CliServiceProvider());
        foreach (['definitions', 'instances'] as $property) {
            $registered = new \ReflectionProperty(ContainerBuilder::class, $property)->getValue($builder);
            self::assertIsArray($registered);
            self::assertArrayNotHasKey(CommandOutputBuffer::class, $registered);
        }

        $definitions = new ContainerDefinitionBuilder();
        new CliServiceProvider()->define($definitions, new ContainerDefinitionContext([]));
        $set = $definitions->build();
        self::assertNotContains(CommandOutputBuffer::class, $set->requiredServiceIds());
        foreach ($set->toDescriptorStream() as $operation) {
            self::assertNotSame(CommandOutputBuffer::class, $operation['id'] ?? null);
            self::assertNotSame(CommandOutputBuffer::class, $operation['serviceId'] ?? null);
            self::assertNotSame(CommandOutputBuffer::class, $operation['alias'] ?? null);
        }
    }

    public function testOneBufferIsCreatedLocallyAndReusedForExceptionRendering(): void
    {
        $reflection = new \ReflectionClass(CliApplication::class);
        foreach ($reflection->getProperties() as $property) {
            self::assertNotSame(CommandOutputBuffer::class, $property->getType()?->getName());
            self::assertNotSame(ErrorDescriptor::class, $property->getType()?->getName());
        }
        $method = $reflection->getMethod('run');
        $lines = \file($method->getFileName());
        self::assertIsArray($lines);
        $source = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );
        self::assertSame(1, \preg_match_all('/\bnew\s+CommandOutputBuffer\s*\(/', $source));
        self::assertSame(1, \preg_match_all('/\$buffer\s*=\s*new\s+CommandOutputBuffer\s*\(/', $source));
        self::assertSame(1, \preg_match_all('/\$buffer\s*->\s*discard\s*\(/', $source));

        $catchPosition = \strpos($source, '} catch (\\Throwable $exception) {');
        $discardPosition = \strpos($source, '$buffer->discard()');
        $renderPosition = \strpos($source, '$this->exceptions->render(');

        self::assertIsInt($catchPosition);
        self::assertIsInt($discardPosition);
        self::assertIsInt($renderPosition);
        self::assertGreaterThan($catchPosition, $discardPosition);
        self::assertGreaterThan($discardPosition, $renderPosition);
        self::assertStringContainsString('$this->runner->run($descriptor, $normalized, $buffer, $format)', $source);
        self::assertSame(1, \preg_match_all('/\$this->errors->handle\s*\(/', $source));
        self::assertStringContainsString(
            '$errorDescriptor = $this->errors->handle($exception);',
            $source,
        );
        self::assertStringContainsString(
            '$this->exceptions->render($errorDescriptor, $buffer);',
            $source,
        );
        self::assertStringContainsString(
            '$this->appendSuggestions($buffer, $suggestions, $errorDescriptor);',
            $source,
        );

        $suggestionsMethod = $reflection->getMethod('appendSuggestions');
        $suggestionsSource = \implode(
            '',
            \array_slice(
                $lines,
                $suggestionsMethod->getStartLine() - 1,
                $suggestionsMethod->getEndLine() - $suggestionsMethod->getStartLine() + 1,
            ),
        );
        self::assertStringContainsString('ErrorDescriptor $descriptor', $suggestionsSource);
        self::assertStringContainsString('$buffer->discard();', $suggestionsSource);
        self::assertStringContainsString(
            '$this->exceptions->render($descriptor, $buffer);',
            $suggestionsSource,
        );
        self::assertStringNotContainsString('$this->errors->handle(', $suggestionsSource);

        $suggestionDiscardPosition = \strpos($suggestionsSource, '$buffer->discard();');
        $suggestionRenderPosition = \strpos(
            $suggestionsSource,
            '$this->exceptions->render($descriptor, $buffer);',
        );
        self::assertIsInt($suggestionDiscardPosition);
        self::assertIsInt($suggestionRenderPosition);
        self::assertGreaterThan($suggestionDiscardPosition, $suggestionRenderPosition);
        self::assertStringContainsString('$buffer->finalize()', $source);
        self::assertSame(0, \preg_match('/\$this\s*->\s*(?:buffer|outputBuffer)\s*=/', $source));
    }

    public function testRepeatedInvocationsDoNotRetainPreviousErrorRecords(): void
    {
        $policy = new CliOutputPolicy('plain', 'table', 'plain', 'never', 120);
        $ansi = new AnsiDecorator();
        $redactor = new class() implements SensitiveDataRedactorInterface {
            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                throw new \LogicException('Unexpected direct string redaction.');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                return $value;
            }
        };
        $runner = new \ReflectionClass(CommandRunner::class)->newInstanceWithoutConstructor();
        $errors = self::recordingHandler();
        $application = new CliApplication(
            new ArgvInputParser(),
            new CommandCatalog(new TagRegistry(), new CommandTagSchema(), new CommandOverrides(), [], []),
            new CommandInputValidator(),
            new FormatResolver($policy),
            new ColorResolver($policy),
            $runner,
            $errors,
            new ExceptionRenderer(),
            new OutputFormatter(
                $redactor,
                new JsonFormatter(),
                new TableFormatter(120, $ansi),
                new PlainFormatter($ansi),
            ),
            new SmartSuggestor(),
        );

        $outputs = [];
        foreach (['missing:first', 'missing:second', 'missing:first'] as $index => $command) {
            $stdout = \fopen('php://temp', 'w+');
            $stderr = \fopen('php://temp', 'w+');
            self::assertIsResource($stdout);
            self::assertIsResource($stderr);
            try {
                self::assertSame(
                    1,
                    $application->run(
                        ['coretsia', $command],
                        new TerminalCapabilities(false, false),
                        new ConsoleOutputWriter($stdout, $stderr),
                    ),
                );
                \rewind($stdout);
                \rewind($stderr);
                $out = \stream_get_contents($stdout);
                $err = \stream_get_contents($stderr);
                self::assertSame('', $out);
                self::assertIsString($err);
                self::assertSame(1, \substr_count($err, 'CORETSIA_CLI_'));
                self::assertSame($index + 1, new \ReflectionProperty($errors, 'calls')->getValue($errors));
                $outputs[] = $err;
            } finally {
                \fclose($stdout);
                \fclose($stderr);
            }
        }
        self::assertSame($outputs[0], $outputs[2]);
        self::assertSame($outputs[0], $outputs[1]);
    }

    public function testUnknownCommandSuggestionsReuseSingleNormalizedDescriptor(): void
    {
        $registry = new TagRegistry();
        $registry->add(
            ReservedTags::CLI_COMMAND,
            HelpCommand::class,
            meta: [
                'name' => HelpCommand::NAME,
                'summary' => HelpCommand::SUMMARY,
                'group' => HelpCommand::GROUP,
                'hidden' => HelpCommand::HIDDEN,
                'arguments' => HelpCommand::ARGUMENTS,
                'options' => HelpCommand::OPTIONS,
            ],
        );

        $policy = new CliOutputPolicy(
            'plain',
            'table',
            'plain',
            'never',
            120,
        );
        $redactor = new class() implements SensitiveDataRedactorInterface {
            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                throw new \LogicException('Unexpected direct string redaction.');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                return $value;
            }
        };

        $errors = self::recordingHandler();
        $renderer = new ExceptionRenderer();
        $ansi = new AnsiDecorator();

        $application = new CliApplication(
            new ArgvInputParser(),
            new CommandCatalog(
                $registry,
                new CommandTagSchema(),
                new CommandOverrides(),
                [],
                ['help' => HelpCommand::class],
            ),
            new CommandInputValidator(),
            new FormatResolver($policy),
            new ColorResolver($policy),
            new \ReflectionClass(CommandRunner::class)->newInstanceWithoutConstructor(),
            $errors,
            $renderer,
            new OutputFormatter(
                $redactor,
                new JsonFormatter(),
                new TableFormatter(120, $ansi),
                new PlainFormatter($ansi),
            ),
            new SmartSuggestor(),
        );

        $stdout = \fopen('php://temp', 'w+');
        $stderr = \fopen('php://temp', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        try {
            self::assertSame(
                1,
                $application->run(
                    ['coretsia', 'hel'],
                    new TerminalCapabilities(false, false),
                    new ConsoleOutputWriter($stdout, $stderr),
                ),
            );

            \rewind($stdout);
            \rewind($stderr);

            $output = \stream_get_contents($stdout);
            self::assertIsString($output);
            self::assertStringContainsString('"suggestions":["help"]', $output);
            self::assertSame(
                "CORETSIA_CLI_INPUT_INVALID: cli-command-unknown\n",
                \stream_get_contents($stderr),
            );
            self::assertSame(1, new \ReflectionProperty($errors, 'calls')->getValue($errors));

            $recoveryBuffer = new CommandOutputBuffer();
            try {
                $recoveryBuffer->json(['unsupported' => new \stdClass()]);
                self::fail('Expected output buffer failure.');
            } catch (CliOutputFormatException) {
                // The failed buffer forces appendSuggestions() into its recovery path.
            }

            $descriptor = new ErrorDescriptor('CORETSIA_CLI_INPUT_INVALID', 'cli-command-unknown');
            new \ReflectionMethod(CliApplication::class, 'appendSuggestions')->invoke(
                $application,
                $recoveryBuffer,
                ['help'],
                $descriptor,
            );

            self::assertSame(
                [
                    [
                        'code' => 'CORETSIA_CLI_INPUT_INVALID',
                        'message' => 'cli-command-unknown',
                        'type' => 'error',
                    ],
                ],
                $recoveryBuffer->finalize()->records(),
            );
            self::assertSame(1, new \ReflectionProperty($errors, 'calls')->getValue($errors));
        } finally {
            \fclose($stdout);
            \fclose($stderr);
        }
    }

    private static function recordingHandler(): ErrorHandlerInterface
    {
        return new class() implements ErrorHandlerInterface {
            public int $calls = 0;

            public function handle(\Throwable $throwable, ?ErrorHandlingContext $context = null): ErrorDescriptor
            {
                ++$this->calls;

                return new CliExceptionMapper()->map($throwable, $context)
                    ?? new ErrorDescriptor('CORETSIA_UNHANDLED_EXCEPTION', 'Unhandled exception.');
            }
        };
    }
}
