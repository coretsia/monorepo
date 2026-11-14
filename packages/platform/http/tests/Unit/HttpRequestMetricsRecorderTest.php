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

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpRequestMetricsRecorderTest extends TestCase
{
    #[DataProvider('validCompletions')]
    public function testEmitsExactlyTheTwoCatalogMetricsWithSafeLabels(
        string $method,
        int|string $status,
        string $outcome,
    ): void {
        $labels = ['method' => $method, 'status' => $status, 'outcome' => $outcome];
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')
            ->with('http.request_total', 1, $labels);
        $meter->expects(self::once())->method('observe')
            ->with('http.request_duration_ms', 17, $labels);

        new HttpRequestMetricsRecorder($meter, true)->record($method, $status, $outcome, 17);
        self::assertSame(['method', 'status', 'outcome'], \array_keys($labels));
    }

    /** @return iterable<string, array{string, int|string, string}> */
    public static function validCompletions(): iterable
    {
        yield 'get-200' => ['GET', 200, 'success'];
        yield 'head-204' => ['HEAD', 204, 'success'];
        yield 'post-400' => ['POST', 400, 'handled_error'];
        yield 'put-413' => ['PUT', 413, 'handled_error'];
        yield 'patch-429' => ['PATCH', 429, 'handled_error'];
        yield 'delete-500' => ['DELETE', 500, 'handled_error'];
        yield 'options-503' => ['OPTIONS', 503, 'handled_error'];
        yield 'trace-302' => ['TRACE', 302, 'success'];
        yield 'connect-200' => ['CONNECT', 200, 'success'];
        yield 'unsupported-method' => ['OTHER', 200, 'success'];
        yield 'uncaught-throwable' => ['OTHER', 'unknown', 'fatal_error'];
    }

    #[DataProvider('invalidCompletions')]
    public function testRejectsUnsafeUnboundedOrInconsistentRecorderInputs(
        string $method,
        int|string $status,
        string $outcome,
        int $duration,
    ): void {
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::never())->method('increment');
        $meter->expects(self::never())->method('observe');
        new HttpRequestMetricsRecorder($meter, true)->record($method, $status, $outcome, $duration);
    }

    /** @return iterable<string, array{string, int|string, string, int}> */
    public static function invalidCompletions(): iterable
    {
        yield 'raw-path-in-method' => ['GET /private/path?secret=x', 200, 'success', 12];
        yield 'raw-request-method' => ['X-UNKNOWN', 200, 'success', 12];
        yield 'incorrect-method-case' => ['get', 200, 'success', 12];
        yield 'method-with-newline' => ["GET\r\nCookie: secret", 200, 'success', 12];
        yield 'status-text' => ['GET', '500', 'handled_error', 12];
        yield 'status-low' => ['GET', 99, 'success', 12];
        yield 'status-high' => ['GET', 600, 'handled_error', 12];
        yield 'handled-error-with-success' => ['GET', 200, 'handled_error', 12];
        yield 'success-with-500' => ['GET', 500, 'success', 12];
        yield 'fatal-with-known-status' => ['GET', 500, 'fatal_error', 12];
        yield 'unknown-with-success' => ['GET', 'unknown', 'success', 12];
        yield 'unknown-with-handled' => ['GET', 'unknown', 'handled_error', 12];
        yield 'raw-exception-outcome' => ['GET', 500, 'secret-exception', 12];
        yield 'negative-duration' => ['GET', 200, 'success', -1];
        yield 'overflow-duration' => ['GET', 200, 'success', 4_294_967_296];
    }

    public function testDisabledRecorderDoesNotTouchMeter(): void
    {
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::never())->method('increment');
        $meter->expects(self::never())->method('observe');
        new HttpRequestMetricsRecorder($meter, false)->record('GET', 200, 'success', 1);
    }

    public function testCounterFailureDoesNotSuppressIndependentDurationObservation(): void
    {
        $failure = new \RuntimeException('meter-counter-failed');
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->willThrowException($failure);
        $meter->expects(self::once())->method('observe')
            ->with('http.request_duration_ms', 42, [
                'method' => 'OTHER',
                'status' => 'unknown',
                'outcome' => 'fatal_error',
            ]);

        new HttpRequestMetricsRecorder($meter, true)->record('OTHER', 'unknown', 'fatal_error', 42);
    }

    public function testDurationFailureCannotReplaceSuccessfulHttpCompletion(): void
    {
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment');
        $meter->expects(self::once())->method('observe')
            ->willThrowException(new \RuntimeException('meter-duration-failed'));

        new HttpRequestMetricsRecorder($meter, true)->record('POST', 413, 'handled_error', 0);
        self::assertTrue(true);
    }

    public function testEachEnabledInvocationAttemptsOnlyTheTwoApprovedMetrics(): void
    {
        $events = [];
        $meter = $this->createStub(MeterPortInterface::class);
        $meter->method('increment')->willReturnCallback(
            static function (string $name, int $delta, array $labels) use (&$events): void {
                $events[] = [$name, $delta, $labels];
            },
        );
        $meter->method('observe')->willReturnCallback(
            static function (string $name, int $value, array $labels) use (&$events): void {
                $events[] = [$name, $value, $labels];
            },
        );
        $recorder = new HttpRequestMetricsRecorder($meter, true);
        $recorder->record('OTHER', 'unknown', 'fatal_error', 0);
        $recorder->record('GET', 200, 'success', 11);

        self::assertCount(4, $events);
        self::assertSame([
            'http.request_total',
            'http.request_duration_ms',
            'http.request_total',
            'http.request_duration_ms',
        ], \array_column($events, 0));
        foreach ($events as [$name, $value, $labels]) {
            self::assertContains($name, ['http.request_total', 'http.request_duration_ms']);
            self::assertIsInt($value);
            self::assertSame(['method', 'status', 'outcome'], \array_keys($labels));
            self::assertArrayNotHasKey('correlation_id', $labels);
            self::assertArrayNotHasKey('route', $labels);
            self::assertArrayNotHasKey('path', $labels);
        }
    }

    public function testFoundationNoopMeterAcceptsRepeatInvocationsWithoutState(): void
    {
        $recorder = new HttpRequestMetricsRecorder(new NoopMeter(), true);
        foreach ([0, 15, 4_294_967_295] as $duration) {
            $recorder->record('GET', 200, 'success', $duration);
        }
        $properties = \array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            new \ReflectionObject($recorder)->getProperties(),
        );
        \sort($properties);
        self::assertSame(['enabled', 'meter'], $properties);
    }
}
