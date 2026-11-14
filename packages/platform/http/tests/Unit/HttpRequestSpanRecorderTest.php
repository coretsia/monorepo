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

namespace Coretsia\Platform\Http\Tests\Unit;

use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Observability\Tracing\NoopSpan;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpRequestSpanRecorderTest extends TestCase
{
    public function testStartsOnlyCanonicalHttpRequestSpanAndReturnsExactRequestLocalHandle(): void
    {
        $span = $this->createStub(SpanInterface::class);
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')
            ->with('http.request', ['method' => 'OTHER'])->willReturn($span);
        $tracer->expects(self::never())->method('inSpan');
        $tracer->expects(self::never())->method('currentSpan');

        $recorder = new HttpRequestSpanRecorder($tracer, true);
        self::assertSame($span, $recorder->start('OTHER'));
        self::assertSame([], new \ReflectionObject($recorder)->getStaticProperties());
    }

    #[DataProvider('spanCompletions')]
    public function testCompletesEveryStartedSpanOnceAfterSettingOnlySafeAttributes(
        string $method,
        int|string $status,
        string $outcome,
    ): void {
        $operations = [];
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::exactly(3))->method('setAttribute')
            ->willReturnCallback(static function (string $name, mixed $value) use (&$operations): void {
                $operations[] = [$name, $value];
            });
        $span->expects(self::once())->method('end')
            ->willReturnCallback(static function () use (&$operations): void {
                $operations[] = ['end'];
            });
        $span->expects(self::never())->method('recordException');
        $span->expects(self::never())->method('addEvent');
        $span->expects(self::never())->method('setAttributes');
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')
            ->with('http.request', ['method' => $method])->willReturn($span);
        $recorder = new HttpRequestSpanRecorder($tracer, true);

        $handle = $recorder->start($method);
        self::assertSame($span, $handle);
        $recorder->finish($handle, $method, $status, $outcome);
        self::assertSame([
            ['method', $method],
            ['status', $status],
            ['outcome', $outcome],
            ['end'],
        ], $operations);
    }

    /** @return iterable<string, array{string, int|string, string}> */
    public static function spanCompletions(): iterable
    {
        yield 'successful-200' => ['GET', 200, 'success'];
        yield 'short-circuit-403' => ['HEAD', 403, 'handled_error'];
        yield 'bad-json-400' => ['POST', 400, 'handled_error'];
        yield 'too-large-413' => ['PUT', 413, 'handled_error'];
        yield 'rate-limited-429' => ['PATCH', 429, 'handled_error'];
        yield 'problem-details-500' => ['DELETE', 500, 'handled_error'];
        yield 'maintenance-503' => ['OPTIONS', 503, 'handled_error'];
        yield 'unexpected-failure' => ['OTHER', 'unknown', 'fatal_error'];
    }

    public function testTracingDisabledStartsNothingAndDoesNotFinishASpan(): void
    {
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::never())->method('startSpan');
        $recorder = new HttpRequestSpanRecorder($tracer, false);
        self::assertNull($recorder->start('GET'));
        $recorder->finish(null, 'GET', 200, 'success');
    }

    #[DataProvider('invalidMethods')]
    public function testInvalidUnboundedOrUnnormalizedMethodsCannotStartSpan(string $method): void
    {
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::never())->method('startSpan');
        self::assertNull(new HttpRequestSpanRecorder($tracer, true)->start($method));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMethods(): iterable
    {
        yield 'empty' => [''];
        yield 'lowercase' => ['get'];
        yield 'raw-path' => ['GET /private'];
        yield 'too-long' => [\str_repeat('G', 512)];
        yield 'unknown-raw' => ['CUSTOM'];
        yield 'newline' => ["GET\r\nCookie: secret"];
    }

    public function testTracerStartFailureIsSuppressedWithoutSpanRetention(): void
    {
        $tracer = $this->createStub(TracerPortInterface::class);
        $tracer->method('startSpan')->willThrowException(new \RuntimeException('tracer-start-failure'));
        $recorder = new HttpRequestSpanRecorder($tracer, true);
        self::assertNull($recorder->start('GET'));
        $recorder->finish(null, 'GET', 'unknown', 'fatal_error');
    }

    #[DataProvider('failedAttributeNames')]
    public function testAttributeFailureDoesNotSuppressRemainingAttributesOrSingleEnd(string $failedName): void
    {
        $attempted = [];
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::exactly(3))->method('setAttribute')
            ->willReturnCallback(static function (string $name, mixed $value) use (&$attempted, $failedName): void {
                $attempted[] = [$name, $value];
                if ($name === $failedName) {
                    throw new \RuntimeException('test-span-attribute-failure');
                }
            });
        $span->expects(self::once())->method('end');
        $span->expects(self::never())->method('recordException');

        new HttpRequestSpanRecorder(new NoopTracer(), true)
            ->finish($span, 'POST', 500, 'handled_error');
        self::assertSame([
            ['method', 'POST'],
            ['status', 500],
            ['outcome', 'handled_error'],
        ], $attempted);
    }

    /** @return iterable<string, array{string}> */
    public static function failedAttributeNames(): iterable
    {
        yield 'method' => ['method'];
        yield 'status' => ['status'];
        yield 'outcome' => ['outcome'];
    }

    public function testSpanEndFailureIsSuppressedWithoutRecordingThrowable(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::once())->method('end')
            ->willThrowException(new \RuntimeException('span-end-failure'));
        $span->expects(self::never())->method('recordException');
        new HttpRequestSpanRecorder(new NoopTracer(), true)
            ->finish($span, 'OTHER', 'unknown', 'fatal_error');
        self::assertTrue(true);
    }

    #[DataProvider('invalidCompletions')]
    public function testUnsafeOrInconsistentCompletionAttributesAreNotEmitted(
        string $method,
        int|string $status,
        string $outcome,
    ): void {
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::once())->method('end');
        $span->expects(self::never())->method('recordException');
        $events = [];
        $span->method('setAttribute')->willReturnCallback(
            static function (string $name, mixed $value) use (&$events): void {
                $events[$name] = $value;
            },
        );

        new HttpRequestSpanRecorder(new NoopTracer(), true)->finish($span, $method, $status, $outcome);
        self::assertArrayNotHasKey('status', $events);
        self::assertArrayNotHasKey('outcome', $events);
        if ($method === 'GET') {
            self::assertSame(['method' => 'GET'], $events);
        } else {
            self::assertSame([], $events);
        }
    }

    public function testUnsafeMethodIsOmittedEvenWhenFinalCompletionValuesAreSafe(): void
    {
        $attributes = [];
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::once())->method('end');
        $span->method('setAttribute')->willReturnCallback(
            static function (string $name, mixed $value) use (&$attributes): void {
                $attributes[$name] = $value;
            },
        );

        new HttpRequestSpanRecorder(new NoopTracer(), true)
            ->finish($span, 'GET /user/1', 500, 'handled_error');
        self::assertSame(['status' => 500, 'outcome' => 'handled_error'], $attributes);
    }

    /** @return iterable<string, array{string, int|string, string}> */
    public static function invalidCompletions(): iterable
    {
        yield 'invalid-status' => ['GET', 600, 'handled_error'];
        yield 'status-string' => ['GET', '500', 'handled_error'];
        yield 'wrong-outcome' => ['GET', 500, 'success'];
        yield 'fatal-with-response' => ['GET', 500, 'fatal_error'];
        yield 'success-without-response' => ['GET', 'unknown', 'success'];
        yield 'raw-exception-outcome' => ['GET', 'unknown', 'token-secret'];
    }

    public function testTwoRequestLocalSpanHandlesAreNeverStoredByRecorder(): void
    {
        $first = $this->createMock(SpanInterface::class);
        $second = $this->createMock(SpanInterface::class);
        $first->expects(self::once())->method('end');
        $second->expects(self::once())->method('end');
        $spans = [$first, $second];
        $tracer = $this->createStub(TracerPortInterface::class);
        $tracer->method('startSpan')->willReturnCallback(
            static function () use (&$spans): SpanInterface {
                return \array_shift($spans);
            },
        );
        $recorder = new HttpRequestSpanRecorder($tracer, true);
        $one = $recorder->start('GET');
        $two = $recorder->start('POST');
        self::assertSame($first, $one);
        self::assertSame($second, $two);
        $recorder->finish($one, 'GET', 200, 'success');
        $recorder->finish($two, 'POST', 400, 'handled_error');
        self::assertSame([], $spans);

        $properties = \array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            new \ReflectionObject($recorder)->getProperties(),
        );
        \sort($properties);
        self::assertSame(['enabled', 'tracer'], $properties);
    }

    public function testFoundationNoopTracingIsCompatibleWithoutParentLinkageAssumptions(): void
    {
        $recorder = new HttpRequestSpanRecorder(new NoopTracer(), true);
        $span = $recorder->start('GET');
        self::assertInstanceOf(NoopSpan::class, $span);
        self::assertSame('noop', $span->name());
        $recorder->finish($span, 'GET', 200, 'success');
        self::assertNull(new NoopTracer()->currentSpan());
    }
}
