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
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Platform\Http\Host\TrustedHostPolicy;
use Coretsia\Platform\Http\HttpsRedirect\HttpsRedirectPolicy;
use Coretsia\Platform\Http\Maintenance\MaintenanceMiddleware;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\HttpsRedirectMiddleware;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware;
use Coretsia\Platform\Http\Middleware\RequestContextMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedHostMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\Proxy\TrustedProxyPolicy;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class HttpsRedirectRespectsProxySchemeTest extends TestCase
{
    /**
     * @param array<string, list<string>> $forwarded
     * @param ?array<string, string> $metadata
     */
    #[DataProvider('proxyCases')]
    public function testCanonicalProxyHandoffControlsRedirectIndependentlyOfContext(
        string $direct,
        ?string $peer,
        array $forwarded,
        bool $proxyEnabled,
        ?array $metadata,
        bool $redirected,
    ): void {
        foreach ([true, false] as $enrich) {
            $context = new ContextStore();
            $headers = $forwarded + ['Host' => ['attacker.example']];
            $uri = $this->uri($direct, '/encoded%2fpart', 'a=%2F&b=%20');
            $original = $this->request($uri, 'GET', $peer, $headers, [
                'client_ip' => '192.0.2.90',
                'scheme' => $direct === 'http' ? 'https' : 'http',
                'host' => 'forged.example',
            ]);
            $sanitized = $this->request($uri, 'GET', $peer, $headers, null);
            $accepted = $metadata === null ? $sanitized : $this->request($uri, 'GET', $peer, $headers, $metadata);
            $original->expects(self::once())->method('withoutAttribute')
                ->with(TrustedProxyMiddleware::ATTRIBUTE)->willReturn($sanitized);
            if ($metadata === null) {
                $sanitized->expects(self::never())->method('withAttribute');
            } else {
                $sanitized->expects(self::once())->method('withAttribute')
                    ->with(TrustedProxyMiddleware::ATTRIBUTE, $metadata)->willReturn($accepted);
            }
            [$middleware, $terminal, $expected, $verify] = $this->decision(
                new HttpsRedirectPolicy(true, 308, ['GET'], 'secure.example:8443'),
                $redirected ? 'https://secure.example:8443/encoded%2fpart?a=%2F&b=%20' : null,
                $proxyEnabled,
                true,
                true,
            );
            $pipeline = new Pipeline([
                new TrustedProxyMiddleware(new TrustedProxyPolicy(['10.0.0.0/8']), $proxyEnabled),
                new RequestContextMiddleware($context, $enrich, $proxyEnabled),
                $middleware,
            ], $terminal);

            self::assertSame($expected, $pipeline->handle($original));
            $verify();
            if ($enrich) {
                self::assertSame($metadata['scheme'] ?? $direct, $context->get(ContextKeys::SCHEME));
            } else {
                self::assertSame([], $context->all());
            }
        }
    }

    /** @return iterable<string, array{string, ?string, array<string, list<string>>, bool, ?array<string, string>, bool}> */
    public static function proxyCases(): iterable
    {
        $https = ['X-Forwarded-For' => ['192.0.2.7'], 'X-Forwarded-Proto' => ['https']];
        $http = [
            'X-Forwarded-For' => ['192.0.2.7'],
            'X-Forwarded-Proto' => ['http'],
            'X-Forwarded-Host' => ['attacker.example:9443'],
        ];
        yield 'trusted-https' => [
            'http',
            '10.0.0.1',
            $https,
            true,
            [
                'client_ip' => '192.0.2.7',
                'scheme' => 'https',
            ],
            false,
        ];
        yield 'trusted-http' => [
            'https',
            '10.0.0.1',
            $http,
            true,
            [
                'client_ip' => '192.0.2.7',
                'scheme' => 'http',
                'host' => 'attacker.example:9443',
            ],
            true,
        ];
        yield 'untrusted-peer' => ['http', '192.0.2.9', $https, true, null, true];
        yield 'missing-peer' => ['http', null, $https, true, null, true];
        yield 'missing-handoff' => ['http', '10.0.0.1', [], true, null, true];
        yield 'missing-scheme' => [
            'http',
            '10.0.0.1',
            ['X-Forwarded-For' => ['192.0.2.7']],
            true,
            [
                'client_ip' => '192.0.2.7',
            ],
            true,
        ];
        yield 'proto-without-for' => ['http', '10.0.0.1', ['X-Forwarded-Proto' => ['https']], true, null, true];
        yield 'malformed-proto' => [
            'http',
            '10.0.0.1',
            [
                'X-Forwarded-For' => ['192.0.2.7'],
                'X-Forwarded-Proto' => ['https,http'],
            ],
            true,
            null,
            true,
        ];
        yield 'disabled-proxy' => ['http', '10.0.0.1', $https, false, null, true];
        yield 'untrusted-http' => ['https', '192.0.2.9', $http, true, null, false];
        yield 'rfc-forwarded' => [
            'http',
            '10.0.0.1',
            ['Forwarded' => ['for=192.0.2.7;proto=https']],
            true,
            [
                'client_ip' => '192.0.2.7',
                'scheme' => 'https',
            ],
            false,
        ];
    }

    #[DataProvider('malformedProxyAttributes')]
    public function testMalformedProxyAttributeCannotOverrideDirectScheme(
        string $direct,
        mixed $metadata,
    ): void {
        [$middleware, $terminal, $expected, $verify] = $this->decision(
            new HttpsRedirectPolicy(true, 308, ['GET'], 'secure.example'),
            $direct === 'http' ? 'https://secure.example/' : null,
            true,
            true,
            true,
        );

        $request = $this->request(
            $this->uri($direct, '/', ''),
            'GET',
            null,
            [],
            $metadata,
        );

        self::assertSame($expected, $middleware->process($request, $terminal));
        $verify();
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function malformedProxyAttributes(): iterable
    {
        foreach (['http', 'https'] as $direct) {
            $opposite = $direct === 'http' ? 'https' : 'http';

            foreach (
                [
                    'missing' => null,
                    'not-array' => $opposite,
                    'missing-client-ip' => ['scheme' => $opposite],
                    'empty-client-ip' => [
                        'client_ip' => '',
                        'scheme' => $opposite,
                    ],
                    'non-string-client-ip' => [
                        'client_ip' => 7,
                        'scheme' => $opposite,
                    ],
                    'missing-scheme' => [
                        'client_ip' => '192.0.2.7',
                    ],
                    'unsupported-scheme' => [
                        'client_ip' => '192.0.2.7',
                        'scheme' => 'ftp',
                    ],
                    'uppercase-scheme' => [
                        'client_ip' => '192.0.2.7',
                        'scheme' => \strtoupper($opposite),
                    ],
                    'non-string-scheme' => [
                        'client_ip' => '192.0.2.7',
                        'scheme' => 7,
                    ],
                ] as $case => $metadata
            ) {
                yield $direct . '-' . $case => [$direct, $metadata];
            }
        }
    }

    /** @param list<string> $allowed */
    #[DataProvider('directCases')]
    public function testDirectSchemeMethodAndLocationValidation(
        string $scheme,
        string $method,
        string $path,
        string $query,
        int $status,
        array $allowed,
        ?string $location,
    ): void {
        $policy = new HttpsRedirectPolicy(true, $status, $allowed, 'secure.example');

        self::assertSame($status, $policy->status());

        [$middleware, $terminal, $expected, $verify] = $this->decision(
            $policy,
            $location,
            false,
            true,
            true,
        );
        // A disabled proxy tag may be absent altogether. The forged attribute
        // must not be read by the redirect middleware in that case.
        $request = $this->request($this->uri($scheme, $path, $query), $method, null, [], [
            'client_ip' => '192.0.2.7',
            'scheme' => $scheme === 'http' ? 'https' : 'http',
        ]);
        self::assertSame($expected, $middleware->process($request, $terminal));
        $verify();
    }

    /** @return iterable<string, array{string, string, string, string, int, list<string>, ?string}> */
    public static function directCases(): iterable
    {
        yield 'empty-path' => ['http', 'GET', '', '', 307, ['GET'], 'https://secure.example/'];
        yield 'encoded-components' => [
            'http',
            'HEAD',
            '/a%2Fb%3F%20',
            'x=%2f?y=%23',
            308,
            ['HEAD'],
            'https://secure.example/a%2Fb%3F%20?x=%2f?y=%23',
        ];
        yield 'https' => ['https', 'GET', '/', '', 308, ['GET'], null];
        yield 'non-http' => ['ftp', 'GET', '/', '', 308, ['GET'], null];
        yield 'missing-scheme' => ['', 'GET', '/', '', 308, ['GET'], null];
        yield 'empty-allowlist' => ['http', 'GET', '/', '', 308, [], null];
        yield 'lowercase-method' => ['http', 'get', '/', '', 308, ['GET'], null];
        yield 'not-allowed' => ['http', 'POST', '/', '', 308, ['GET'], null];
        yield 'multiple-allowed-head' => [
            'http',
            'HEAD',
            '/',
            '',
            308,
            ['GET', 'HEAD'],
            'https://secure.example/',
        ];
        yield 'multiple-allowed-denied' => [
            'http',
            'POST',
            '/',
            '',
            308,
            ['GET', 'HEAD'],
            null,
        ];
        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            yield 'allowed-' . $method => ['http', $method, '/', '', 308, [$method], 'https://secure.example/'];
        }
        foreach (
            [
                'relative',
                '//attacker.example/x',
                '/a b',
                '/a\\b',
                '/a#b',
                '/a?b',
                '/a%',
                '/a%2',
                '/a%GG',
                "/a\r\nb",
                "/a\xc3\xa9",
                '/a[b]',
            ] as $index => $path
        ) {
            yield 'bad-path-' . $index => ['http', 'GET', $path, '', 308, ['GET'], null];
        }
        foreach (['a b', 'a\\b', 'a#b', 'a%', 'a%2', 'a%GG', "a\0b", "a\xc3\xa9", 'a[b]'] as $index => $query) {
            yield 'bad-query-' . $index => ['http', 'GET', '/', $query, 308, ['GET'], null];
        }
    }

    public function testProviderOrderingAndSameSlotManualAppendNeverDuplicateParticipation(): void
    {
        $http = require __DIR__ . '/../../config/http.php';
        $http['tracing']['enabled'] = false;
        $http['maintenance']['enabled'] = true;
        $http['proxy']['enabled'] = true;
        $http['context']['enrich']['enabled'] = true;
        $http['hosts']['enabled'] = true;
        $http['https_redirect']['enabled'] = true;
        $http['https_redirect']['target_authority'] = 'secure.example';
        $http['request']['json']['enabled'] = false;
        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext(['http' => $http]);
        new RedactionServiceProvider()->define($definitions, $context);
        new HttpServiceProvider()->define($definitions, $context);
        $tags = new TagRegistry();
        foreach ($definitions->build()->toDescriptorStream() as $operation) {
            if (($operation['kind'] ?? null) === 'tag') {
                $tags->add($operation['tag'], $operation['serviceId'], $operation['priority'], $operation['meta']);
            }
        }
        $maintenanceEntries = \array_values(
            \array_filter(
                $tags->all(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE),
                static fn ($entry): bool => $entry->id() === MaintenanceMiddleware::class,
            ),
        );
        self::assertCount(1, $maintenanceEntries);
        self::assertSame(900, $maintenanceEntries[0]->priority());
        self::assertSame(['toggle' => 'http.maintenance.enabled'], $maintenanceEntries[0]->meta());
        $entries = \array_values(
            \array_filter(
                $tags->all(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE),
                static fn ($entry): bool => $entry->id() === HttpsRedirectMiddleware::class,
            ),
        );
        self::assertCount(1, $entries);
        self::assertSame(850, $entries[0]->priority());
        self::assertSame(['toggle' => 'http.https_redirect.enabled'], $entries[0]->meta());
        $stack = new HttpMiddlewareStackBuilder($tags);
        self::assertSame([
            MaintenanceMiddleware::class,
            TrustedProxyMiddleware::class,
            RequestContextMiddleware::class,
            TrustedHostMiddleware::class,
            HttpsRedirectMiddleware::class,
            RequestBodySizeLimitMiddleware::class,
        ], $stack->build($http));
        $http['middleware']['system_pre'] = [HttpsRedirectMiddleware::class];
        self::assertSame([
            MaintenanceMiddleware::class,
            TrustedProxyMiddleware::class,
            RequestContextMiddleware::class,
            TrustedHostMiddleware::class,
            HttpsRedirectMiddleware::class,
            RequestBodySizeLimitMiddleware::class,
        ], $stack->build($http));

        $http['maintenance']['enabled'] = false;
        $http['proxy']['enabled'] = false;
        $http['context']['enrich']['enabled'] = false;
        $http['hosts']['enabled'] = false;
        foreach ([true, false] as $enabled) {
            $http['https_redirect']['enabled'] = $enabled;
            [$redirect, $terminal, $expected, $verify] = $this->decision(
                new HttpsRedirectPolicy($enabled, 308, ['GET'], $enabled ? 'secure.example' : ''),
                null,
                false,
                true,
                true,
            );
            $bodyLimit = $this->createMock(MiddlewareInterface::class);
            $bodyLimit->expects(self::once())->method('process')->willReturnCallback(
                static fn (
                    ServerRequestInterface $request,
                    RequestHandlerInterface $next,
                ): ResponseInterface => $next->handle($request),
            );
            $container = $this->createMock(ContainerInterface::class);
            $resolved = [];
            $container->expects(self::exactly(2))->method('get')->willReturnCallback(
                static function (string $id) use ($redirect, $bodyLimit, &$resolved): MiddlewareInterface {
                    $resolved[] = $id;

                    return match ($id) {
                        HttpsRedirectMiddleware::class => $redirect,
                        RequestBodySizeLimitMiddleware::class => $bodyLimit,
                        default => throw new \LogicException('unexpected-middleware'),
                    };
                },
            );
            $pipeline = HttpServiceFactory::pipeline(
                $container,
                new ArrayConfigRepository(['http' => $http], []),
                $stack,
                $terminal,
            );
            self::assertSame(
                $enabled ? [HttpsRedirectMiddleware::class, RequestBodySizeLimitMiddleware::class]
                    : [RequestBodySizeLimitMiddleware::class, HttpsRedirectMiddleware::class],
                $resolved,
            );
            $request = $this->request($this->uri($enabled ? 'https' : 'http', '/', ''), 'GET', null, [], null);
            self::assertSame($expected, $pipeline->handle($request));
            $verify();
        }
    }

    #[DataProvider('authorities')]
    public function testRedirectAuthorityUsesCanonicalTrustedHostGrammar(string $authority, bool $valid): void
    {
        foreach ([true, false] as $enabled) {
            try {
                new TrustedHostPolicy([$authority]);
                self::assertTrue($valid, 'trusted-host-invalid-authority-accepted');
            } catch (\InvalidArgumentException) {
                self::assertFalse($valid, 'trusted-host-valid-authority-rejected');
            }
            try {
                $policy = new HttpsRedirectPolicy($enabled, 308, ['GET'], $authority);
                self::assertTrue($valid, 'redirect-invalid-authority-accepted');
                self::assertSame($authority, $policy->targetAuthority());
            } catch (\InvalidArgumentException $actual) {
                self::assertFalse($valid, 'redirect-valid-authority-rejected');
                self::assertSame('http-https-redirect-target-authority-invalid', $actual->getMessage());
            }
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function authorities(): iterable
    {
        foreach (
            [
                'secure.example',
                'secure.example:8443',
                '192.0.2.1',
                '192.0.2.1:443',
                '[2001:db8::1]',
                '[2001:db8::1]:8443',
            ] as $index => $value
        ) {
            yield 'valid-' . $index => [$value, true];
        }
        foreach (
            [
                'https://secure.example',
                'user@secure.example',
                'secure.example/path',
                'secure.example?query',
                'secure.example#fragment',
                'SECURE.example',
                'secure.example:0',
                'secure.example:65536',
                'secure.example:0443',
                '010.0.0.1',
                '2001:db8::1',
                '[2001:0db8::1]',
                ' secure.example',
                "secure.example\r\n",
            ] as $index => $value
        ) {
            yield 'invalid-' . $index => [$value, false];
        }
    }

    public function testEmptyAuthorityIsOnlyAcceptedWhenDisabled(): void
    {
        self::assertSame('', new HttpsRedirectPolicy(false, 308, [], '')->targetAuthority());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http-https-redirect-target-authority-invalid');
        new HttpsRedirectPolicy(true, 308, [], '');
    }

    /** @param list<string> $methods */
    #[DataProvider('invalidRedirectPolicies')]
    public function testInvalidStatusAndMethodListsAreRejected(int $status, array $methods, string $token): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($token);
        new HttpsRedirectPolicy(true, $status, $methods, 'secure.example');
    }

    /** @return iterable<string, array{int, list<string>, string}> */
    public static function invalidRedirectPolicies(): iterable
    {
        foreach ([200, 301, 302, 303, 306, 309] as $status) {
            yield 'unsupported-status-' . $status => [$status, ['GET'], 'http-https-redirect-status-invalid'];
        }
        yield 'duplicate' => [308, ['GET', 'GET'], 'http-https-redirect-allow-methods-invalid'];
        yield 'unsupported' => [308, ['TRACE'], 'http-https-redirect-allow-methods-invalid'];
        yield 'lowercase' => [308, ['get'], 'http-https-redirect-allow-methods-invalid'];
        yield 'empty-method' => [308, [''], 'http-https-redirect-allow-methods-invalid'];
    }

    #[DataProvider('observationCases')]
    public function testObservationFlagsAndFailuresAreIndependent(
        bool $redirected,
        bool $metrics,
        bool $logs,
        bool $meterFails,
        bool $logFails,
    ): void {
        [$middleware, $terminal, $expected, $verify] = $this->decision(
            new HttpsRedirectPolicy(true, 307, ['GET'], 'secure.example'),
            $redirected ? 'https://secure.example/private%2Ftoken?secret=value' : null,
            false,
            $metrics,
            $logs,
            $meterFails,
            $logFails,
        );
        $request = $this->request(
            $this->uri($redirected ? 'http' : 'https', '/private%2Ftoken', 'secret=value'),
            'GET',
            null,
            [
                'Host' => ['attacker.example'],
                'Authorization' => ['Bearer synthetic-token'],
                'Cookie' => ['session=synthetic-session'],
                'X-Forwarded-Host' => ['forwarded.example'],
            ],
            null,
        );
        $request->method('getCookieParams')->willReturn([
            'session' => 'synthetic-session',
        ]);
        self::assertSame($expected, $middleware->process($request, $terminal));
        $verify();
    }

    /** @return iterable<string, array{bool, bool, bool, bool, bool}> */
    public static function observationCases(): iterable
    {
        foreach ([true, false] as $redirected) {
            foreach ([true, false] as $metrics) {
                foreach ([true, false] as $logs) {
                    foreach ([false, true] as $meterFails) {
                        foreach ([false, true] as $logFails) {
                            yield \sprintf('%d-%d-%d-%d-%d', $redirected, $metrics, $logs, $meterFails, $logFails)
                            => [$redirected, $metrics, $logs, $meterFails, $logFails];
                        }
                    }
                }
            }
        }
    }

    public function testSkippedDecisionIsRecordedBeforeTheOriginalDownstreamFailure(): void
    {
        $failure = new \RuntimeException('synthetic-downstream-failure');
        $events = [];
        $meter = $this->createMock(MeterPortInterface::class);
        $counters = [];
        $records = [];
        $meter->expects(self::once())->method('increment')->willReturnCallback(
            static function (string $name, int $delta, array $labels) use (&$events, &$counters): void {
                $events[] = 'metric';
                $counters[] = [$name, $delta, $labels];
            },
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willReturnCallback(
            static function (string $event, array $context) use (&$events, &$records): void {
                $events[] = 'log';
                $records[] = [$event, $context];
            },
        );
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->expects(self::never())->method('createResponse');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturnCallback(
            static function () use (&$events, $failure): never {
                $events[] = 'terminal';
                throw $failure;
            },
        );
        $middleware = new HttpsRedirectMiddleware(
            new HttpsRedirectPolicy(true, 308, ['GET'], 'secure.example'),
            $factory,
            $meter,
            $logger,
            false,
            true,
            true,
        );
        try {
            $middleware->process($this->request($this->uri('https', '/', ''), 'GET', null, [], null), $terminal);
            self::fail('downstream-failure-swallowed');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
        self::assertSame(['metric', 'log', 'terminal'], $events);
        self::assertSame([['http.https_redirect_total', 1, ['outcome' => 'skipped']]], $counters);
        self::assertSame([['http.https_redirect.decided', ['outcome' => 'skipped']]], $records);
    }

    #[DataProvider('constructionFailures')]
    public function testIncompleteRedirectNeverEmitsDecision(string $stage): void
    {
        $failure = new \RuntimeException('synthetic-redirect-construction-failure');
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $creation = $factory->expects(self::once())->method('createResponse')->with(308);
        if ($stage === 'factory') {
            $creation->willThrowException($failure);
        } else {
            $response = $this->createMock(ResponseInterface::class);
            $creation->willReturn($response);
            $mutation = $response->expects(self::once())->method('withHeader')->with(
                'Location',
                'https://secure.example/',
            );
            if ($stage === 'invalid') {
                $mutation->willReturn($this->createStub(MessageInterface::class));
            } else {
                $mutation->willThrowException($failure);
            }
        }
        $emissions = [];
        $meter = $this->createStub(MeterPortInterface::class);
        $meter->method('increment')->willReturnCallback(static function () use (&$emissions): void {
            $emissions[] = 'metric';
        });
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('info')->willReturnCallback(static function () use (&$emissions): void {
            $emissions[] = 'log';
        });
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $middleware = new HttpsRedirectMiddleware(
            new HttpsRedirectPolicy(true, 308, ['GET'], 'secure.example'),
            $factory,
            $meter,
            $logger,
            false,
            true,
            true,
        );
        try {
            $middleware->process($this->request($this->uri('http', '/', ''), 'GET', null, [], null), $terminal);
            self::fail('incomplete-redirect-returned');
        } catch (\RuntimeException $actual) {
            if ($stage === 'invalid') {
                self::assertSame('http-https-redirect-response-invalid', $actual->getMessage());
            } else {
                self::assertSame($failure, $actual);
            }
        }
        self::assertSame([], $emissions);
    }

    /** @return iterable<string, array{string}> */
    public static function constructionFailures(): iterable
    {
        yield 'factory' => ['factory'];
        yield 'location-mutation' => ['mutation'];
        yield 'non-response-result' => ['invalid'];
    }

    /** @return array{HttpsRedirectMiddleware, RequestHandlerInterface, ResponseInterface, \Closure(): void} */
    private function decision(
        HttpsRedirectPolicy $policy,
        ?string $location,
        bool $proxy,
        bool $metrics,
        bool $logs,
        bool $meterFails = false,
        bool $logFails = false,
    ): array {
        $expected = $this->createStub(ResponseInterface::class);
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $meter = $this->createStub(MeterPortInterface::class);
        $logger = $this->createStub(LoggerInterface::class);
        $redirected = $location !== null;
        $outcome = $redirected ? 'redirected' : 'skipped';
        $terminal->expects($redirected ? self::never() : self::once())->method('handle')->willReturn($expected);
        if ($redirected) {
            $initial = $this->createMock(ResponseInterface::class);
            $initial->expects(self::once())->method('withHeader')->with('Location', $location)->willReturn($expected);
            $factory->expects(self::once())->method('createResponse')->with($policy->status())->willReturn($initial);
        } else {
            $factory->expects(self::never())->method('createResponse');
        }
        $counters = [];
        $records = [];
        $meter->method('increment')->willReturnCallback(
            static function (string $name, int $delta, array $labels) use (&$counters, $meterFails): void {
                $counters[] = [$name, $delta, $labels];
                if ($meterFails) {
                    throw new \RuntimeException('synthetic-counter-failure');
                }
            },
        );
        $meter->method('observe')->willReturnCallback(static function () use (&$counters): void {
            $counters[] = ['unexpected-observation'];
        });
        $logger->method('info')->willReturnCallback(
            static function (string|\Stringable $event, array $context) use (&$records, $logFails): void {
                $records[] = [$event, $context];
                if ($logFails) {
                    throw new \RuntimeException('synthetic-log-failure');
                }
            },
        );
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'debug', 'log'] as $method) {
            $logger->method($method)->willReturnCallback(static function () use (&$records): void {
                $records[] = ['unexpected-log'];
            });
        }
        $verify = static function () use (
            &$counters,
            &$records,
            $policy,
            $metrics,
            $logs,
            $redirected,
            $outcome,
        ): void {
            self::assertSame(
                $policy->enabled() && $metrics ? [['http.https_redirect_total', 1, ['outcome' => $outcome]]] : [],
                $counters,
            );
            $context = $redirected ? ['outcome' => $outcome, 'status' => $policy->status()] : ['outcome' => $outcome];
            self::assertSame($policy->enabled() && $logs ? [['http.https_redirect.decided', $context]] : [], $records);
        };

        return [
            new HttpsRedirectMiddleware($policy, $factory, $meter, $logger, $proxy, $metrics, $logs),
            $terminal,
            $expected,
            $verify,
        ];
    }

    private function uri(string $scheme, string $path, string $query): UriInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getScheme')->willReturn($scheme);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getQuery')->willReturn($query);
        $uri->method('getHost')->willReturn('origin.example');
        $uri->method('getPort')->willReturn(8080);
        $uri->method('getUserInfo')->willReturn('synthetic-user:password');
        $uri->method('getFragment')->willReturn('fragment');
        $uri->method('getAuthority')->willReturnCallback(
            static fn (): string => $uri->getUserInfo()
                . '@'
                . $uri->getHost()
                . ':'
                . $uri->getPort(),
        );
        $uri->method('__toString')->willReturnCallback(
            static fn (): string => ($scheme !== '' ? $scheme . ':' : '')
                . '//'
                . $uri->getAuthority()
                . $path
                . ($query !== '' ? '?' . $query : '')
                . '#'
                . $uri->getFragment(),
        );

        return $uri;
    }

    /**
     * @param array<string, list<string>> $headers
     * @return ServerRequestInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function request(
        UriInterface $uri,
        string $method,
        ?string $peer,
        array $headers,
        mixed $metadata,
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::never())->method('withUri');
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn($method);
        $request->method('getServerParams')->willReturn($peer === null ? [] : ['REMOTE_ADDR' => $peer]);
        $request->method('getHeaders')->willReturn($headers);
        $headers = \array_change_key_case($headers, \CASE_LOWER);
        $request->method('hasHeader')->willReturnCallback(
            static fn (string $name): bool => isset($headers[\strtolower($name)]),
        );

        $request->method('getHeader')->willReturnCallback(
            static fn (string $name): array => $headers[\strtolower($name)] ?? [],
        );

        $request->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => \implode(
                ',',
                $headers[\strtolower($name)] ?? [],
            ),
        );
        $request->method('getAttribute')->with(TrustedProxyMiddleware::ATTRIBUTE)->willReturn($metadata);

        return $request;
    }
}
