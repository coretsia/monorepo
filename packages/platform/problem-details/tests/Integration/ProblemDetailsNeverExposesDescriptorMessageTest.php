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
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\ProblemDetails;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ProblemDetailsNeverExposesDescriptorMessageTest extends TestCase
{
    public function testDefaultConfigurationDoesNotEnablePublicDisclosure(): void
    {
        $packageRoot = \dirname(__DIR__, 2);

        self::assertSame([], require $packageRoot . '/config/problem_details.php');
        self::assertSame([
            'schemaVersion' => 1,
            'configRoot' => 'problem_details',
            'additionalKeys' => false,
            'keys' => [],
        ], require $packageRoot . '/config/rules.php');
    }

    public function testSensitiveDescriptorAndRequestDataNeverReachRepeatedHttpResponses(): void
    {
        $primary = new \RuntimeException('SYNTHETIC_THROWABLE_PRIVATE');
        $descriptor = new ErrorDescriptor(
            code: 'SYNTHETIC_ERROR_CODE',
            message: 'SYNTHETIC_MESSAGE_PRIVATE',
            severity: ErrorSeverity::Critical,
            httpStatus: 403,
            extensions: [
                'safe_note' => 'SYNTHETIC_EXTENSION_PRIVATE',
                'details' => ['example' => 'SYNTHETIC_NESTED_PRIVATE'],
            ],
        );
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::exactly(12))->method('handle')->with(self::identicalTo($primary))
            ->willReturn($descriptor);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::never())->method('getBody');
        $request->expects(self::never())->method('getHeaders');
        $request->expects(self::never())->method('getParsedBody');
        $request->expects(self::never())->method('getCookieParams');
        $request->expects(self::never())->method('getQueryParams');
        $request->expects(self::never())->method('getServerParams');
        $request->expects(self::never())->method('getAttributes');
        $downstream = $this->createMock(RequestHandlerInterface::class);
        $downstream->expects(self::exactly(12))->method('handle')->with(self::identicalTo($request))
            ->willThrowException($primary);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(24))->method('withHeader')->willReturnSelf();
        $response->expects(self::exactly(12))->method('withBody')->willReturnSelf();
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::exactly(12))->method('createResponse')->with(403)->willReturn($response);
        $bodies = [];
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::exactly(12))->method('createStream')->willReturnCallback(
            function (string $body) use (&$bodies): StreamInterface {
                $bodies[] = $body;

                return $this->createStub(StreamInterface::class);
            },
        );
        $middleware = new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $streams,
        );

        for ($attempt = 0; $attempt < 12; ++$attempt) {
            self::assertSame($response, $middleware->process($request, $downstream));
        }

        self::assertCount(12, $bodies);
        $expected = "{\"status\":403,\"title\":\"Forbidden\",\"type\":\"about:blank\"}\n";
        foreach ($bodies as $body) {
            self::assertSame($expected, $body);
            foreach (['SYNTHETIC_', 'detail', 'instance', 'extensions', 'schemaVersion', 'severity'] as $private) {
                self::assertStringNotContainsString($private, $body);
            }
            self::assertSame(
                ['status' => 403, 'title' => 'Forbidden', 'type' => 'about:blank'],
                \json_decode($body, true, flags: \JSON_THROW_ON_ERROR),
            );
        }
        self::assertSame(
            [],
            \array_diff(\array_keys(new ProblemDetails(403)->toArray()), ['status', 'title', 'type']),
        );
    }

    public function testHandlerFailureEmergencyResponseContainsNoRawErrorOrRequestData(): void
    {
        $primary = new \RuntimeException('SYNTHETIC_THROWABLE_PRIVATE');
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')->with(self::identicalTo($primary))
            ->willThrowException(new \RuntimeException('SYNTHETIC_MAPPER_PRIVATE'));
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::never())->method('getBody');
        $request->expects(self::never())->method('getHeaders');
        $request->expects(self::never())->method('getParsedBody');
        $downstream = $this->createMock(RequestHandlerInterface::class);
        $downstream->expects(self::once())->method('handle')->with(self::identicalTo($request))
            ->willThrowException($primary);
        $response = $this->createMock(ResponseInterface::class);
        $headers = [];
        $response->expects(self::exactly(2))->method('withHeader')
            ->willReturnCallback(static function (
                string $key,
                string $value,
            ) use (&$headers, $response): ResponseInterface {
                $headers[] = [$key, $value];

                return $response;
            });
        $response->expects(self::once())->method('withBody')->willReturnSelf();
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(500)->willReturn($response);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')
            ->with("{\"status\":500,\"title\":\"Internal Server Error\",\"type\":\"about:blank\"}\n")
            ->willReturn($this->createStub(StreamInterface::class));
        $middleware = new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $streams,
        );

        self::assertSame($response, $middleware->process($request, $downstream));
        self::assertSame([
            ['Content-Type', 'application/problem+json'],
            ['Cache-Control', 'no-store'],
        ], $headers);
        self::assertSame(
            [],
            \array_diff(\array_keys(new ProblemDetails(500)->toArray()), ['status', 'title', 'type']),
        );
    }
}
