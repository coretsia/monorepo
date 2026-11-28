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

namespace Coretsia\Platform\HttpApp\Tests\Unit;

use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouteDispatchStateTest extends TestCase
{
    private const string ATTRIBUTE = 'coretsia.http_app.route_dispatch';

    public function testMatchedAndReservedHandoffsPreserveExactIdentityAndImmutableShape(): void
    {
        self::assertSame(self::ATTRIBUTE, RouteDispatchState::ATTRIBUTE);
        $match = new RouteMatch('route', '/items/{slug}', 'action', ['slug' => '%41']);
        $request = $this->request();
        $state = RouteDispatchState::matched($request, $match);
        $reserved = RouteDispatchState::reserved($this->request(path: '/_alive'));
        self::assertSame($match, $state->match());
        self::assertNull($reserved->match());
        $state->assertRequest($request);
        $reserved->assertRequest($this->request(path: '/_alive'));
        self::assertSame(
            $state,
            RouteDispatchState::fromRequest($this->request(attributes: [self::ATTRIBUTE => $state])),
        );
        self::assertSame(
            $reserved,
            RouteDispatchState::fromRequest($this->request(attributes: [self::ATTRIBUTE => $reserved])),
        );
        $reflection = new \ReflectionClass($state);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->getConstructor()->isPrivate());
        self::assertTrue(new \ReflectionClass($match)->isReadOnly());
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isPrivate());
            self::assertFalse($property->isStatic());
            $properties[$property->getName()] = $property->getValue($state);
        }
        \ksort($properties);
        self::assertSame([
            'host' => 'private.example',
            'match' => $match,
            'method' => 'GET',
            'path' => '/items/%41',
        ], $properties);
        $methods = \array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        \sort($methods);
        self::assertSame(['assertRequest', 'fromRequest', 'match', 'matched', 'reserved'], $methods);
    }

    #[DataProvider('invalidAttributes')]
    public function testMissingNullAndWronglyTypedRoutingStateAreRejected(array $attributes): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getAttributes')->willReturn($attributes);
        $request->expects(self::never())->method('getMethod');
        $request->expects(self::never())->method('getUri');
        self::assertRejected(static fn () => RouteDispatchState::fromRequest($request), 'http-app-route-state-invalid');
    }

    /** @return iterable<string, array{array}> */
    public static function invalidAttributes(): iterable
    {
        yield 'absent' => [[]];
        yield 'null' => [[self::ATTRIBUTE => null]];
        yield 'object' => [[self::ATTRIBUTE => new \stdClass()]];
        yield 'route-match' => [[self::ATTRIBUTE => new RouteMatch('route', '/items', 'action')]];
        yield 'string' => [[self::ATTRIBUTE => 'private-state']];
        yield 'array' => [[self::ATTRIBUTE => ['match' => 'private-state']]];
        yield 'false' => [[self::ATTRIBUTE => false]];
    }

    public function testOnlyExactAttributeKeyIsAdmittedEvenForCorrectlyTypedState(): void
    {
        $state = RouteDispatchState::reserved($this->request());
        foreach (
            [
                'coretsia.http_app.Route_Dispatch',
                'coretsia.http_app.route_dispatch.extra',
                'route_dispatch',
            ] as $key
        ) {
            $request = $this->request(attributes: [$key => $state]);
            self::assertRejected(
                static fn () => RouteDispatchState::fromRequest($request),
                'http-app-route-state-invalid'
            );
        }
    }

    #[DataProvider('routingMutations')]
    public function testExactRoutingInputsCannotChange(array $before, array $after): void
    {
        $original = $this->request(...$before);
        $changed = $this->request(...$after);
        $match = new RouteMatch('route', '/items/{slug}', 'action', ['slug' => '%41']);
        foreach ([RouteDispatchState::matched($original, $match), RouteDispatchState::reserved($original)] as $state) {
            $state->assertRequest($original);
            self::assertRejected(static fn () => $state->assertRequest($changed), 'http-app-route-input-changed');
            $state->assertRequest($original);
        }
    }

    /** @return iterable<string, array{array{string, string, string}, array{string, string, string}}> */
    public static function routingMutations(): iterable
    {
        yield 'method-case' => [['GET', '/items/%41', 'private.example'], ['get', '/items/%41', 'private.example']];
        yield 'method' => [['GET', '/items/%41', 'private.example'], ['POST', '/items/%41', 'private.example']];
        yield 'method-numeric' => [['1', '/items/%41', 'private.example'], ['01', '/items/%41', 'private.example']];
        yield 'decoded-path' => [['GET', '/items/%41', 'private.example'], ['GET', '/items/A', 'private.example']];
        yield 'encoded-path-case' => [
            ['GET', '/items/%2F', 'private.example'],
            ['GET', '/items/%2f', 'private.example'],
        ];
        yield 'path' => [['GET', '/items/%41', 'private.example'], ['GET', '/private/other', 'private.example']];
        yield 'relative-path' => [['GET', '1', ''], ['GET', '01', '']];
        yield 'host' => [['GET', '/items/%41', 'private.example'], ['GET', '/items/%41', 'other.example']];
        yield 'host-case' => [['GET', '/items/%41', 'private.example'], ['GET', '/items/%41', 'PRIVATE.example']];
        yield 'numeric-host' => [['GET', '/items/%41', '1'], ['GET', '/items/%41', '01']];
        yield 'empty-host-introduced' => [['GET', '/items/%41', ''], ['GET', '/items/%41', 'private.example']];
        yield 'host-removed' => [['GET', '/items/%41', 'private.example'], ['GET', '/items/%41', '']];
    }

    public function testEmptyHostIsNormalizedOnlyToNullAndOtherAdaptationsRemainAdmissible(): void
    {
        $original = $this->request(host: '');
        $match = new RouteMatch('route', '/items/{slug}', 'action', ['slug' => '%41']);
        $state = RouteDispatchState::matched($original, $match);
        self::assertNull(new \ReflectionProperty($state, 'host')->getValue($state));
        $body = ['parsed' => 'private-value'];
        $adapted = $this->request(
            host: '',
            attributes: [self::ATTRIBUTE => $state, 'other' => true],
            parsedBody: $body,
        );
        self::assertNotSame($original, $adapted);
        self::assertSame($body, $adapted->getParsedBody());
        self::assertSame($state, RouteDispatchState::fromRequest($adapted));
        $state->assertRequest($adapted);
        self::assertSame($match, $state->match());
        $parameters = $match->parameters();
        $parameters['slug'] = 'changed';
        self::assertSame(['slug' => '%41'], $state->match()->parameters());
    }

    public function testTypedReplacementIsAdmittedByValueObjectButRejectedByCanonicalPipeline(): void
    {
        $match = new RouteMatch('route', '/items/{slug}', 'action', ['slug' => '%41']);
        $base = $this->request();
        $state = RouteDispatchState::matched($base, $match);
        $replacement = RouteDispatchState::matched($base, $match);
        self::assertNotSame($state, $replacement);
        $original = $this->request(attributes: [self::ATTRIBUTE => $state]);
        $changed = $this->request(attributes: [self::ATTRIBUTE => $replacement]);
        self::assertSame($replacement, RouteDispatchState::fromRequest($changed));
        $replacement->assertRequest($changed);
        $state->assertRequest($changed);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with(self::identicalTo($original))->willReturn($response);
        $mutator = $this->createStub(MiddlewareInterface::class);
        $mutator->method('process')->willReturnCallback(static fn (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ): ResponseInterface => $next->handle($changed));
        $guarded = new Pipeline([$mutator], $terminal, true, [self::ATTRIBUTE], true);
        try {
            $guarded->handle($original);
            self::fail('replacement-state-must-not-reach-terminal');
        } catch (\LogicException $exception) {
            self::assertSame('http-pipeline-protected-attribute-changed', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
        $delegate = $this->createStub(MiddlewareInterface::class);
        $delegate->method('process')->willReturnCallback(static fn (
            ServerRequestInterface $request,
            RequestHandlerInterface $next,
        ): ResponseInterface => $next->handle($request));
        self::assertSame(
            $response,
            new Pipeline([$delegate], $terminal, true, [self::ATTRIBUTE], true)->handle($original),
        );
        self::assertSame($state, RouteDispatchState::fromRequest($original));
    }

    /** @param array<string, mixed> $attributes */
    private function request(
        string $method = 'GET',
        string $path = '/items/%41',
        string $host = 'private.example',
        array $attributes = [],
        ?array $parsedBody = null,
    ): ServerRequestInterface {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn($host);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getAttributes')->willReturn($attributes);
        $request->method('getParsedBody')->willReturn($parsedBody);

        return $request;
    }

    private static function assertRejected(\Closure $operation, string $reason): void
    {
        try {
            $operation();
        } catch (HttpAppDispatchException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame($reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());

            return;
        }

        self::fail('invalid-route-dispatch-state-accepted');
    }
}
