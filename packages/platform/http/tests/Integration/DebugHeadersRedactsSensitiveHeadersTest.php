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

namespace Coretsia\Platform\Http\Tests\Integration;

use Coretsia\Contracts\Security\Exception\RedactionException;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Platform\Http\Debug\DebugHeadersHandler;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class DebugHeadersRedactsSensitiveHeadersTest extends TestCase
{
    private const string TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testExactPresenceProjectionNeverContainsHeaderValues(string $mode): void
    {
        $fixture = $this->boot($mode);

        try {
            $expected = [
                'headers' => [
                    'accept' => true,
                    'authorization' => self::placeholder('authorization'),
                    'content-type' => true,
                    'cookie' => self::placeholder('cookie'),
                    'set-cookie' => self::placeholder('cookie'),
                    'user-agent' => true,
                    'x-debug-token' => self::placeholder('token'),
                ],
            ];
            foreach (['PRIVATE_FIRST', 'PRIVATE_SECOND_LONGER'] as $private) {
                $response = $fixture->kernel()->handle(
                    $fixture->request(
                        'GET',
                        '/_debug/headers',
                        headers: [
                            'aCcEpT' => ['Bearer ' . $private],
                            'AUTHORIZATION' => ['Bearer ' . $private, 'Basic ' . $private],
                            'content-TYPE' => ['application/json; secret=' . $private],
                            'cOoKiE' => ['sid=' . $private],
                            'SET-cookie' => ['session=' . $private],
                            'USER-agent' => ["\r\n" . $private],
                            'x-DEBUG-token' => [self::TOKEN],
                            'X-Unsupported-' . $private => [$private],
                            'Proxy-Authorization' => ['Bearer ' . $private],
                            'Referer' => ['https://example.test/' . $private],
                        ],
                        serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                    ),
                );
                self::assertSame(200, $response->getStatusCode());
                self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
                self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                $body = (string) $response->getBody();
                self::assertSame($expected, \json_decode($body, true, 512, \JSON_THROW_ON_ERROR));
                self::assertSame(\json_encode($expected, \JSON_THROW_ON_ERROR) . "\n", $body);
                self::assertStringNotContainsString($private, $body);
                self::assertStringNotContainsString(self::TOKEN, $body);
                self::assertStringNotContainsString(\hash('sha256', self::TOKEN), $body);
            }

            $response = $fixture->kernel()->handle(
                $fixture->request(
                    'GET',
                    '/_debug/headers',
                    headers: ['X-Debug-Token' => [self::TOKEN]],
                    serverParams: ['REMOTE_ADDR' => '::1'],
                ),
            );
            self::assertSame(200, $response->getStatusCode());
            self::assertSame([
                'headers' => [
                    'accept' => false,
                    'authorization' => null,
                    'content-type' => false,
                    'cookie' => null,
                    'set-cookie' => null,
                    'user-agent' => false,
                    'x-debug-token' => self::placeholder('token'),
                ],
            ], \json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR));
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testHandlerNeverReadsSensitiveBytesAndUsesCanonicalPlaceholderContext(string $mode): void
    {
        $fixture = $this->boot($mode);
        try {
            $request = $this->createMock(ServerRequestInterface::class);
            $request->method('hasHeader')->willReturn(true);
            $request->expects(self::never())->method('getHeader');
            $request->expects(self::never())->method('getHeaderLine');
            $request->expects(self::never())->method('getHeaders');
            $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
            $kinds = [];
            $redactor->expects(self::exactly(4))->method('redactValue')->willReturnCallback(
                static function (string $value, RedactionKind $kind, RedactionContext $context) use (
                    &$kinds,
                ): RedactedValue {
                    self::assertSame('', $value);
                    self::assertSame('http.debug.headers', $context->scope());
                    self::assertSame(RedactionMode::Placeholder, $context->mode());
                    $kinds[] = $kind;

                    return new RedactedValue($kind, RedactionMode::Placeholder, null, null);
                },
            );
            $redactor->expects(self::never())->method('redactJsonLike');
            $response = new DebugHeadersHandler(
                $redactor,
                $fixture->container->get(FallbackResponses::class),
            )->handle($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame([
                RedactionKind::Authorization,
                RedactionKind::Cookie,
                RedactionKind::Cookie,
                RedactionKind::Token,
            ], $kinds);
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testInvalidOrThrowingRedactorCannotConstructDiagnosticResponse(string $mode): void
    {
        $fixture = $this->boot($mode);
        try {
            foreach (
                [
                    RedactionException::outputInvalid(),
                    new RedactedValue(RedactionKind::Cookie, RedactionMode::Placeholder, null, null),
                    new RedactedValue(RedactionKind::Authorization, RedactionMode::Length, 42, null),
                    new RedactedValue(
                        RedactionKind::Authorization,
                        RedactionMode::Hash,
                        null,
                        'sha256:' . \str_repeat('a', 64),
                    ),
                ] as $failure
            ) {
                $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
                $method = $redactor->expects(self::once())->method('redactValue');
                if ($failure instanceof \Throwable) {
                    $method->willThrowException($failure);
                } else {
                    $method->willReturn($failure);
                }

                $handler = new DebugHeadersHandler($redactor, $fixture->container->get(FallbackResponses::class));
                try {
                    $handler->handle($fixture->request('GET', '/_debug/headers', headers: [
                        'Authorization' => ['Bearer PRIVATE'],
                    ]));
                    self::fail('Expected the header diagnostic to fail closed.');
                } catch (RedactionException $exception) {
                    self::assertSame(RedactionException::outputInvalid()->getMessage(), $exception->getMessage());
                }
                self::assertSame([], $fixture->factories()->statuses);
                self::assertSame([], $fixture->factories()->bodies);
            }
        } finally {
            $fixture->close();
        }
    }

    /** @return array<string, mixed> */
    private static function placeholder(string $kind): array
    {
        return [
            'hash' => null,
            'kind' => $kind,
            'length' => null,
            'mode' => 'placeholder',
            'redacted' => true,
            'schemaVersion' => 1,
        ];
    }

    private function boot(string $mode): SystemEndpointRuntimeFixture
    {
        return SystemEndpointRuntimeFixture::boot($mode, $this->createStub(...), [
            'appEnv' => 'local',
            'problemDetails' => true,
            'http' => [
                'debug' => [
                    'endpoints' => ['enabled' => true],
                    'token_sha256' => \hash('sha256', self::TOKEN),
                ],
            ],
        ]);
    }
}
