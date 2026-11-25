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

namespace Coretsia\Foundation\Tests\Unit;

use Coretsia\Foundation\Routing\ReservedPathMatcher;
use Coretsia\Foundation\Routing\SystemRouteTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservedPathMatcherSegmentPrefixTest extends TestCase
{
    public function testMatcherExposesOnlyTheCanonicalPublicApi(): void
    {
        $reflection = new \ReflectionClass(ReservedPathMatcher::class);
        self::assertTrue($reflection->isFinal());
        self::assertFalse($reflection->isInstantiable());

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertSame([], $constructor->getParameters());

        $publicMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        self::assertCount(2, $publicMethods);

        $expected = [
            'match' => '?array',
            'normalize' => 'string',
        ];

        foreach ($publicMethods as $method) {
            self::assertArrayHasKey($method->getName(), $expected);
            self::assertTrue($method->isStatic());
            self::assertFalse($method->returnsReference());
            self::assertSame(
                $expected[$method->getName()],
                (string) $method->getReturnType(),
            );

            $parameters = $method->getParameters();
            self::assertCount(1, $parameters);
            self::assertSame('path', $parameters[0]->getName());
            self::assertSame('string', (string) $parameters[0]->getType());
            self::assertFalse($parameters[0]->isOptional());
            self::assertFalse($parameters[0]->isVariadic());
            self::assertFalse($parameters[0]->isPassedByReference());
        }
    }

    #[DataProvider('matchingPaths')]
    public function testReturnsTheCompleteCanonicalDeclaration(string $path, string $pattern): void
    {
        [$owner, $status, $type] = match ($pattern) {
            '/_alive', '/error', '/ping' => ['platform/http', 'active', 'exact'],
            '/_debug' => ['platform/http', 'dev_only', 'exact'],
            '/_debug/' => ['platform/http', 'dev_only', 'prefix'],
            '/health' => ['platform/health', 'reserved', 'exact'],
            '/health/' => ['platform/health', 'reserved', 'prefix'],
            '/metrics' => ['platform/metrics', 'reserved', 'exact'],
        };
        $expected = [
            'owner' => $owner,
            'pattern' => $pattern,
            'status' => $status,
            'type' => $type,
        ];

        $entries = \array_column(SystemRouteTable::entries(), null, 'pattern');
        self::assertSame($expected, $entries[$pattern]);
        self::assertSame($expected, ReservedPathMatcher::match($path));
    }

    /** @return iterable<string, array{string, string}> */
    public static function matchingPaths(): iterable
    {
        yield 'alive-exact' => ['/_alive', '/_alive'];
        yield 'alive-missing-leading-slash' => ['_alive', '/_alive'];
        yield 'alive-two-leading-slashes' => ['//_alive', '/_alive'];
        yield 'alive-three-leading-slashes' => ['///_alive', '/_alive'];
        yield 'error-exact' => ['/error', '/error'];
        yield 'ping-exact' => ['/ping', '/ping'];
        yield 'metrics-exact' => ['/metrics', '/metrics'];
        yield 'health-exact' => ['/health', '/health'];
        yield 'health-prefix-boundary' => ['/health/', '/health/'];
        yield 'health-descendant' => ['/health/ready', '/health/'];
        yield 'health-missing-leading-slash' => ['health/ready', '/health/'];
        yield 'health-leading-slashes-are-path-data' => ['//health/ready', '/health/'];
        yield 'health-interior-slashes' => ['/health//ready///live', '/health/'];
        yield 'health-trailing-slashes' => ['/health///', '/health/'];
        yield 'health-case-sensitive-descendant' => ['/health/READY', '/health/'];
        yield 'debug-exact' => ['/_debug', '/_debug'];
        yield 'debug-prefix-boundary' => ['/_debug/', '/_debug/'];
        yield 'debug-descendant' => ['/_debug/toolbar', '/_debug/'];
        yield 'debug-repeated-slashes' => ['//_debug//toolbar/', '/_debug/'];
        yield 'encoded-non-ascii' => ['/health/%C3%A9', '/health/'];
        yield 'encoded-non-utf8-octet' => ['/health/%FF', '/health/'];
        yield 'encoded-control-octet' => ['/health/%00', '/health/'];
        yield 'encoded-slash' => ['/health/%2fready', '/health/'];
        yield 'encoded-dot-segment' => ['/health/%2e', '/health/'];
        yield 'encoded-dot-dot-segment' => ['/health/%2E%2E/ready', '/health/'];
        yield 'encoded-percent-remains-opaque' => ['/health/%252E%252E', '/health/'];
        yield 'encoded-delimiters' => ['/health/%3F%23%7B%7D%5C', '/health/'];
        yield 'all-literal-pchar-families' => ['/health/Az09-._~!$&\'()*+,;=:@', '/health/'];
        yield 'asterisk-inside-path' => ['/health/*', '/health/'];
    }

    #[DataProvider('nonMatchingPaths')]
    public function testReturnsNullForValidPathsOutsideReservedDeclarations(string $path): void
    {
        self::assertNull(ReservedPathMatcher::match($path));
    }

    /** @return iterable<string, array{string}> */
    public static function nonMatchingPaths(): iterable
    {
        yield 'root' => ['/'];
        yield 'empty-root' => [''];
        yield 'repeated-root-slashes' => ['////'];
        yield 'unrelated-path' => ['/application'];
        yield 'health-sibling' => ['/healthz'];
        yield 'health-sibling-descendant' => ['/healthz/ready'];
        yield 'debug-sibling' => ['/_debugger'];
        yield 'debug-sibling-descendant' => ['/_debugger/toolbar'];
        yield 'alive-trailing-slash' => ['/_alive/'];
        yield 'alive-repeated-trailing-slashes' => ['/_alive///'];
        yield 'error-trailing-slash' => ['/error/'];
        yield 'metrics-trailing-slash' => ['/metrics/'];
        yield 'ping-trailing-slash' => ['/ping/'];
        yield 'alive-descendant' => ['/_alive/check'];
        yield 'alive-nested' => ['/probe/_alive'];
        yield 'health-case-variant' => ['/Health/ready'];
        yield 'debug-case-variant' => ['/_DEBUG/toolbar'];
        yield 'alive-case-variant' => ['/_Alive'];
        yield 'encoded-slash-not-a-prefix-boundary' => ['/health%2Fready'];
        yield 'encoded-slash-lowercase-not-a-prefix-boundary' => ['/health%2fready'];
        yield 'encoded-underscore-not-alive' => ['/%5Falive'];
        yield 'encoded-letter-not-alive' => ['/_%61live'];
        yield 'encoded-leading-slash-not-normalized' => ['%2F_alive'];
        yield 'encoded-percent-not-decoded-repeatedly' => ['/%255Falive'];
        yield 'encoded-dot' => ['/%2E'];
        yield 'encoded-dot-dot' => ['/%2E%2E'];
        yield 'encoded-non-ascii' => ['/%C3%A9'];
        yield 'encoded-non-utf8' => ['/%FF'];
        yield 'authority-like-path-data' => ['//example.com/health'];
        yield 'authority-like-path-with-port' => ['//example.com:443/health'];
    }

    #[DataProvider('invalidPaths')]
    public function testInvalidPathsProduceOnlyTheFixedDiagnostic(string $path): void
    {
        try {
            ReservedPathMatcher::match($path);
            self::fail('reserved-path-invalid-not-rejected');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(\InvalidArgumentException::class, $exception::class);
            self::assertSame('http-reserved-path-invalid', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPaths(): iterable
    {
        yield 'percent-without-octet' => ['/health/%'];
        yield 'truncated-percent-octet' => ['/health/%0'];
        yield 'invalid-first-hex-digit' => ['/health/%G0'];
        yield 'invalid-second-hex-digit' => ['/health/%0G'];
        yield 'invalid-percent-octet' => ['/health/%%'];
        yield 'valid-then-invalid-percent' => ['/health/%41%G0'];
        yield 'leading-space' => [' /health'];
        yield 'trailing-space' => ['/health '];
        yield 'interior-space' => ['/health/a b'];
        yield 'tab' => ["/health/\t"];
        yield 'line-feed' => ["/health/\n"];
        yield 'carriage-return' => ["/health/\r"];
        yield 'vertical-tab' => ["/health/\x0B"];
        yield 'form-feed' => ["/health/\x0C"];
        yield 'nul' => ["/health/\0"];
        yield 'control-byte' => ["/health/\x1F"];
        yield 'del' => ["/health/\x7F"];
        yield 'non-ascii-byte' => ["/health/\x80"];
        yield 'non-ascii-utf8' => ["/health/\xC3\xA9"];
        yield 'backslash' => ['/health\\ready'];
        yield 'query-string' => ['/health?probe=secret'];
        yield 'fragment' => ['/health#secret'];
        yield 'absolute-uri' => ['https://example.com/health'];
        yield 'uppercase-uri-scheme' => ['HTTPS://example.com/health'];
        yield 'scheme-without-authority' => ['mailto:secret@example.com'];
        yield 'scheme-with-allowed-special-characters' => ['a+1.b-c:secret'];
        yield 'authority-form' => ['example.com:443'];
        yield 'dot-segment' => ['.'];
        yield 'dot-dot-segment' => ['..'];
        yield 'leading-dot-segment' => ['/./health'];
        yield 'interior-dot-segment' => ['/health//./ready'];
        yield 'interior-dot-dot-segment' => ['/health/../ready'];
        yield 'terminal-dot-segment' => ['/health/.'];
        yield 'terminal-dot-dot-segment' => ['/health/..'];
        yield 'route-template' => ['/{slug}'];
        yield 'nested-route-template' => ['/health/{probe}'];
        yield 'opening-brace' => ['/health/{'];
        yield 'closing-brace' => ['/health/}'];
        yield 'square-brackets' => ['/health/[probe]'];
        yield 'angle-brackets' => ['/health/<probe>'];
        yield 'double-quote' => ['/health/"'];
        yield 'pipe' => ['/health/|'];
        yield 'caret' => ['/health/^'];
        yield 'backtick' => ['/health/`'];
        yield 'asterisk-request-target' => ['*'];
    }

    #[DataProvider('normalizedPaths')]
    public function testCanonicalNormalizationPreservesExactPathRepresentation(
        string $path,
        string $expected,
    ): void {
        self::assertSame($expected, ReservedPathMatcher::normalize($path));
    }

    /** @return iterable<string, array{string, string}> */
    public static function normalizedPaths(): iterable
    {
        yield 'empty-root' => ['', '/'];
        yield 'repeated-root-slashes' => ['////', '/'];
        yield 'interior-slashes' => ['health//ready///live', '/health/ready/live'];
        yield 'trailing-slash' => ['//health//ready///', '/health/ready/'];
        yield 'case-preservation' => ['/_debug//ToolBar//', '/_debug/ToolBar/'];
        yield 'encoded-octet-preservation' => ['//health//%2f%2F//%2e/', '/health/%2f%2F/%2e/'];
        yield 'encoded-non-ascii-preservation' => ['/health/%C3%A9//%FF', '/health/%C3%A9/%FF'];
    }

    public function testReservedPathNormalizationDoesNotDefineTheExactMaintenanceBypass(): void
    {
        $alive = ReservedPathMatcher::match(SystemRouteTable::ALIVE_PATH);
        self::assertNotNull($alive);
        self::assertSame('/_alive', $alive['pattern']);

        // The matcher recognizes reserved ownership; maintenance compares the
        // original URI path directly (covered by its existing integration tests).
        foreach (['//_alive', '///_alive', '_alive'] as $path) {
            self::assertNotSame(SystemRouteTable::ALIVE_PATH, $path);
            self::assertSame($alive, ReservedPathMatcher::match($path));
        }
    }

    public function testRepeatedMatchesAndReturnedEntriesDoNotRetainMutableState(): void
    {
        $reflection = new \ReflectionClass(ReservedPathMatcher::class);
        self::assertSame([], $reflection->getProperties());
        foreach ($reflection->getMethods() as $method) {
            self::assertSame([], $method->getStaticVariables());
        }

        $table = SystemRouteTable::entries();
        $health = ReservedPathMatcher::match('/health/ready');
        $debug = ReservedPathMatcher::match('/_debug/toolbar');
        self::assertNotNull($health);
        self::assertNotNull($debug);
        self::assertSame('reserved', $health['status']);
        self::assertSame('dev_only', $debug['status']);

        $copy = ReservedPathMatcher::match('/health/ready');
        self::assertNotNull($copy);
        self::assertSame($health, $copy);
        $copy['pattern'] = '/changed/';
        $copy['status'] = 'active';

        self::assertNotSame($health, $copy);
        self::assertSame($debug, ReservedPathMatcher::match('/_debug/toolbar'));
        self::assertNull(ReservedPathMatcher::match('/application'));
        self::assertSame($health, ReservedPathMatcher::match('/health/ready'));
        self::assertSame($health, ReservedPathMatcher::match('//health//ready'));
        self::assertSame($debug, ReservedPathMatcher::match('/_debug/toolbar'));
        self::assertSame($table, SystemRouteTable::entries());
    }
}
