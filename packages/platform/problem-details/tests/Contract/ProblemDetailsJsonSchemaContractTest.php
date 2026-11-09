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

namespace Coretsia\Platform\ProblemDetails\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\ProblemDetails;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;

final class ProblemDetailsJsonSchemaContractTest extends TestCase
{
    public function testProblemDetailsHasExactImmutablePublicSchemaAndNoTitleOverride(): void
    {
        $reflection = new ReflectionClass(ProblemDetails::class);
        $constructor = $reflection->getConstructor();

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertNotNull($constructor);
        self::assertCount(1, $constructor->getParameters());
        self::assertSame('status', $constructor->getParameters()[0]->getName());
        self::assertSame('int', (string) $constructor->getParameters()[0]->getType());
        self::assertSame(
            ['type', 'title', 'status'],
            \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
        self::assertSame(['status', 'title', 'type'], \array_keys(new ProblemDetails(404)->toArray()));
        self::assertSame(
            ['status' => 404, 'title' => 'Not Found', 'type' => 'about:blank'],
            new ProblemDetails(404)->toArray(),
        );
    }

    public function testCallerCannotInjectArbitraryTitleThroughNamedConstructorArgument(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Unknown named parameter $title');

        new ReflectionClass(ProblemDetails::class)->newInstanceArgs([
            'status' => 403,
            'title' => 'SYNTHETIC_PRIVATE_TITLE',
        ]);
    }

    #[DataProvider('canonicalStatusCases')]
    public function testCanonicalStatusMappingAndRfc9457BaselineAreFixed(
        ?int $hint,
        int $status,
        string $title,
    ): void {
        $descriptor = new ErrorDescriptor(
            code: 'SYNTHETIC_SECRET_CODE',
            message: 'SYNTHETIC_PRIVATE_MESSAGE',
            httpStatus: $hint,
            extensions: ['note' => 'SYNTHETIC_PRIVATE_EXTENSION'],
        );
        $adapter = new ErrorDescriptorToProblemDetailsAdapter();
        $problem = $adapter->map($descriptor);
        $expected = ['status' => $status, 'title' => $title, 'type' => 'about:blank'];

        self::assertSame($expected, $problem->toArray());
        self::assertSame($expected, new ProblemDetails($status)->toArray());
        self::assertSame(['status', 'title', 'type'], \array_keys($problem->toArray()));
        $body = new ProblemDetailsJsonRenderer()->render($problem);
        self::assertSame(\json_encode($expected, \JSON_THROW_ON_ERROR) . "\n", $body);
        self::assertSame($expected, \json_decode($body, true, flags: \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('SYNTHETIC_', $body);
        foreach (['detail', 'instance', 'extensions', 'message', 'code', 'severity', 'schemaVersion'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $problem->toArray());
        }
    }

    #[DataProvider('invalidDirectStatuses')]
    public function testDirectConstructionRejectsNonErrorStatuses(int $status): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProblemDetails($status);
    }

    public function testHttpResponseStatusMatchesPublicJsonStatusWithoutDisclosingRequestOrThrowable(): void
    {
        $primary = new \RuntimeException('SYNTHETIC_THROWABLE_PRIVATE');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::never())->method('getBody');
        $request->expects(self::never())->method('getParsedBody');
        $request->expects(self::never())->method('getHeaders');
        $downstream = $this->createMock(RequestHandlerInterface::class);
        $downstream->expects(self::once())->method('handle')->with(self::identicalTo($request))
            ->willThrowException($primary);
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')->with(self::identicalTo($primary))
            ->willReturn(
                new ErrorDescriptor(
                    code: 'SYNTHETIC_SECRET_CODE',
                    message: 'SYNTHETIC_PII_PRIVATE',
                    httpStatus: 429,
                    extensions: ['private' => 'SYNTHETIC_TOKEN_PRIVATE'],
                ),
            );

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(429);
        $response->expects(self::exactly(2))->method('withHeader')->willReturnSelf();
        $response->expects(self::once())->method('withBody')->willReturnSelf();
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(429)->willReturn($response);
        $body = null;
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')->willReturnCallback(
            function (string $content) use (&$body): StreamInterface {
                $body = $content;

                $stream = $this->createStub(StreamInterface::class);

                if (!$stream instanceof StreamInterface) {
                    throw new \LogicException('Stream stub must implement StreamInterface.');
                }

                return $stream;
            },
        );

        $actual = new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $streams,
        )->process($request, $downstream);

        self::assertSame($response, $actual);
        self::assertIsString($body);
        self::assertSame(429, $actual->getStatusCode());
        self::assertSame(\json_decode($body, true, flags: \JSON_THROW_ON_ERROR)['status'], $actual->getStatusCode());
        self::assertSame("{\"status\":429,\"title\":\"Too Many Requests\",\"type\":\"about:blank\"}\n", $body);
        self::assertSame('application/problem+json', new ProblemDetailsJsonRenderer()->contentType());
        self::assertStringNotContainsString('SYNTHETIC_', $body);
    }

    /**
     * @return iterable<string, array{?int, int, string}>
     */
    public static function canonicalStatusCases(): iterable
    {
        yield '400' => [400, 400, 'Bad Request'];
        yield '401' => [401, 401, 'Unauthorized'];
        yield '404' => [404, 404, 'Not Found'];
        yield '413' => [413, 413, 'Content Too Large'];
        yield '422' => [422, 422, 'Unprocessable Content'];
        yield '429' => [429, 429, 'Too Many Requests'];
        yield '500' => [500, 500, 'Internal Server Error'];
        yield '503' => [503, 503, 'Service Unavailable'];
        yield '511' => [511, 511, 'Network Authentication Required'];
        yield 'nonstandard 4xx' => [430, 430, 'HTTP Error'];
        yield 'nonstandard 5xx' => [599, 599, 'HTTP Error'];
        yield 'null status' => [null, 500, 'Internal Server Error'];
        yield 'informational status' => [101, 500, 'Internal Server Error'];
        yield 'redirect status' => [302, 500, 'Internal Server Error'];
        yield 'highest non-error status' => [399, 500, 'Internal Server Error'];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidDirectStatuses(): iterable
    {
        yield 'informational' => [100];
        yield 'redirect' => [302];
        yield 'below errors' => [399];
        yield 'above errors' => [600];
    }
}
