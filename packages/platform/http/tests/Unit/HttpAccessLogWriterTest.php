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

use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class HttpAccessLogWriterTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    #[DataProvider('safeCompletionRecords')]
    public function testEmitsExactlyOneSafeCompletionRecordWithFixedUnknownRoute(
        string $method,
        int|string $status,
        string $outcome,
        ?string $correlationId,
    ): void {
        $expected = [
            'method' => $method,
            'status' => $status,
            'outcome' => $outcome,
            'duration_ms' => 37,
            'route_template' => 'unknown',
        ];
        if ($correlationId !== null) {
            $expected['correlation_id'] = $correlationId;
        }
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('http.request.completed', $expected);
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'debug', 'log'] as $loggerMethod) {
            $logger->expects(self::never())->method($loggerMethod);
        }

        new HttpAccessLogWriter($logger, true)->write($method, $status, $outcome, 37, $correlationId);
        self::assertSame(
            ['method', 'status', 'outcome', 'duration_ms', 'route_template'],
            \array_slice(\array_keys($expected), 0, 5),
        );
    }

    /** @return iterable<string, array{string, int|string, string, ?string}> */
    public static function safeCompletionRecords(): iterable
    {
        yield '200' => ['GET', 200, 'success', self::CORRELATION];
        yield 'short-circuit-403' => ['POST', 403, 'handled_error', null];
        yield 'problem-json-400' => ['OTHER', 400, 'handled_error', self::CORRELATION];
        yield 'problem-payload-413' => ['PUT', 413, 'handled_error', null];
        yield 'rate-limit-429' => ['OPTIONS', 429, 'handled_error', null];
        yield 'problem-500' => ['PATCH', 500, 'handled_error', null];
        yield 'maintenance-503' => ['DELETE', 503, 'handled_error', null];
        yield 'uncaught-throwable' => ['OTHER', 'unknown', 'fatal_error', null];
    }

    #[DataProvider('invalidCompletionInputs')]
    public function testUnsafeInputsCannotBeExportedToLogger(
        string $method,
        int|string $status,
        string $outcome,
        int $duration,
    ): void {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');
        new HttpAccessLogWriter($logger, true)->write($method, $status, $outcome, $duration, self::CORRELATION);
    }

    /** @return iterable<string, array{string, int|string, string, int}> */
    public static function invalidCompletionInputs(): iterable
    {
        yield 'raw-method-path' => ['GET /private?token=secret', 200, 'success', 1];
        yield 'unbounded-method' => [\str_repeat('G', 300), 200, 'success', 1];
        yield 'lowercase-method' => ['get', 200, 'success', 1];
        yield 'unknown-method-not-normalized' => ['CUSTOM', 200, 'success', 1];
        yield 'bad-status' => ['GET', 600, 'handled_error', 1];
        yield 'text-status' => ['GET', '500', 'handled_error', 1];
        yield 'fatal-with-status' => ['GET', 500, 'fatal_error', 1];
        yield 'handled-with-200' => ['GET', 200, 'handled_error', 1];
        yield 'success-with-500' => ['GET', 500, 'success', 1];
        yield 'unknown-with-success' => ['GET', 'unknown', 'success', 1];
        yield 'outcome-injection' => ['GET', 200, 'success\r\nCookie: secret', 1];
        yield 'negative-duration' => ['GET', 200, 'success', -1];
        yield 'oversized-duration' => ['GET', 200, 'success', 4_294_967_296];
    }

    #[DataProvider('invalidCorrelations')]
    public function testInvalidCorrelationValuesAreOmittedRatherThanLogged(?string $correlation): void
    {
        $expected = [
            'method' => 'GET',
            'status' => 200,
            'outcome' => 'success',
            'duration_ms' => 0,
            'route_template' => 'unknown',
        ];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('http.request.completed', $expected);
        new HttpAccessLogWriter($logger, true)->write('GET', 200, 'success', 0, $correlation);
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidCorrelations(): iterable
    {
        yield 'absent' => [null];
        yield 'from-header' => ['unauthorized-supplied-value'];
        yield 'request-id' => ['req=secret'];
        yield 'oversize' => [\str_repeat('A', 128)];
        yield 'newline' => ["01ARZ3NDEKTSV4RRFFQ69G5FAV\r\nSet-Cookie: bad"];
    }

    public function testDisabledAccessLoggingDoesNotInvokeAnyLoggerMethod(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');
        $logger->expects(self::never())->method('log');
        new HttpAccessLogWriter($logger, false)->write('GET', 200, 'success', 0);
    }

    public function testLoggingFailureIsIsolatedAndDoesNotRetainRequestState(): void
    {
        $calls = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('info')->willReturnCallback(
            static function (string $event, array $context) use (&$calls): void {
                $calls[] = [$event, $context];
                if (\count($calls) === 1) {
                    throw new \RuntimeException('optional-logger-failed');
                }
            },
        );
        $writer = new HttpAccessLogWriter($logger, true);
        $writer->write('GET', 200, 'success', 1, self::CORRELATION);
        $writer->write('OTHER', 'unknown', 'fatal_error', 2);

        self::assertCount(2, $calls);
        self::assertSame(self::CORRELATION, $calls[0][1]['correlation_id']);
        self::assertArrayNotHasKey('correlation_id', $calls[1][1]);
        self::assertSame('unknown', $calls[1][1]['route_template']);
        self::assertSame('unknown', $calls[1][1]['status']);
        foreach ($calls as [$event, $context]) {
            self::assertSame('http.request.completed', $event);
            self::assertSame('unknown', $context['route_template']);
            self::assertSame(
                [],
                \array_intersect(
                    [
                        'path',
                        'route',
                        'request',
                        'headers',
                        'cookie',
                        'query',
                        'body',
                        'exception',
                        'authorization',
                        'token',
                    ],
                    \array_keys($context),
                )
            );
        }

        $properties = \array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            new \ReflectionObject($writer)->getProperties(),
        );
        \sort($properties);
        self::assertSame(['enabled', 'logger'], $properties);
    }

    public function testFoundationNoopLoggerPreservesNoopBehavior(): void
    {
        $writer = new HttpAccessLogWriter(new NoopLogger(), true);
        $writer->write('GET', 200, 'success', 0, self::CORRELATION);
        $writer->write('OTHER', 'unknown', 'fatal_error', 4);
        self::assertInstanceOf(HttpAccessLogWriter::class, $writer);
    }
}
