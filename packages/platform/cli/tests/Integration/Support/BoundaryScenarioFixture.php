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

/** Real application/parser/catalog/runner/rendering with one observable owner command. */
final class BoundaryScenarioFixture
{
    public readonly BoundaryScenarioRuntime $runtime;
    private readonly CliApplication $application;

    public function __construct()
    {
        $this->runtime = new BoundaryScenarioRuntime();
        BoundaryScenarioCommand::reset($this->runtime);
        $registry = new TagRegistry();
        $registry->add(ReservedTags::CLI_COMMAND, BoundaryScenarioCommand::class, meta: [
            'name' => BoundaryScenarioCommand::NAME,
            'summary' => BoundaryScenarioCommand::SUMMARY,
            'group' => BoundaryScenarioCommand::GROUP,
            'hidden' => BoundaryScenarioCommand::HIDDEN,
            'arguments' => BoundaryScenarioCommand::ARGUMENTS,
            'options' => BoundaryScenarioCommand::OPTIONS,
        ]);
        $policy = new CliOutputPolicy('adaptive', 'table', 'plain', 'auto', 120);
        $ansi = new AnsiDecorator();
        $redactor = new class() implements SensitiveDataRedactorInterface {
            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                throw new \LogicException('Only the JSON-like redaction port is used.');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                return $value;
            }
        };
        $this->application = new CliApplication(
            new ArgvInputParser(),
            new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []),
            new CommandInputValidator(),
            new FormatResolver($policy),
            new ColorResolver($policy),
            new CommandRunner(
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
                new BoundaryScenarioContainer($this->runtime),
                new Stopwatch(),
            ),
            new CliErrorHandler(),
            new ExceptionRenderer(),
            new OutputFormatter(
                $redactor,
                new JsonFormatter(),
                new TableFormatter(120, $ansi),
                new PlainFormatter($ansi),
            ),
        );
    }

    /** @param list<string> $arguments @return array{exit:int,stdout:string,stderr:string} */
    public function run(array $arguments, ?TerminalCapabilities $capabilities = null): array
    {
        $out = \fopen('php://temp', 'w+');
        $err = \fopen('php://temp', 'w+');
        if (!\is_resource($out) || !\is_resource($err)) {
            throw new \RuntimeException('cli-fixture-stream-unavailable');
        }
        try {
            $exit = $this->application->run(
                ['coretsia', ...$arguments],
                $capabilities ?? new TerminalCapabilities(false, false),
                new ConsoleOutputWriter($out, $err),
            );
            \rewind($out);
            \rewind($err);
            return [
                'exit' => $exit,
                'stdout' => (string) \stream_get_contents($out),
                'stderr' => (string) \stream_get_contents($err),
            ];
        } finally {
            \fclose($out);
            \fclose($err);
        }
    }
}
