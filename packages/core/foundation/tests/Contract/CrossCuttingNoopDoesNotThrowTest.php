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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Profiling\NoopProfiler;
use Coretsia\Foundation\Observability\Profiling\NoopProfilingSession;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Foundation\Observability\Tracing\NoopSpan;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Stringable;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testNoopLoggerAcceptsArbitraryPsr3ContextAndIgnoresItSafely(): void
    {
        $logger = new NoopLogger();

        $message = new class() implements Stringable {
            public function __toString(): string
            {
                return 'ignored-message';
            }
        };

        $context = [
            'string' => 'value',
            'int' => 123,
            'bool' => true,
            'float' => 1.25,
            'null' => null,
            'object' => new \stdClass(),
            'closure' => static fn (): string => 'ignored',
            'nested' => [
                'token' => 'ignored-token',
                'headers' => [
                    'authorization' => 'ignored-authorization',
                ],
            ],
        ];

        $logger->emergency($message, $context);
        $logger->alert($message, $context);
        $logger->critical($message, $context);
        $logger->error($message, $context);
        $logger->warning($message, $context);
        $logger->notice($message, $context);
        $logger->info($message, $context);
        $logger->debug($message, $context);
        $logger->log(LogLevel::INFO, $message, $context);

        $this->addToAssertionCount(1);
    }

    public function testNoopTracerReturnsNoopSpanAndRunsSuccessfulCallback(): void
    {
        $tracer = new NoopTracer();

        $span = $tracer->startSpan('foundation.noop.test', [
            'operation' => 'ignored',
        ]);

        self::assertInstanceOf(NoopSpan::class, $span);
        self::assertSame('noop', $span->name());
        self::assertNull($tracer->currentSpan());

        $callbackSpan = null;

        $result = $tracer->inSpan(
            'foundation.noop.callback',
            static function ($span) use (&$callbackSpan): string {
                $callbackSpan = $span;

                return 'ok';
            },
            [
                'outcome' => 'ignored',
            ],
        );

        self::assertSame('ok', $result);
        self::assertInstanceOf(NoopSpan::class, $callbackSpan);
    }

    public function testNoopTracerRethrowsThrowableFromCallback(): void
    {
        $tracer = new NoopTracer();
        $throwable = new \RuntimeException('expected-test-exception');

        try {
            $tracer->inSpan(
                'foundation.noop.throwing_callback',
                static fn (): never => throw $throwable,
            );

            self::fail('Expected throwable was not re-thrown.');
        } catch (\RuntimeException $caught) {
            self::assertSame($throwable, $caught);
        }
    }

    public function testNoopSpanOperationsDoNotThrow(): void
    {
        $span = new NoopSpan('foundation.noop.span');

        $span->setAttribute('operation', 'ignored');
        $span->setAttribute('count', 1);
        $span->setAttributes([
            'nested' => [
                'outcome' => 'ignored',
            ],
        ]);
        $span->addEvent('foundation.noop.event', [
            'outcome' => 'ignored',
        ]);
        $span->recordException(new \RuntimeException('ignored'), [
            'outcome' => 'ignored',
        ]);
        $span->end();
        $span->end();

        self::assertSame('noop', $span->name());
    }

    public function testNoopMeterOperationsDoNotThrow(): void
    {
        $meter = new NoopMeter();

        $meter->increment('foundation.noop.counter');
        $meter->increment('foundation.noop.counter', 2, [
            'operation' => 'noop',
            'status' => 200,
            'outcome' => true,
        ]);

        $meter->observe('foundation.noop.duration_ms', 42);
        $meter->observe('foundation.noop.duration_ms', 42, [
            'operation' => 'noop',
            'status' => 200,
            'outcome' => true,
        ]);

        $this->addToAssertionCount(1);
    }

    public function testNoopErrorReporterDoesNotThrow(): void
    {
        $reporter = new NoopErrorReporter();

        $reporter->report(
            new ErrorDescriptor(
                code: 'foundation.noop.test',
                message: 'Foundation noop test',
                extensions: [
                    'outcome' => 'ignored',
                ],
            ),
        );

        $this->addToAssertionCount(1);
    }

    public function testNoopProfilerReturnsNoopSessionAndRepeatedStopDoesNotThrow(): void
    {
        $profiler = new NoopProfiler();

        $session = $profiler->start('foundation.noop.profile', [
            'operation' => 'ignored',
        ]);

        self::assertInstanceOf(NoopProfilingSession::class, $session);
        self::assertNull($session->stop());
        self::assertNull($session->stop());
    }

    public function testNoopContextPropagationDoesNotThrowAndDoesNotMutateCarrier(): void
    {
        $propagation = new NoopContextPropagation();

        $carrier = [
            'traceparent' => 'ignored-traceparent',
            'tracestate' => [
                'ignored-tracestate',
            ],
        ];

        self::assertSame(
            $carrier,
            $propagation->inject($carrier, [
                'operation' => 'ignored',
            ]),
        );

        self::assertSame([], $propagation->extract($carrier));
    }

    public function testNoopImplementationsDoNotContainOutputSinks(): void
    {
        foreach (self::noopImplementationFiles() as $file) {
            self::assertNoOutputSinksInPhpFile($file);
        }
    }

    /**
     * @return list<string>
     */
    private static function noopImplementationFiles(): array
    {
        $packageRoot = \dirname(__DIR__, 2);

        return [
            $packageRoot . '/src/Logging/NoopLogger.php',
            $packageRoot . '/src/Observability/Errors/NoopErrorReporter.php',
            $packageRoot . '/src/Observability/Metrics/NoopMeter.php',
            $packageRoot . '/src/Observability/Profiling/NoopProfiler.php',
            $packageRoot . '/src/Observability/Profiling/NoopProfilingSession.php',
            $packageRoot . '/src/Observability/Tracing/NoopContextPropagation.php',
            $packageRoot . '/src/Observability/Tracing/NoopSpan.php',
            $packageRoot . '/src/Observability/Tracing/NoopTracer.php',
        ];
    }

    private static function assertNoOutputSinksInPhpFile(string $file): void
    {
        self::assertFileExists($file);

        $code = \file_get_contents($file);

        self::assertIsString($code);

        $tokens = \token_get_all($code);

        foreach ($tokens as $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$id, $text, $line] = $token;

            if ($id === \T_ECHO || $id === \T_PRINT) {
                self::fail(
                    \sprintf(
                        'Forbidden output token %s found in %s on line %d.',
                        \token_name($id),
                        $file,
                        $line,
                    )
                );
            }

            if (!self::isNameToken($id)) {
                continue;
            }

            $normalized = \strtolower(\ltrim($text, '\\'));

            if (\in_array($normalized, ['var_dump', 'fwrite', 'error_log'], true)) {
                self::fail(
                    \sprintf(
                        'Forbidden output function %s found in %s on line %d.',
                        $text,
                        $file,
                        $line,
                    )
                );
            }

            if (\in_array(\strtoupper($text), ['STDOUT', 'STDERR'], true)) {
                self::fail(
                    \sprintf(
                        'Forbidden output stream %s found in %s on line %d.',
                        $text,
                        $file,
                        $line,
                    )
                );
            }
        }

        self::assertTrue(true);
    }

    private static function isNameToken(int $id): bool
    {
        return $id === \T_STRING
            || $id === \T_NAME_FULLY_QUALIFIED
            || $id === \T_NAME_QUALIFIED
            || $id === \T_NAME_RELATIVE;
    }
}
