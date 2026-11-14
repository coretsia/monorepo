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

use Coretsia\Platform\Http\Exception\TrustedHostRejectedException;
use Coretsia\Platform\Http\Host\TrustedHostPolicy;
use Coretsia\Platform\Http\Middleware\TrustedHostMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TrustedHostPolicyDeterministicTest extends TestCase
{
    public function testOnlyExactCanonicalAllowlistEntriesAreAccepted(): void
    {
        $policy = new TrustedHostPolicy([
            'api.example.com',
            'example.org:8443',
            '192.0.2.3',
            '[2001:db8::1]:8080',
        ]);

        self::assertTrue($policy->allows('API.EXAMPLE.COM'));
        self::assertTrue($policy->allows('example.org:8443'));
        self::assertTrue($policy->allows('192.0.2.3'));
        self::assertTrue($policy->allows('[2001:db8::1]:8080'));
        self::assertFalse($policy->allows('example.com'));
        self::assertFalse($policy->allows('api.example.com:443'));
        self::assertFalse($policy->allows('sub.api.example.com'));
        self::assertFalse($policy->allows('example.org'));
        self::assertFalse($policy->allows('[2001:db8::1]'));
        self::assertFalse(new TrustedHostPolicy([])->allows('api.example.com'));
    }

    #[DataProvider('invalidEffectiveAuthorities')]
    public function testRejectsMalformedAmbiguousOrUnsafeEffectiveAuthorities(string $authority): void
    {
        $policy = new TrustedHostPolicy(['example.com', 'example.com:443', '192.0.2.1']);
        self::assertFalse($policy->allows($authority));
        self::assertFalse($policy->allows($authority));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEffectiveAuthorities(): iterable
    {
        yield 'empty' => [''];
        yield 'duplicate-host-list' => ['example.com,evil.example'];
        yield 'userinfo' => ['user@example.com'];
        yield 'trailing-dot' => ['example.com.'];
        yield 'leading-dot' => ['.example.com'];
        yield 'scheme' => ['https://example.com'];
        yield 'path' => ['example.com/admin'];
        yield 'query' => ['example.com?token=secret'];
        yield 'space' => ['example.com evil.example'];
        yield 'newline' => ["example.com\r\nHost: evil.example"];
        yield 'port-zero' => ['example.com:0'];
        yield 'port-too-large' => ['example.com:65536'];
        yield 'port-leading-zero' => ['example.com:0443'];
        yield 'unbracketed-ipv6' => ['2001:db8::1'];
        yield 'malformed-ipv6' => ['[2001:db8:::1]'];
        yield 'numeric-ipv4-with-leading-zero' => ['192.000.2.1'];
        yield 'numeric-ipv4-incomplete' => ['192.0.2'];
        yield 'wildcard' => ['*.example.com'];
        yield 'overlong' => [\str_repeat('a', 254)];
    }

    /** @param list<mixed> $allowed */
    #[DataProvider('invalidAllowlist')]
    public function testRejectsInvalidAllowlistDeterministically(array $allowed): void
    {
        try {
            new TrustedHostPolicy($allowed);
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('http-hosts-allowed-invalid', $exception->getMessage());

            return;
        }

        self::fail('Invalid host allowlist must fail closed.');
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalidAllowlist(): iterable
    {
        yield 'duplicate' => [['example.com', 'example.com']];
        yield 'differently-cased' => [['EXAMPLE.COM']];
        yield 'wildcard' => [['*.example.com']];
        yield 'invalid-port' => [['example.com:65536']];
        yield 'ipv4-noncanonical' => [['192.000.2.1']];
        yield 'ipv6-noncanonical' => [['[2001:0db8::1]']];
        yield 'non-list' => [['host' => 'example.com']];
        yield 'non-string' => [[42]];
        yield 'too-many' => [[...\array_fill(0, 65, 'example.com')]];
    }

    public function testMiddlewareChecksDirectAuthorityAndDoesNotParseForwardingHeaders(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $uri = $this->createStub(UriInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getUri')->willReturn($uri);
        $uri->method('getAuthority')->willReturn('API.EXAMPLE.COM');
        $request->method('getHeaderLine')->with('Host')->willReturn('api.example.com');
        $request->expects(self::never())->method('getAttribute');
        $request->expects(self::never())->method('getHeader');
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame(
            $response,
            new TrustedHostMiddleware(new TrustedHostPolicy(['api.example.com']), true, false)
                ->process($request, $handler),
        );
    }

    public function testMiddlewareRejectsConflictingDirectAuthorityAndHostHeader(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $uri = $this->createStub(UriInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getUri')->willReturn($uri);
        $uri->method('getAuthority')->willReturn('example.com');
        $request->method('getHeaderLine')->with('Host')->willReturn('example.com,evil.example');
        $handler->expects(self::never())->method('handle');

        try {
            new TrustedHostMiddleware(new TrustedHostPolicy(['example.com']), true, false)
                ->process($request, $handler);
        } catch (TrustedHostRejectedException $exception) {
            self::assertSame(TrustedHostRejectedException::ERROR_CODE, $exception->errorCode());
            self::assertSame(TrustedHostRejectedException::REASON_TRUSTED_HOST_REJECTED, $exception->reason());
            self::assertStringNotContainsString('evil.example', $exception->getMessage());

            return;
        }

        self::fail('Conflicting Host and URI authority must be rejected.');
    }

    public function testMiddlewareUsesValidatedProxyAttributeInsteadOfRawHostHeader(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getAttribute')->with(TrustedProxyMiddleware::ATTRIBUTE)->willReturn([
            'client_ip' => '192.0.2.8',
            'host' => 'example.com',
        ]);
        $request->expects(self::never())->method('getHeaderLine');
        $request->expects(self::never())->method('getUri');
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame(
            $response,
            new TrustedHostMiddleware(new TrustedHostPolicy(['example.com']), true, true)
                ->process($request, $handler),
        );
    }

    public function testHostMiddlewareFallsBackToDirectAuthorityWithoutValidatedProxyMetadata(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $uri = $this->createStub(UriInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->method('getAttribute')->willReturn(['host' => 'evil.example']);
        $request->method('getUri')->willReturn($uri);
        $uri->method('getAuthority')->willReturn('example.com');
        $request->method('getHeaderLine')->with('Host')->willReturn('example.com');
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame(
            $response,
            new TrustedHostMiddleware(new TrustedHostPolicy(['example.com']), true, true)
                ->process($request, $handler),
        );
    }
}
