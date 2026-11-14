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

use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpEarlyShortCircuitPostDecoratorsTest extends TestCase
{
    #[DataProvider('shortCircuitSlots')]
    public function testAllThreePostDecoratorsUnwindAroundOrdinaryEarlyResponse(string $slot): void
    {
        $events = [];
        $headers = [];
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(3))->method('withHeader')
            ->willReturnCallback(
                static function (string $name, string $value) use ($response, &$headers): ResponseInterface {
                    $headers[] = [$name, $value];

                    return $response;
                },
            );
        $request = $this->createStub(ServerRequestInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $services = [];
        foreach (['system', 'app', 'route'] as $post) {
            $name = $post . '-post';
            $services[$name] = $this->createMock(MiddlewareInterface::class);
            $services[$name]->expects(self::once())->method('process')
                ->willReturnCallback(static function (
                    ServerRequestInterface $incoming,
                    RequestHandlerInterface $next,
                ) use ($name, &$events): ResponseInterface {
                    $events[] = $name . ':enter';
                    $returned = $next->handle($incoming);
                    $events[] = $name . ':leave';

                    return $returned->withHeader('X-Decorator', $name);
                });
        }
        $services['short'] = $this->createMock(MiddlewareInterface::class);
        $services['short']->expects(self::once())->method('process')
            ->willReturnCallback(static function (
                ServerRequestInterface $incoming,
                RequestHandlerInterface $next,
            ) use (&$events, $response, $request): ResponseInterface {
                self::assertSame($request, $incoming);
                self::assertInstanceOf(RequestHandlerInterface::class, $next);
                $events[] = 'short-circuit';

                return $response;
            });
        $pipeline = self::pipeline($services, $terminal, $slot);
        self::assertSame($response, $pipeline->handle($request));
        self::assertSame([
            'system-post:enter',
            'app-post:enter',
            'route-post:enter',
            'short-circuit',
            'route-post:leave',
            'app-post:leave',
            'system-post:leave',
        ], $events);
        self::assertSame([
            ['X-Decorator', 'route-post'],
            ['X-Decorator', 'app-post'],
            ['X-Decorator', 'system-post'],
        ], $headers);
    }

    /** @return iterable<string, array{string}> */
    public static function shortCircuitSlots(): iterable
    {
        yield 'short-circuit-at-system' => ['system'];
        yield 'short-circuit-at-app' => ['app'];
        yield 'short-circuit-at-route' => ['route'];
    }

    public function testOutermostExceptionConversionDoesNotPretendToBeOrdinaryPostDecoration(): void
    {
        $events = [];
        $primary = new \RuntimeException('private-downstream-failure');
        $errorHandler = $this->createMock(ErrorHandlerInterface::class);
        $errorHandler->expects(self::once())->method('handle')->with(self::identicalTo($primary))
            ->willThrowException(new \RuntimeException('normalization-failed'));
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->expects(self::exactly(2))->method('withHeader')->willReturnSelf();
        $response->expects(self::once())->method('withBody')->willReturnSelf();
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(500)->willReturn($response);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')->willReturn($this->createStub(StreamInterface::class));
        $boundary = new HttpErrorHandlingMiddleware(
            $errorHandler,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $streams,
        );
        $services = [HttpErrorHandlingMiddleware::class => $boundary];
        foreach (['system', 'app', 'route'] as $post) {
            $name = $post . '-post';
            $services[$name] = $this->createMock(MiddlewareInterface::class);
            $services[$name]->expects(self::once())->method('process')
                ->willReturnCallback(static function (
                    ServerRequestInterface $incoming,
                    RequestHandlerInterface $next,
                ) use (&$events, $name): ResponseInterface {
                    $events[] = $name . ':enter';
                    $result = $next->handle($incoming);
                    $events[] = $name . ':leave';

                    return $result;
                });
        }
        $services['short'] = $this->createMock(MiddlewareInterface::class);
        $services['short']->expects(self::once())->method('process')
            ->willReturnCallback(static function () use ($primary, &$events): never {
                $events[] = 'pipeline-failure';
                throw $primary;
            });
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = self::pipeline($services, $terminal, 'app', true);

        self::assertSame($response, $pipeline->handle($this->createStub(ServerRequestInterface::class)));
        self::assertSame([
            'system-post:enter',
            'app-post:enter',
            'route-post:enter',
            'pipeline-failure',
        ], $events);
    }

    /**
     * @param array<string, MiddlewareInterface> $services
     */
    private static function pipeline(
        array $services,
        RequestHandlerInterface $terminal,
        string $shortCircuitSlot,
        bool $withErrorBoundary = false,
    ): Pipeline {
        $tags = new TagRegistry();
        if ($withErrorBoundary) {
            $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, HttpErrorHandlingMiddleware::class, 1000);
        }
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST, 'system-post', 100);
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_APP_POST, 'app-post', 100);
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_ROUTE_POST, 'route-post', 100);
        $tag = match ($shortCircuitSlot) {
            'system' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM,
            'app' => ReservedTags::HTTP_MIDDLEWARE_APP,
            'route' => ReservedTags::HTTP_MIDDLEWARE_ROUTE,
            default => throw new \LogicException('unknown-short-circuit-slot'),
        };
        $tags->add($tag, 'short', 100);
        $http = require __DIR__ . '/../../config/http.php';
        $stack = new HttpMiddlewareStackBuilder($tags);
        self::assertSame([
            ...($withErrorBoundary ? [HttpErrorHandlingMiddleware::class] : []),
            'system-post',
            'app-post',
            'route-post',
            'short',
        ], $stack->build($http));
        $container = new class($services) implements ContainerInterface {
            /** @param array<string, MiddlewareInterface> $services */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                return $this->services[$id] ?? throw new \RuntimeException('http-test-middleware-missing');
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };

        return HttpServiceFactory::pipeline(
            $container,
            new ArrayConfigRepository(['http' => $http]),
            $stack,
            $terminal,
        );
    }
}
