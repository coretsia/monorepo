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

namespace Coretsia\Platform\Cli\Runner;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Exception\CliCommandFailedException;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Lazy, single-command dispatch inside the canonical Kernel UoW lifecycle.
 * Command observability remains independent and best-effort.
 *
 * @internal
 */
final readonly class CommandRunner
{
    private const string METRIC_COMMAND_TOTAL = 'cli.command_total';
    private const string METRIC_COMMAND_DURATION_MS = 'cli.command_duration_ms';

    private const string OUTCOME_SUCCESS = 'success';
    private const string OUTCOME_FAILURE = 'failure';

    public function __construct(
        private KernelRuntimeInterface $runtime,
        private ContextAccessorInterface $context,
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private ContainerInterface $container,
        private Stopwatch $stopwatch,
    ) {
    }

    public function run(
        CommandDescriptor $descriptor,
        InputInterface $input,
        OutputInterface $output,
        string $outputFormat,
    ): int {
        if (!\in_array($outputFormat, ['json', 'table', 'plain'], true)) {
            throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_INVALID);
        }

        $operation = $descriptor->name();
        $spanAttributes = ['operation' => $operation, 'format' => $outputFormat];
        $attributes = ['operation' => $operation, 'output_format' => $outputFormat];
        $span = null;
        $start = null;
        $durationMs = 0;
        $exitCode = 1;
        $outcome = self::OUTCOME_FAILURE;
        $identifiers = [];

        try {
            $span = $this->tracer->startSpan('cli.command', $spanAttributes);
        } catch (\Throwable) {
            // Tracing must not affect command execution.
        }

        try {
            $start = $this->stopwatch->start();
        } catch (\Throwable) {
            // Use a non-negative fallback duration.
        }

        try {
            $result = $this->runtime->runUnitOfWork(
                UnitOfWorkType::CLI,
                function () use ($descriptor, $input, $output, &$identifiers): int {
                    $identifiers = $this->safeContextIdentifiers();
                    $command = $this->container->get($descriptor->serviceId());

                    if (
                        !$command instanceof CommandInterface
                        || \get_class($command) !== $descriptor->serviceId()
                        || $command->name() !== $descriptor->name()
                    ) {
                        throw InvalidCommandTagMetaException::withReason(
                            InvalidCommandTagMetaException::REASON_IDENTITY_MISMATCH,
                        );
                    }

                    $code = $command->run($input, $output);
                    if ($code < 0 || $code > 255) {
                        throw CliCommandFailedException::invalidExitCode();
                    }

                    return $code;
                },
                $attributes,
            );

            if (!\is_int($result) || $result < 0 || $result > 255) {
                throw CliCommandFailedException::invalidExitCode();
            }

            $exitCode = $result;
            $outcome = $exitCode === 0 ? self::OUTCOME_SUCCESS : self::OUTCOME_FAILURE;

            return $exitCode;
        } finally {
            if ($start !== null) {
                try {
                    $durationMs = \max(0, $this->stopwatch->stop($start));
                } catch (\Throwable) {
                    $durationMs = 0;
                }
            }

            if ($span instanceof SpanInterface) {
                try {
                    $span->setAttributes([
                        'operation' => $operation,
                        'format' => $outputFormat,
                        'outcome' => $outcome,
                        'exit_code' => $exitCode,
                    ]);
                } catch (\Throwable) {
                    // Ending the span is still mandatory.
                }

                try {
                    $span->end();
                } catch (\Throwable) {
                    // Telemetry must never mask the primary outcome.
                }
            }

            $labels = ['operation' => $operation, 'outcome' => $outcome];
            try {
                $this->meter->increment(self::METRIC_COMMAND_TOTAL, 1, $labels);
            } catch (\Throwable) {
                // Counters are best-effort.
            }

            try {
                $this->meter->observe(self::METRIC_COMMAND_DURATION_MS, $durationMs, $labels);
            } catch (\Throwable) {
                // Duration observation is independently best-effort.
            }

            try {
                $this->logger->log(
                    $outcome === self::OUTCOME_SUCCESS ? 'info' : 'error',
                    'cli.command',
                    [
                        'operation' => $operation,
                        'outcome' => $outcome,
                        'exit_code' => $exitCode,
                        ...$identifiers,
                    ],
                );
            } catch (\Throwable) {
                // Logging is best-effort.
            }
        }
    }

    /** @return array<string, string> */
    private function safeContextIdentifiers(): array
    {
        $identifiers = [];
        foreach (
            [
                ContextKeys::CORRELATION_ID => 'correlation_id',
                ContextKeys::UOW_ID => 'uow_id',
            ] as $key => $field
        ) {
            try {
                if (!$this->context->has($key)) {
                    continue;
                }

                $value = $this->context->get($key);
                if (
                    \is_string($value)
                    && \preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}\z/D', $value) === 1
                ) {
                    $identifiers[$field] = $value;
                }
            } catch (\Throwable) {
                // Missing or unreadable context must not change execution.
            }
        }

        return $identifiers;
    }
}
