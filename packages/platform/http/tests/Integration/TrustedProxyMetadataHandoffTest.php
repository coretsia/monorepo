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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Platform\Http\Exception\TrustedHostRejectedException;
use Coretsia\Platform\Http\Host\TrustedHostPolicy;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestContextMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedHostMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware;
use Coretsia\Platform\Http\Proxy\TrustedProxyPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TrustedProxyMetadataHandoffTest extends TestCase
{
    public function testTrustedImmediatePeerPublishesOnlyNormalizedHandoffAndAuthorizesForwardedHost(): void
    {
        $context = new ContextStore();
        $original = $this->createMock(ServerRequestInterface::class);
        $sanitized = $this->createMock(ServerRequestInterface::class);
        $accepted = $this->createMock(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $metadata = ['client_ip' => '192.0.2.7', 'scheme' => 'https', 'host' => 'example.com:443'];
        $original->expects(self::once())->method('withoutAttribute')
            ->with(TrustedProxyMiddleware::ATTRIBUTE)->willReturn($sanitized);
        $original->expects(self::never())->method('getAttribute');
        $sanitized->method('getServerParams')->willReturn(['REMOTE_ADDR' => '10.0.0.1']);
        $sanitized->method('getHeader')->willReturnCallback(static fn (string $name): array => match ($name) {
            'Forwarded' => ['for=192.0.2.7;proto=HTTPS;host="EXAMPLE.COM:443"'],
            default => [],
        });
        $sanitized->expects(self::once())->method('withAttribute')
            ->with(TrustedProxyMiddleware::ATTRIBUTE, $metadata)->willReturn($accepted);
        $accepted->method('getAttribute')->with(TrustedProxyMiddleware::ATTRIBUTE)->willReturn($metadata);
        $accepted->method('getServerParams')->willReturn(['REMOTE_ADDR' => '10.0.0.1']);
        $accepted->method('getHeader')->willReturnCallback(static fn (string $name): array => match ($name) {
            'Host' => [],
            'User-Agent' => ['safe-agent'],
            default => [],
        });
        $uri = $this->uri('http', 'origin.example', '/safe');
        $accepted->method('getUri')->willReturn($uri);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($accepted)
            ->willReturnCallback(static function () use ($context, $response): ResponseInterface {
                self::assertSame([
                    ContextKeys::CLIENT_IP => '192.0.2.7',
                    ContextKeys::SCHEME => 'https',
                    ContextKeys::HOST => 'example.com:443',
                    ContextKeys::PATH => '/safe',
                    ContextKeys::USER_AGENT => 'safe-agent',
                ], $context->all());

                return $response;
            });
        $pipeline = new Pipeline([
            new TrustedProxyMiddleware(new TrustedProxyPolicy(['10.0.0.0/8']), true),
            new RequestContextMiddleware($context, true, true),
            new TrustedHostMiddleware(new TrustedHostPolicy(['example.com:443']), true, true),
        ], $terminal);

        self::assertSame($response, $pipeline->handle($original));
        self::assertSame(['client_ip', 'scheme', 'host'], \array_keys($metadata));
        self::assertNotContains('Forwarded', \array_keys($context->all()));
    }

    #[DataProvider('untrustedPeers')]
    public function testUntrustedOrMissingPeerClearsForgedAttributeAndUsesOnlyDirectMetadata(mixed $peer): void
    {
        $context = new ContextStore();
        $original = $this->createMock(ServerRequestInterface::class);
        $sanitized = $this->createMock(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $original->expects(self::once())->method('withoutAttribute')
            ->with(TrustedProxyMiddleware::ATTRIBUTE)->willReturn($sanitized);
        $original->method('getAttribute')->with(TrustedProxyMiddleware::ATTRIBUTE)->willReturn([
            'client_ip' => '198.51.100.100',
            'scheme' => 'https',
            'host' => 'evil.example',
        ]);
        $sanitized->method('getServerParams')->willReturn($peer === null ? [] : ['REMOTE_ADDR' => $peer]);
        $sanitized->expects(self::never())->method('withAttribute');
        $sanitized->method('getAttribute')->with(TrustedProxyMiddleware::ATTRIBUTE)->willReturn(null);
        $sanitized->method('getUri')->willReturn($this->uri('http', 'origin.example', '/direct'));
        $sanitized->method('getHeader')->willReturnCallback(static fn (string $name): array => match ($name) {
            'Host' => [],
            'User-Agent' => ['safe-agent'],
            default => [],
        });
        $sanitized->method('getHeaderLine')->willReturnCallback(static fn (string $name): string => match ($name) {
            'User-Agent' => 'safe-agent',
            'Host' => 'origin.example',
            default => '',
        });
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($sanitized)->willReturn($response);
        $pipeline = new Pipeline([
            new TrustedProxyMiddleware(new TrustedProxyPolicy(['10.0.0.0/8']), true),
            new RequestContextMiddleware($context, true, true),
            new TrustedHostMiddleware(new TrustedHostPolicy(['origin.example']), true, true),
        ], $terminal);

        self::assertSame($response, $pipeline->handle($original));
        self::assertSame('origin.example', $context->get(ContextKeys::HOST));
        self::assertSame('http', $context->get(ContextKeys::SCHEME));
        self::assertNotSame('198.51.100.100', $context->get(ContextKeys::CLIENT_IP));
        self::assertNotContains('evil.example', $context->all());
    }

    /** @return iterable<string, array{mixed}> */
    public static function untrustedPeers(): iterable
    {
        yield 'missing' => [null];
        yield 'untrusted' => ['192.0.2.55'];
        yield 'noncanonical' => ['010.0.0.1'];
        yield 'invalid' => ["10.0.0.1\r\nInjected: yes"];
    }

    public function testDisabledProxyClearsReservedAttributeAndNeverMakesItAuthoritative(): void
    {
        $original = $this->createMock(ServerRequestInterface::class);
        $sanitized = $this->createMock(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $context = new ContextStore();
        $original->expects(self::once())->method('withoutAttribute')
            ->with(TrustedProxyMiddleware::ATTRIBUTE)->willReturn($sanitized);
        $sanitized->expects(self::never())->method('withAttribute');
        $sanitized->method('getServerParams')->willReturn(['REMOTE_ADDR' => '10.0.0.1']);
        $sanitized->method('getUri')->willReturn($this->uri('http', 'origin.example', '/ok'));
        $sanitized->method('getHeader')->willReturnCallback(static fn (string $name): array => match ($name) {
            'Host' => [],
            'User-Agent' => ['agent'],
            default => [],
        });
        $sanitized->method('getHeaderLine')->willReturnCallback(static fn (string $name): string => match ($name) {
            'User-Agent' => 'agent',
            'Host' => 'origin.example',
            default => '',
        });
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($sanitized)->willReturn($response);
        $pipeline = new Pipeline([
            new TrustedProxyMiddleware(new TrustedProxyPolicy(['10.0.0.0/8']), false),
            new RequestContextMiddleware($context, true, false),
            new TrustedHostMiddleware(new TrustedHostPolicy(['origin.example']), true, false),
        ], $terminal);

        self::assertSame($response, $pipeline->handle($original));
        self::assertSame('origin.example', $context->get(ContextKeys::HOST));
        self::assertSame('10.0.0.1', $context->get(ContextKeys::CLIENT_IP));
    }

    public function testMalformedForwardedHostCannotOverrideDirectHostOrReachContext(): void
    {
        $original = $this->createMock(ServerRequestInterface::class);
        $sanitized = $this->createMock(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $context = new ContextStore();
        $original->expects(self::once())->method('withoutAttribute')->willReturn($sanitized);
        $sanitized->method('getServerParams')->willReturn(['REMOTE_ADDR' => '10.0.0.1']);
        $sanitized->method('getHeader')->willReturnCallback(static fn (string $name): array => match ($name) {
            'Forwarded' => ['for=192.0.2.9;host=evil.example/path'],
            'Host' => [],
            'User-Agent' => ['agent'],
            default => [],
        });
        $sanitized->expects(self::never())->method('withAttribute');
        $sanitized->method('getUri')->willReturn($this->uri('http', 'origin.example', '/safe'));
        $sanitized->method('getHeaderLine')->willReturnCallback(static fn (string $name): string => match ($name) {
            'Host' => 'origin.example',
            'User-Agent' => 'agent',
            default => '',
        });
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($response);
        $pipeline = new Pipeline([
            new TrustedProxyMiddleware(new TrustedProxyPolicy(['10.0.0.0/8']), true),
            new RequestContextMiddleware($context, true, true),
            new TrustedHostMiddleware(new TrustedHostPolicy(['origin.example']), true, true),
        ], $terminal);

        self::assertSame($response, $pipeline->handle($original));
        self::assertSame('origin.example', $context->get(ContextKeys::HOST));
    }

    public function testWrongDirectAuthorityCannotBeAuthorizedByUntrustedForwardedHost(): void
    {
        $original = $this->createStub(ServerRequestInterface::class);
        $sanitized = $this->createStub(ServerRequestInterface::class);
        $original->method('withoutAttribute')->willReturn($sanitized);
        $sanitized->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.0.2.55']);
        $sanitized->method('getUri')->willReturn($this->uri('http', 'evil.example', '/'));
        $sanitized->method('getHeader')->willReturnCallback(static fn (string $name): array => match ($name) {
            'Forwarded' => ['for=192.0.2.7;host=allowed.example'],
            'X-Forwarded-Host' => ['allowed.example'],
            default => [],
        });
        $sanitized->method('getHeaderLine')->willReturnCallback(static fn (string $name): string => match ($name) {
            'Host' => 'evil.example',
            'Forwarded' => 'for=192.0.2.7;host=allowed.example',
            'X-Forwarded-Host' => 'allowed.example',
            default => '',
        });
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = new Pipeline([
            new TrustedProxyMiddleware(new TrustedProxyPolicy(['10.0.0.0/8']), true),
            new TrustedHostMiddleware(new TrustedHostPolicy(['allowed.example']), true, true),
        ], $terminal);

        $this->expectException(TrustedHostRejectedException::class);
        $pipeline->handle($original);
    }

    private function uri(string $scheme, string $host, string $path): UriInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getScheme')->willReturn($scheme);
        $uri->method('getHost')->willReturn($host);
        $uri->method('getPort')->willReturn(null);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getAuthority')->willReturn($host);

        return $uri;
    }
}
