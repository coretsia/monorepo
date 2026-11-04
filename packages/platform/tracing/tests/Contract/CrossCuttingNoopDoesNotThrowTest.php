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

namespace Coretsia\Platform\Tracing\Tests\Contract;

use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Foundation\Observability\Tracing\NoopSpan;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation;
use PHPUnit\Framework\TestCase;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testFoundationNoopTracerAndSpanDoNotRetainInstrumentation(): void
    {
        $tracer = new NoopTracer();
        $span = $tracer->startSpan('caller-supplied', ['safe' => 'value']);

        self::assertInstanceOf(TracerPortInterface::class, $tracer);
        self::assertInstanceOf(SpanInterface::class, $span);
        self::assertInstanceOf(NoopSpan::class, $span);
        self::assertSame('noop', $span->name());
        self::assertNull($tracer->currentSpan());

        $span->setAttribute('safe', 'value');
        $span->setAttributes(['safe' => ['nested' => 'value']]);
        $span->addEvent('operation.complete', ['safe' => true]);
        $span->recordException(new \RuntimeException('synthetic private message'));
        $span->end();
        $span->end();
        $span->end();

        self::assertSame('noop', $span->name());
        self::assertNull($tracer->currentSpan());
        self::assertSame([], new \ReflectionClass($tracer)->getProperties());
        self::assertSame([], new \ReflectionClass($span)->getProperties());
    }

    public function testInSpanReturnsCallbackValueAndPreservesOriginalThrowable(): void
    {
        $tracer = new NoopTracer();
        $result = $tracer->inSpan('operation.safe', static function (SpanInterface $span): string {
            self::assertSame('noop', $span->name());

            return 'completed';
        });

        self::assertSame('completed', $result);
        self::assertNull($tracer->currentSpan());

        $original = new \RuntimeException('synthetic callback failure');

        try {
            $tracer->inSpan('operation.safe', static function (SpanInterface $span) use ($original): never {
                self::assertSame('noop', $span->name());
                throw $original;
            });
            self::fail('The original callback exception must escape.');
        } catch (\RuntimeException $caught) {
            self::assertSame($original, $caught);
        }

        self::assertNull($tracer->currentSpan());

        $error = new \Error('synthetic callback error');

        try {
            $tracer->inSpan('operation.safe', static function (SpanInterface $span) use ($error): never {
                self::assertSame('noop', $span->name());

                throw $error;
            });

            self::fail('The original callback error must escape.');
        } catch (\Error $caught) {
            self::assertSame($error, $caught);
        }

        self::assertNull($tracer->currentSpan());
    }

    public function testFoundationNoopPropagationRemainsPassThroughAndNotASanitizer(): void
    {
        $propagation = new NoopContextPropagation();
        $carrier = [
            'traceparent' => 'invalid',
            'tracestate' => 'vendor=opaque',
            'x-request-id' => ['safe'],
        ];

        self::assertInstanceOf(ContextPropagationInterface::class, $propagation);
        self::assertSame([], $propagation->extract($carrier));
        self::assertSame($carrier, $propagation->inject($carrier));
        self::assertSame($carrier, $propagation->inject($carrier, ['trace_id' => 'unused']));
        self::assertSame([], $propagation->extract([]));
    }

    public function testNoopAndW3cAreStatelessAndEmitNoOutputOrDiagnostics(): void
    {
        $tracer = new NoopTracer();
        $noop = new NoopContextPropagation();
        $w3c = new W3cTraceContextPropagation();
        $originalLevel = \error_reporting();
        $warnings = [];
        \error_reporting(E_ALL);
        \set_error_handler(static function (int $severity) use (&$warnings): bool {
            $warnings[] = $severity;

            return true;
        });
        \ob_start();

        try {
            for ($iteration = 0; $iteration < 3; $iteration++) {
                $span = $tracer->startSpan('operation.safe');
                $span->end();
                $span->end();
                self::assertNull($tracer->currentSpan());
                self::assertSame([], $noop->extract(['traceparent' => 'invalid']));
                self::assertSame([], $w3c->extract(['traceparent' => 'invalid']));
                self::assertSame([], $w3c->inject([], []));
            }
        } finally {
            $output = \ob_get_clean();
            \restore_error_handler();
            \error_reporting($originalLevel);
        }

        self::assertSame('', $output);
        self::assertSame([], $warnings);
        self::assertSame([], new \ReflectionClass($w3c)->getProperties());
        self::assertSame([], new \ReflectionClass($noop)->getProperties());
    }
}
