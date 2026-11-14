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

use Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware;
use Coretsia\Platform\Http\Proxy\TrustedProxyPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ForwardedParsingDeterministicTest extends TestCase
{
    public function testOnlyTheCanonicalConfiguredImmediatePeerCanAuthorizeHeaders(): void
    {
        $policy = new TrustedProxyPolicy(['10.0.0.0/8', '2001:db8::/32']);
        $forwarded = ['forwarded' => ['for=192.0.2.7;proto=https;host=example.com']];

        self::assertTrue($policy->isTrustedPeer('10.1.2.3'));
        self::assertTrue($policy->isTrustedPeer('2001:db8::1'));
        self::assertFalse($policy->isTrustedPeer('192.0.2.4'));
        self::assertFalse($policy->isTrustedPeer('2001:0db8::1'));
        self::assertFalse($policy->isTrustedPeer('10.1.2.3:8080'));
        self::assertFalse($policy->isTrustedPeer(''));
        self::assertSame([], $policy->resolve('192.0.2.4', $forwarded));
        self::assertSame([], $policy->resolve('', $forwarded));
        self::assertSame([], $policy->resolve('10.1.2.3:8080', $forwarded));
    }

    public function testCanonicalForwardedValuesAndNearestUntrustedChainMember(): void
    {
        $policy = new TrustedProxyPolicy(['10.0.0.0/8']);

        self::assertSame(
            ['client_ip' => '192.0.2.7', 'scheme' => 'https', 'host' => 'example.com:443'],
            $policy->resolve('10.0.0.1', [
                'forwarded' => ['for=192.0.2.7;proto=HTTPS;host="EXAMPLE.COM:443"'],
            ]),
        );
        self::assertSame(
            ['client_ip' => '198.51.100.8', 'scheme' => 'https'],
            $policy->resolve('10.0.0.1', [
                'forwarded' => ['for=198.51.100.8;proto=https,for=10.0.0.7;proto=https'],
            ]),
        );
        self::assertSame(
            ['client_ip' => '203.0.113.9'],
            $policy->resolve('10.0.0.1', [
                'forwarded' => ['for=192.0.2.50,for=203.0.113.9,for=10.0.0.7'],
            ]),
        );
        self::assertSame(
            ['client_ip' => '2001:db8::1', 'scheme' => 'http', 'host' => '[2001:db8::2]:8080'],
            $policy->resolve('10.0.0.1', [
                'forwarded' => ['for="[2001:db8::1]";proto=HTTP;host="[2001:db8::2]:8080"'],
            ]),
        );
        self::assertSame(
            ['client_ip' => '192.0.2.17', 'scheme' => 'https', 'host' => 'example.org'],
            $policy->resolve('10.0.0.1', [
                'x-forwarded-for' => ['192.0.2.17'],
                'x-forwarded-proto' => ['HTTPS'],
                'x-forwarded-host' => ['EXAMPLE.ORG'],
            ]),
        );
    }

    /**
     * @param array<string, mixed> $headers
     */
    #[DataProvider('invalidForwardingHeaders')]
    public function testMalformedAmbiguousAndConflictingHeadersAreRejectedAtomically(array $headers): void
    {
        $policy = new TrustedProxyPolicy(['10.0.0.0/8']);
        self::assertSame([], $policy->resolve('10.0.0.1', $headers));
        self::assertSame([], $policy->resolve('10.0.0.1', $headers));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidForwardingHeaders(): iterable
    {
        yield 'duplicate-for' => [['forwarded' => ['for=192.0.2.1;for=192.0.2.2']]];
        yield 'duplicate-proto' => [['forwarded' => ['for=192.0.2.1;proto=http;proto=https']]];
        yield 'unknown-parameter' => [['forwarded' => ['for=192.0.2.1;evil=value']]];
        yield 'missing-for' => [['forwarded' => ['proto=https;host=example.com']]];
        yield 'unmatched-quote' => [['forwarded' => ['for="192.0.2.1']]];
        yield 'invalid-node' => [['forwarded' => ['for=unknown']]];
        yield 'invalid-scheme' => [['forwarded' => ['for=192.0.2.1;proto=ftp']]];
        yield 'invalid-host' => [['forwarded' => ['for=192.0.2.1;host=evil.example/path']]];
        yield 'invalid-control-byte' => [['forwarded' => ["for=192.0.2.1\r\nInjected: yes"]]];
        yield 'mixed-rfc-and-legacy' => [
            [
                'forwarded' => ['for=192.0.2.1'],
                'x-forwarded-for' => ['192.0.2.2'],
            ],
        ];
        yield 'conflicting-legacy-schemes' => [
            [
                'x-forwarded-for' => ['192.0.2.1'],
                'x-forwarded-proto' => ['https', 'http'],
            ],
        ];
        yield 'conflicting-legacy-hosts' => [
            [
                'x-forwarded-for' => ['192.0.2.1'],
                'x-forwarded-host' => ['example.com,evil.example'],
            ],
        ];
        yield 'legacy-for-invalid-ip' => [['x-forwarded-for' => ['192.0.2.999']]];
        yield 'legacy-for-empty-node' => [['x-forwarded-for' => ['192.0.2.1,']]];
        yield 'missing-legacy-for' => [['x-forwarded-host' => ['example.com']]];
        yield 'empty-header-list' => [['forwarded' => []]];
        yield 'non-list-values' => [['forwarded' => ['line' => 'for=192.0.2.1']]];
        yield 'overlong-header' => [['forwarded' => [\str_repeat('x', 8193)]]];
        yield 'too-many-hops' => [['forwarded' => [\implode(',', \array_fill(0, 17, 'for=192.0.2.1'))]]];
    }

    /** @param list<mixed> $trusted */
    #[DataProvider('invalidTrustedNetworks')]
    public function testInvalidOrAmbiguousTrustedProxyConfigurationFailsDeterministically(array $trusted): void
    {
        try {
            new TrustedProxyPolicy($trusted);
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('http-trusted-proxies-invalid', $exception->getMessage());

            return;
        }

        self::fail('Invalid trusted proxy configuration must fail closed.');
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalidTrustedNetworks(): iterable
    {
        yield 'noncanonical-cidr' => [['10.1.0.1/16']];
        yield 'duplicate' => [['10.0.0.0/8', '10.0.0.0/8']];
        yield 'invalid-prefix' => [['10.0.0.0/33']];
        yield 'octal-ipv4' => [['010.0.0.1']];
        yield 'invalid-ip' => [['999.0.0.1']];
        yield 'noncanonical-ipv6' => [['2001:0db8::/32']];
        yield 'non-list' => [['first' => '10.0.0.1']];
        yield 'wrong-type' => [[false]];
        yield 'too-many-networks' => [[...\array_fill(0, 65, '10.0.0.1')]];
    }

    public function testMiddlewarePublishesOnlyCanonicalMetadataForTrustedPeer(): void
    {
        $original = $this->createMock(ServerRequestInterface::class);
        $sanitized = $this->createMock(ServerRequestInterface::class);
        $accepted = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $original->expects(self::once())
            ->method('withoutAttribute')
            ->with(TrustedProxyMiddleware::ATTRIBUTE)
            ->willReturn($sanitized);
        $sanitized->method('getServerParams')->willReturn(['REMOTE_ADDR' => '10.0.0.1']);
        $sanitized->method('getHeader')->willReturnCallback(static fn (string $name): array => match ($name) {
            'Forwarded' => ['for=192.0.2.5;proto=HTTPS;host=EXAMPLE.COM'],
            default => [],
        });
        $sanitized->expects(self::once())
            ->method('withAttribute')
            ->with(
                TrustedProxyMiddleware::ATTRIBUTE,
                ['client_ip' => '192.0.2.5', 'scheme' => 'https', 'host' => 'example.com'],
            )
            ->willReturn($accepted);
        $handler->expects(self::once())->method('handle')->with($accepted)->willReturn($response);

        self::assertSame(
            $response,
            new TrustedProxyMiddleware(new TrustedProxyPolicy(['10.0.0.0/8']), true)
                ->process($original, $handler),
        );
    }

    /** @param array<string, mixed> $serverParams */
    #[DataProvider('untrustedServerParams')]
    public function testAbsentInvalidOrUntrustedPeerCannotPublishOrPreserveProxyAttribute(array $serverParams): void
    {
        $original = $this->createMock(ServerRequestInterface::class);
        $sanitized = $this->createMock(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $original->expects(self::once())
            ->method('withoutAttribute')
            ->with(TrustedProxyMiddleware::ATTRIBUTE)
            ->willReturn($sanitized);
        $sanitized->method('getServerParams')->willReturn($serverParams);
        $sanitized->expects(self::never())->method('getHeader');
        $sanitized->expects(self::never())->method('withAttribute');
        $handler->expects(self::once())->method('handle')->with($sanitized)->willReturn($response);

        self::assertSame(
            $response,
            new TrustedProxyMiddleware(new TrustedProxyPolicy(['10.0.0.0/8']), true)
                ->process($original, $handler),
        );
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function untrustedServerParams(): iterable
    {
        yield 'missing' => [[]];
        yield 'null' => [['REMOTE_ADDR' => null]];
        yield 'non-string' => [['REMOTE_ADDR' => ['10.0.0.1']]];
        yield 'invalid' => [['REMOTE_ADDR' => '10.0.0.1:1234']];
        yield 'untrusted' => [['REMOTE_ADDR' => '192.0.2.12']];
        yield 'noncanonical' => [['REMOTE_ADDR' => '2001:0db8::1']];
    }

    public function testInvalidForwardedValuesCannotPublishTrustedAttribute(): void
    {
        $original = $this->createStub(ServerRequestInterface::class);
        $sanitized = $this->createMock(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $original->method('withoutAttribute')->willReturn($sanitized);
        $sanitized->method('getServerParams')->willReturn(['REMOTE_ADDR' => '10.0.0.1']);
        $sanitized->method('getHeader')->willReturnCallback(static fn (string $name): array => match ($name) {
            'Forwarded' => ['for=192.0.2.1;host=evil.example/path'],
            default => [],
        });
        $sanitized->expects(self::never())->method('withAttribute');
        $handler->expects(self::once())->method('handle')->with($sanitized)->willReturn($response);

        self::assertSame(
            $response,
            new TrustedProxyMiddleware(new TrustedProxyPolicy(['10.0.0.0/8']), true)
                ->process($original, $handler),
        );
    }
}
