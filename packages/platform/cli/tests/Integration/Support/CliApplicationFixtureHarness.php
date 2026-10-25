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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
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
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use Coretsia\Platform\Cli\UX\SmartSuggestor;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** Shared real CLI composition and isolated output streams for integration fixtures. */
final class CliApplicationFixtureHarness
{
    private readonly CliApplication $application;

    public function __construct(
        CommandCatalog $catalog,
        KernelRuntimeInterface $runtime,
        ContainerInterface $container,
        SensitiveDataRedactorInterface $redactor,
        ?CliOutputPolicy $policy = null,
        ?LoggerInterface $logger = null,
        ?ErrorHandlerInterface $errors = null,
    ) {
        $policy ??= new CliOutputPolicy(
            'adaptive',
            'table',
            'plain',
            'auto',
            120,
        );
        $ansi = new AnsiDecorator();
        $errors ??= self::canonicalErrorHandler($redactor);

        $this->application = new CliApplication(
            new ArgvInputParser(),
            $catalog,
            new CommandInputValidator(),
            new FormatResolver($policy),
            new ColorResolver($policy),
            new CommandRunner(
                $runtime,
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
                $logger ?? new NullLogger(),
                $container,
                new Stopwatch(),
            ),
            $errors,
            new ExceptionRenderer(),
            new OutputFormatter(
                $redactor,
                new JsonFormatter(),
                new TableFormatter($policy->tableMaxWidth(), $ansi),
                new PlainFormatter($ansi),
            ),
            new SmartSuggestor(),
        );
    }

    private static function canonicalErrorHandler(SensitiveDataRedactorInterface $redactor): ErrorHandlerInterface
    {
        $builder = new ContainerBuilder();
        $builder
            ->instance(
                ConfigRepositoryInterface::class,
                new ArrayConfigRepository([
                    'errors' => require \dirname(__DIR__, 4) . '/errors/config/errors.php',
                ]),
            )
            ->instance(SensitiveDataRedactorInterface::class, $redactor)
            ->instance(ErrorReporterPortInterface::class, new NoopErrorReporter())
            ->instance(
                CorrelationIdProviderInterface::class,
                new class() implements CorrelationIdProviderInterface {
                    public function correlationId(): ?string
                    {
                        return null;
                    }
                },
            )
            ->instance(TracerPortInterface::class, new NoopTracer())
            ->instance(MeterPortInterface::class, new NoopMeter())
            ->instance(LoggerInterface::class, new NullLogger())
            ->instance(Stopwatch::class, new Stopwatch());
        $builder->registerProviders([
            new ErrorsServiceProvider(),
            new CliServiceProvider(),
        ]);

        $handler = $builder->build()->get(ErrorHandlerInterface::class);
        if (!$handler instanceof ErrorHandlerInterface) {
            throw new \LogicException('cli-fixture-canonical-handler-invalid');
        }

        return $handler;
    }

    /**
     * @param list<string> $tokens
     * @param (\Closure(resource,resource): void)|null $beforeRun
     * @return array{exit:int,stdout:string,stderr:string}
     */
    public function run(
        array $tokens,
        ?TerminalCapabilities $capabilities = null,
        ?\Closure $beforeRun = null,
        string $streamFailure = 'cli-fixture-stream-unavailable',
    ): array {
        $stdout = \fopen('php://temp', 'w+');
        $stderr = \fopen('php://temp', 'w+');

        if (!\is_resource($stdout) || !\is_resource($stderr)) {
            if (\is_resource($stdout)) {
                \fclose($stdout);
            }
            if (\is_resource($stderr)) {
                \fclose($stderr);
            }

            throw new \RuntimeException($streamFailure);
        }

        try {
            $beforeRun?->__invoke($stdout, $stderr);

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
