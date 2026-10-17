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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Diagnostics\CliErrorHandler;
use Coretsia\Platform\Cli\Diagnostics\ExceptionRenderer;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CliOutputPolicy;
use Coretsia\Platform\Cli\Output\ColorResolver;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Output\FormatResolver;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use Psr\Log\NullLogger;

/**
 * Full normal-path CLI dispatch using real parser, validator, catalog, UoW
 * callback, output formatting and a deliberately isolated owner command.
 */
final class CliDispatchIntegrationFixture
{
    public readonly RecordingOwnerContainer $container;
    public readonly RecordingCliRuntime $runtime;

    private CliApplication $application;

    public function __construct(?CliOutputPolicy $policy = null)
    {
        $policy ??= new CliOutputPolicy(
            'adaptive',
            'table',
            'plain',
            'auto',
            120,
        );
        $registry = new TagRegistry();
        $registry->add(ReservedTags::CLI_COMMAND, RecordingOwnerCommand::class, meta: [
            'name' => RecordingOwnerCommand::NAME,
            'summary' => RecordingOwnerCommand::SUMMARY,
            'group' => RecordingOwnerCommand::GROUP,
            'hidden' => RecordingOwnerCommand::HIDDEN,
            'arguments' => RecordingOwnerCommand::ARGUMENTS,
            'options' => RecordingOwnerCommand::OPTIONS,
        ]);
        $catalog = new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
        $this->container = new RecordingOwnerContainer();
        $this->runtime = new RecordingCliRuntime();
        $runner = new CommandRunner(
            $this->runtime,
            new class() implements ContextAccessorInterface {
                public function has(string $key): bool
                {
                    return false;
                }

                public function get(string $key): mixed
                {
                    return null;
                }
            },
            new NoopTracer(),
            new NoopMeter(),
            new NullLogger(),
            $this->container,
            new Stopwatch(),
        );
        $ansi = new AnsiDecorator();
        $formatter = new OutputFormatter(
            new class() implements SensitiveDataRedactorInterface {
                public function redactValue(
                    string $value,
                    RedactionKind $kind,
                    RedactionContext $context,
                ): RedactedValue {
                    throw new \LogicException('Direct redaction is not used by this fixture.');
                }

                public function redactJsonLike(mixed $value, RedactionContext $context): mixed
                {
                    return $value;
                }
            },
            new JsonFormatter(),
            new TableFormatter($policy->tableMaxWidth(), $ansi),
            new PlainFormatter($ansi),
        );
        $this->application = new CliApplication(
            new ArgvInputParser(),
            $catalog,
            new CommandInputValidator(),
            new FormatResolver($policy),
            new ColorResolver($policy),
            $runner,
            new CliErrorHandler(),
            new ExceptionRenderer(),
            $formatter,
        );
    }

    /**
     * @param list<string> $tokens
     * @return array{exit:int,stdout:string,stderr:string}
     */
    public function run(array $tokens, ?TerminalCapabilities $capabilities = null): array
    {
        $stdout = \fopen('php://temp', 'w+');
        $stderr = \fopen('php://temp', 'w+');
        if (!\is_resource($stdout) || !\is_resource($stderr)) {
            throw new \RuntimeException('cli-fixture-stream-unavailable');
        }
        try {
            $exit = $this->application->run(
                ['coretsia', ...$tokens],
                $capabilities ?? new TerminalCapabilities(false, false),
                new ConsoleOutputWriter($stdout, $stderr),
            );
            \rewind($stdout);
            \rewind($stderr);
            return [
                'exit' => $exit,
                'stdout' => (string) \stream_get_contents($stdout),
                'stderr' => (string) \stream_get_contents($stderr),
            ];
        } finally {
            \fclose($stdout);
            \fclose($stderr);
        }
    }
}
