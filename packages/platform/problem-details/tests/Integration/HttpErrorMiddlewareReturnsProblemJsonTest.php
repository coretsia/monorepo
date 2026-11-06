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

namespace Coretsia\Platform\ProblemDetails\Tests\Integration;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpErrorMiddlewareReturnsProblemJsonTest extends TestCase
{
    private const string EMERGENCY_BODY = "{\"status\":500,\"title\":\"Internal Server Error\",\"type\":\"about:blank\"}\n";

    public function testSuccessfulDownstreamResponseIsReturnedUnchanged(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $expected = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($expected);
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::never())->method('handle');
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::never())->method('createResponse');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');

        self::assertSame($expected, self::middleware($errors, $responses, $streams)->process($request, $handler));
    }

    public function testCaughtDownstreamThrowableIsHandledExactlyOnceAndRenderedAsSafeProblemJson(): void
    {
        $primary = new \RuntimeException('SYNTHETIC_DOWNSTREAM_PRIVATE');
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())
            ->method('handle')
            ->with(self::identicalTo($primary))
            ->willReturn(new ErrorDescriptor('SYNTHETIC_OWNER', 'SYNTHETIC_DESCRIPTOR_PRIVATE', httpStatus: 422));

        [$response, $responses, $streams, $headers] = $this->responseFixture(
            422,
            "{\"status\":422,\"title\":\"Unprocessable Content\",\"type\":\"about:blank\"}\n",
        );
        $handler = $this->throwingHandler($primary);
        $request = $this->createStub(ServerRequestInterface::class);

        self::assertSame($response, self::middleware($errors, $responses, $streams)->process($request, $handler));
        self::assertSame([
            ['Content-Type', 'application/problem+json'],
            ['Cache-Control', 'no-store'],
        ], $headers->items);
    }

    public function testAbsentHttpStatusHintUsesSafe500WithExactlyOneHandlerCall(): void
    {
        $primary = new \RuntimeException('SYNTHETIC_DOWNSTREAM_PRIVATE');
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')->with(self::identicalTo($primary))
            ->willReturn(new ErrorDescriptor('SYNTHETIC_OWNER', 'SYNTHETIC_DESCRIPTOR_PRIVATE'));
        [$response, $responses, $streams, $headers] = $this->responseFixture(500, self::EMERGENCY_BODY);

        self::assertSame(
            $response,
            self::middleware($errors, $responses, $streams)->process(
                $this->createStub(ServerRequestInterface::class),
                $this->throwingHandler($primary),
            ),
        );
        self::assertSame([
            ['Content-Type', 'application/problem+json'],
            ['Cache-Control', 'no-store'],
        ], $headers->items);
    }

    public function testCanonicalHandlerFailureUsesFixedEmergencyBodyWithoutRetryOrDisclosure(): void
    {
        $primary = new \RuntimeException('SYNTHETIC_DOWNSTREAM_PRIVATE');
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')->with(self::identicalTo($primary))
            ->willThrowException(new \RuntimeException('SYNTHETIC_HANDLER_PRIVATE'));
        [$response, $responses, $streams, $headers] = $this->responseFixture(500, self::EMERGENCY_BODY);

        $warnings = [];
        \set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        });
        \ob_start();
        try {
            $actual = self::middleware($errors, $responses, $streams)->process(
                $this->createStub(ServerRequestInterface::class),
                $this->throwingHandler($primary),
            );
        } finally {
            $output = (string) \ob_get_clean();
            \restore_error_handler();
        }

        self::assertSame($response, $actual);
        self::assertSame('', $output);
        self::assertSame([], $warnings);
        self::assertSame([
            ['Content-Type', ProblemDetailsJsonRenderer::CONTENT_TYPE],
            ['Cache-Control', 'no-store'],
        ], $headers->items);
        self::assertStringNotContainsString('SYNTHETIC_', self::EMERGENCY_BODY);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function transportFailureStages(): iterable
    {
        yield 'create response' => ['create-response'];
        yield 'create stream' => ['create-stream'];
        yield 'set headers' => ['with-header'];
        yield 'set body' => ['with-body'];
    }

    #[DataProvider('transportFailureStages')]
    public function testTransportFailuresPreserveTheExactPrimaryThrowable(string $stage): void
    {
        $primary = new \RuntimeException('SYNTHETIC_PRIMARY_PRIVATE');
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')->with(self::identicalTo($primary))
            ->willReturn(new ErrorDescriptor('SYNTHETIC_OWNER', 'SYNTHETIC_DESCRIPTOR_PRIVATE', httpStatus: 503));

        $responses = $this->createMock(ResponseFactoryInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $stream = $this->createStub(StreamInterface::class);
        $transportFailure = new \RuntimeException('SYNTHETIC_TRANSPORT_PRIVATE');

        if ($stage === 'create-response') {
            $responses
                ->expects(self::once())
                ->method('createResponse')
                ->with(503)
                ->willThrowException($transportFailure);
            $streams->expects(self::never())->method('createStream');
        } elseif ($stage === 'create-stream') {
            $response = $this->createStub(ResponseInterface::class);
            $responses->expects(self::once())->method('createResponse')->with(503)->willReturn($response);
            $streams->expects(self::once())->method('createStream')->willThrowException($transportFailure);
        } else {
            $response = $this->createMock(ResponseInterface::class);
            $responses->expects(self::once())->method('createResponse')->with(503)->willReturn($response);
            $streams->expects(self::once())->method('createStream')->willReturn($stream);
            if ($stage === 'with-header') {
                $response->expects(self::once())->method('withHeader')->willThrowException($transportFailure);
            } else {
                $response->expects(self::exactly(2))->method('withHeader')->willReturnSelf();
                $response
                    ->expects(self::once())
                    ->method('withBody')
                    ->with($stream)
                    ->willThrowException($transportFailure);
            }
        }

        try {
            self::middleware($errors, $responses, $streams)->process(
                $this->createStub(ServerRequestInterface::class),
                $this->throwingHandler($primary),
            );
        } catch (\RuntimeException $caught) {
            self::assertSame($primary, $caught);

            return;
        }
        self::fail('Transport failure must rethrow the original downstream Throwable.');
    }

    public function testEmergencyTransportFailurePreservesPrimaryThrowableWithoutHandlerRetry(): void
    {
        $primary = new \RuntimeException('SYNTHETIC_PRIMARY_PRIVATE');
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')->willThrowException(
            new \RuntimeException('SYNTHETIC_HANDLER_PRIVATE'),
        );
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(500)->willThrowException(
            new \RuntimeException('SYNTHETIC_FACTORY_PRIVATE'),
        );
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');

        try {
            self::middleware($errors, $responses, $streams)->process(
                $this->createStub(ServerRequestInterface::class),
                $this->throwingHandler($primary),
            );
        } catch (\RuntimeException $caught) {
            self::assertSame($primary, $caught);

            return;
        }
        self::fail('Emergency transport failure must preserve primary exception precedence.');
    }

    public function testEmergencyStreamFactoryFailurePreservesPrimaryThrowableWithoutHandlerRetry(): void
    {
        $primary = new \RuntimeException('SYNTHETIC_PRIMARY_PRIVATE');
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')->with(self::identicalTo($primary))
            ->willThrowException(new \RuntimeException('SYNTHETIC_HANDLER_PRIVATE'));

        $response = $this->createStub(ResponseInterface::class);
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(500)->willReturn($response);

        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')->with(self::EMERGENCY_BODY)
            ->willThrowException(new \RuntimeException('SYNTHETIC_STREAM_PRIVATE'));

        try {
            self::middleware($errors, $responses, $streams)->process(
                $this->createStub(ServerRequestInterface::class),
                $this->throwingHandler($primary),
            );
        } catch (\RuntimeException $caught) {
            self::assertSame($primary, $caught);

            return;
        }

        self::fail('Emergency stream factory failure must preserve the primary Throwable.');
    }

    private static function middleware(
        ErrorHandlerInterface $errors,
        ResponseFactoryInterface $responses,
        StreamFactoryInterface $streams,
    ): HttpErrorHandlingMiddleware {
        return new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $streams,
        );
    }

    private function throwingHandler(\Throwable $primary): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willThrowException($primary);

        return $handler;
    }

    /**
     * @return array{
     *     ResponseInterface,
     *     ResponseFactoryInterface,
     *     StreamFactoryInterface,
     *     object{items:list<array{string,string}>}
     * }
     */
    private function responseFixture(int $status, string $body): array
    {
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createStub(StreamInterface::class);
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $headers = new class() {
            /** @var list<array{string,string}> */
            public array $items = [];
        };

        $responses->expects(self::once())->method('createResponse')->with($status)->willReturn($response);
        $streams->expects(self::once())->method('createStream')->with($body)->willReturn($stream);
        $response->expects(self::exactly(2))->method('withHeader')
            ->willReturnCallback(static function (
                string $name,
                string $value,
            ) use ($headers, $response): ResponseInterface {
                $headers->items[] = [$name, $value];

                return $response;
            });
        $response->expects(self::once())->method('withBody')->with($stream)->willReturn($response);

        return [$response, $responses, $streams, $headers];
    }
}
