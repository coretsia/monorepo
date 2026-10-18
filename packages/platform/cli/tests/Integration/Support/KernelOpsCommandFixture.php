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
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\CacheVerifyCommand;
use Coretsia\Platform\Cli\Command\ConfigCompileCommand;
use Coretsia\Platform\Cli\Command\ConfigDebugCommand;
use Coretsia\Platform\Cli\Command\ConfigHashCommand;
use Coretsia\Platform\Cli\Command\ConfigValidateCommand;
use Coretsia\Platform\Cli\Command\DebugModulesCommand;
use Coretsia\Platform\Cli\Diagnostics\CliErrorHandler;
use Coretsia\Platform\Cli\Diagnostics\ExceptionRenderer;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Kernel\KernelOpsRequestResolver;
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
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/** Exercises the real normal-path pipeline with a strict test-only Ops port. */
final class KernelOpsCommandFixture
{
    public const array COMMANDS = [
        CacheVerifyCommand::class,
        ConfigCompileCommand::class,
        ConfigDebugCommand::class,
        ConfigHashCommand::class,
        ConfigValidateCommand::class,
        DebugModulesCommand::class,
    ];

    public readonly RecordingKernelOpsPort $ops;
    public readonly BoundaryScenarioRuntime $runtime;
    public readonly RecordingKernelOpsCommandContainer $container;
    private readonly CliApplication $application;

    public function __construct()
    {
        $this->ops = new RecordingKernelOpsPort();
        $this->runtime = new BoundaryScenarioRuntime();
        $this->container = new RecordingKernelOpsCommandContainer($this->ops, $this->runtime);
        $registry = new TagRegistry();
        foreach (self::COMMANDS as $command) {
            $registry->add(ReservedTags::CLI_COMMAND, $command, meta: [
                'name' => $command::NAME,
                'summary' => $command::SUMMARY,
                'group' => $command::GROUP,
                'hidden' => $command::HIDDEN,
                'arguments' => $command::ARGUMENTS,
                'options' => $command::OPTIONS,
            ]);
        }
        $policy = new CliOutputPolicy('adaptive', 'table', 'plain', 'auto', 120);
        $ansi = new AnsiDecorator();
        $redactor = new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
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
                $this->container,
                new Stopwatch(),
            ),
            new CliErrorHandler(),
            new ExceptionRenderer(),
            new OutputFormatter(
                $redactor,
                new JsonFormatter(),
                new TableFormatter($policy->tableMaxWidth(), $ansi),
                new PlainFormatter($ansi),
            ),
        );
    }

    /** @return array{exit:int,stdout:string,stderr:string} */
    public function run(string $command, string $target = 'api'): array
    {
        $stdout = \fopen('php://temp', 'w+');
        $stderr = \fopen('php://temp', 'w+');
        if (!\is_resource($stdout) || !\is_resource($stderr)) {
            throw new \RuntimeException('ops-fixture-stream-unavailable');
        }

        try {
            $exit = $this->application->run(
                ['coretsia', $command, '--target=' . $target, '--format=json', '--color=always'],
                new TerminalCapabilities(true, true),
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

    /** @param array{exit:int,stdout:string,stderr:string} $result @return array<string,mixed> */
    public static function document(array $result): array
    {
        $decoded = \json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($decoded) || ($decoded['schema'] ?? null) !== 'coretsia.cli-output@1') {
            throw new \RuntimeException('ops-fixture-json-envelope-invalid');
        }
        return $decoded;
    }

    /** @param array{exit:int,stdout:string,stderr:string} $result @return array<string,mixed> */
    public static function payload(array $result): array
    {
        $document = self::document($result);
        $records = $document['data']['records'] ?? null;
        if (!\is_array($records) || \count($records) !== 1 || ($records[0]['type'] ?? null) !== 'json') {
            throw new \RuntimeException('ops-fixture-json-record-invalid');
        }
        return $records[0]['payload'];
    }
}

/** Rejects every service except the selected built-in command. */
final class RecordingKernelOpsCommandContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $resolvedIds = [];

    public function __construct(
        private readonly RecordingKernelOpsPort $ops,
        private readonly BoundaryScenarioRuntime $runtime,
    ) {
    }

    public function get(string $id): mixed
    {
        $this->resolvedIds[] = $id;
        if (!\in_array($id, KernelOpsCommandFixture::COMMANDS, true) || !$this->runtime->active) {
            throw new \LogicException('unexpected-ops-service-resolution');
        }
        return new $id($this->ops, new KernelOpsRequestResolver());
    }

    public function has(string $id): bool
    {
        return \in_array($id, KernelOpsCommandFixture::COMMANDS, true);
    }
}
