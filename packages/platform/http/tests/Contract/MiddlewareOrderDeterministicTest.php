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

namespace Coretsia\Platform\Http\Tests\Contract;

use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareOrderDeterministicTest extends TestCase
{
    private const array SLOT_IDS = [
        'system_pre' => 'App\\SystemPre',
        'system' => 'App\\System',
        'app_pre' => 'App\\AppPre',
        'app' => 'App\\App',
        'route_pre' => 'App\\RoutePre',
        'route' => 'App\\Route',
        'system_post' => 'App\\SystemPost',
        'app_post' => 'App\\AppPost',
        'route_post' => 'App\\RoutePost',
    ];

    private const array SLOT_TAGS = [
        'system_pre' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
        'system' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM,
        'app_pre' => ReservedTags::HTTP_MIDDLEWARE_APP_PRE,
        'app' => ReservedTags::HTTP_MIDDLEWARE_APP,
        'route_pre' => ReservedTags::HTTP_MIDDLEWARE_ROUTE_PRE,
        'route' => ReservedTags::HTTP_MIDDLEWARE_ROUTE,
        'system_post' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST,
        'app_post' => ReservedTags::HTTP_MIDDLEWARE_APP_POST,
        'route_post' => ReservedTags::HTTP_MIDDLEWARE_ROUTE_POST,
    ];

    public function testCanonicalRequestPhaseAndResponseDecoratorUnwindOrder(): void
    {
        $ordered = self::orderedIds();
        self::assertSame([
            'App\\SystemPost',
            'App\\AppPost',
            'App\\RoutePost',
            'App\\SystemPre',
            'App\\System',
            'App\\AppPre',
            'App\\App',
            'App\\RoutePre',
            'App\\Route',
        ], $ordered);

        $events = [];
        $response = $this->createStub(ResponseInterface::class);
        $request = $this->createStub(ServerRequestInterface::class);
        $terminal = self::terminal($response, $events);
        $pipeline = self::recordingPipeline($ordered, $terminal, $events);

        for ($round = 0; $round < 2; ++$round) {
            self::assertSame($response, $pipeline->handle($request));
            $expected = [];
            foreach ($ordered as $id) {
                $expected[] = 'enter:' . $id;
            }
            $expected[] = 'terminal';
            foreach (\array_reverse($ordered) as $id) {
                $expected[] = 'exit:' . $id;
            }
            self::assertSame($expected, \array_slice($events, $round * \count($expected), \count($expected)));
        }
    }

    public function testEarlyShortCircuitStillUnwindsAllApplicablePostDecorators(): void
    {
        $events = [];
        $response = $this->createStub(ResponseInterface::class);
        $shortCircuitRequest = $this->createStub(ServerRequestInterface::class);
        $nextRequest = $this->createStub(ServerRequestInterface::class);
        $terminal = self::terminal($response, $events);
        $middleware = [];
        foreach (self::orderedIds() as $id) {
            $middleware[] = new ContractHttpProbeMiddleware(
                static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                    &$events,
                    $id,
                    $response,
                    $shortCircuitRequest,
                ): ResponseInterface {
                    $events[] = 'enter:' . $id;
                    $result = $id === 'App\\AppPre' && $request === $shortCircuitRequest
                        ? $response
                        : $next->handle($request);
                    $events[] = 'exit:' . $id;

                    return $result;
                },
            );
        }

        $pipeline = new Pipeline($middleware, $terminal);
        self::assertSame($response, $pipeline->handle($shortCircuitRequest));
        self::assertSame([
            'enter:App\\SystemPost',
            'enter:App\\AppPost',
            'enter:App\\RoutePost',
            'enter:App\\SystemPre',
            'enter:App\\System',
            'enter:App\\AppPre',
            'exit:App\\AppPre',
            'exit:App\\System',
            'exit:App\\SystemPre',
            'exit:App\\RoutePost',
            'exit:App\\AppPost',
            'exit:App\\SystemPost',
        ], $events);

        $events = [];
        self::assertSame($response, $pipeline->handle($nextRequest));

        $ordered = self::orderedIds();
        self::assertSame([
            ...\array_map(static fn (string $id): string => 'enter:' . $id, $ordered),
            'terminal',
            ...\array_map(static fn (string $id): string => 'exit:' . $id, \array_reverse($ordered)),
        ], $events);
    }

    public function testOriginalThrowableAndSubsequentInvocationSurviveFailedMiddleware(): void
    {
        $failure = new \RuntimeException('original-downstream-error');
        $fail = $this->createStub(ServerRequestInterface::class);
        $ok = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $events = [];
        $pipeline = new Pipeline([
            new ContractHttpProbeMiddleware(
                static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                    &$events,
                ): ResponseInterface {
                    $events[] = 'first';

                    return $next->handle($request);
                },
            ),
            new ContractHttpProbeMiddleware(
                static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                    $fail,
                    $failure,
                    &$events,
                ): ResponseInterface {
                    $events[] = 'second';
                    if ($request === $fail) {
                        throw $failure;
                    }

                    return $next->handle($request);
                },
            ),
        ], self::terminal($response, $events));

        try {
            $pipeline->handle($fail);
            self::fail('The original downstream Throwable must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame($response, $pipeline->handle($ok));
        self::assertSame(['first', 'second', 'first', 'second', 'terminal'], $events);
    }

    public function testReentrantHandleStartsWithAnIndependentTraversalCursor(): void
    {
        $outer = $this->createStub(ServerRequestInterface::class);
        $inner = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $events = [];
        $pipeline = null;
        $pipeline = new Pipeline([
            new ContractHttpProbeMiddleware(
                static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                    &$pipeline,
                    &$events,
                    $outer,
                    $inner,
                    $response,
                ): ResponseInterface {
                    $events[] = $request === $outer ? 'outer:first' : 'inner:first';
                    if ($request === $outer) {
                        self::assertSame($response, $pipeline->handle($inner));
                    }

                    return $next->handle($request);
                },
            ),
            new ContractHttpProbeMiddleware(
                static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                    &$events,
                    $outer,
                ): ResponseInterface {
                    $events[] = $request === $outer ? 'outer:second' : 'inner:second';

                    return $next->handle($request);
                },
            ),
        ], self::terminal($response, $events));

        self::assertSame($response, $pipeline->handle($outer));
        self::assertSame([
            'outer:first',
            'inner:first',
            'inner:second',
            'terminal',
            'outer:second',
            'terminal',
        ], $events);
    }

    /** @return list<string> */
    private static function orderedIds(): array
    {
        $tags = new TagRegistry();
        foreach (self::SLOT_IDS as $slot => $id) {
            $tags->add(self::SLOT_TAGS[$slot], $id, 50);
        }

        return new HttpMiddlewareStackBuilder($tags)->build(require __DIR__ . '/../../config/http.php');
    }

    /** @param list<string> $ids @param list<string> $events */
    private static function recordingPipeline(array $ids, RequestHandlerInterface $terminal, array &$events): Pipeline
    {
        $middleware = [];
        foreach ($ids as $id) {
            $middleware[] = new ContractHttpProbeMiddleware(
                static function (ServerRequestInterface $request, RequestHandlerInterface $next) use (
                    &$events,
                    $id,
                ): ResponseInterface {
                    $events[] = 'enter:' . $id;
                    $response = $next->handle($request);
                    $events[] = 'exit:' . $id;

                    return $response;
                },
            );
        }

        return new Pipeline($middleware, $terminal);
    }

    /** @param list<string> $events */
    private static function terminal(ResponseInterface $response, array &$events): RequestHandlerInterface
    {
        return new class(
            $response,
            static function () use (&$events): void {
                $events[] = 'terminal';
            },
        ) implements RequestHandlerInterface {
            public function __construct(
                private ResponseInterface $response,
                private \Closure $recordTerminal,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                ($this->recordTerminal)();

                return $this->response;
            }
        };
    }
}

final readonly class ContractHttpProbeMiddleware implements MiddlewareInterface
{
    /** @param \Closure(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $execute */
    public function __construct(private \Closure $execute)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->execute)($request, $handler);
    }
}
