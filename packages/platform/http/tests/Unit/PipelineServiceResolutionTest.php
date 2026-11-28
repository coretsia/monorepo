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

use Coretsia\Platform\Http\Middleware\Pipeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PipelineServiceResolutionTest extends TestCase
{
    public function testExactPublicServiceResolutionAndConstructorApi(): void
    {
        $class = new \ReflectionClass(Pipeline::class);
        self::assertTrue($class->isFinal());
        self::assertTrue($class->isReadOnly());
        self::assertTrue($class->implementsInterface(RequestHandlerInterface::class));
        self::assertStringContainsString('@api', (string) $class->getDocComment());
        self::assertStringNotContainsString('@internal', (string) $class->getDocComment());

        $resolve = $class->getMethod('resolveServices');
        self::assertTrue($resolve->isStatic());
        self::assertFalse($resolve->returnsReference());
        self::assertSame('array', (string) $resolve->getReturnType());
        self::assertStringContainsString(
            '@param list<non-empty-string> $serviceIds',
            (string) $resolve->getDocComment(),
        );
        self::assertStringContainsString('@return list<MiddlewareInterface>', (string) $resolve->getDocComment());
        self::assertRequiredParameters($resolve, [
            'container' => ContainerInterface::class,
            'serviceIds' => 'array',
        ]);

        $constructor = $class->getConstructor();
        self::assertInstanceOf(\ReflectionMethod::class, $constructor);
        self::assertRequiredParameters($constructor, [
            'middleware' => 'array',
            'terminal' => RequestHandlerInterface::class,
            'guardedContinuation' => 'bool',
            'protectedRequestAttributes' => 'array',
            'protectRoutingInputs' => 'bool',
        ]);
        self::assertStringContainsString(
            '@param list<MiddlewareInterface> $middleware',
            (string) $constructor->getDocComment(),
        );
        self::assertStringContainsString(
            '@param list<non-empty-string> $protectedRequestAttributes',
            (string) $constructor->getDocComment(),
        );
        $handle = $class->getMethod('handle');
        self::assertFalse($handle->isStatic());
        self::assertFalse($handle->returnsReference());
        self::assertSame(ResponseInterface::class, (string) $handle->getReturnType());
        self::assertRequiredParameters($handle, ['request' => ServerRequestInterface::class]);
    }

    public function testEveryOccurrenceIsResolvedInSuppliedOrderWithoutDiscoveryOrCaching(): void
    {
        $calls = new \ArrayObject();
        $ids = [
            'z.middleware',
            'a.middleware',
            'z.middleware',
            PipelineServiceResolutionAutowireCandidate::class,
            ' opaque id ',
            '0',
        ];
        $instances = [];
        foreach ($ids as $id) {
            $instances[] = $this->createStub(MiddlewareInterface::class);
        }
        $offset = 0;
        $container = self::container($calls, static function (string $id) use ($instances, &$offset): mixed {
            return $instances[$offset++];
        });

        self::assertSame($instances, Pipeline::resolveServices($container, $ids));
        self::assertSame(\array_map(static fn (string $id): array => ['get', $id], $ids), $calls->getArrayCopy());
        self::assertNotSame($instances[0], $instances[2]);
        self::assertSame(\count($ids), $offset);
    }

    public function testSharedResolvedInstanceIsNotDeduplicated(): void
    {
        $calls = new \ArrayObject();
        $shared = $this->createStub(MiddlewareInterface::class);
        $container = self::container($calls, static fn (): MiddlewareInterface => $shared);

        self::assertSame([$shared, $shared, $shared], Pipeline::resolveServices($container, ['z', 'a', 'z']));
        self::assertSame([['get', 'z'], ['get', 'a'], ['get', 'z']], $calls->getArrayCopy());
    }

    public function testEmptyListDoesNotConsultContainer(): void
    {
        $calls = new \ArrayObject();
        $container = self::container($calls, static fn (): never => self::fail('unexpected-container-lookup'));

        self::assertSame([], Pipeline::resolveServices($container, []));
        self::assertSame([], $calls->getArrayCopy());
    }

    #[DataProvider('invalidServiceIds')]
    public function testMalformedServiceIdsFailBeforeAnyResolution(array $ids): void
    {
        $calls = new \ArrayObject();
        $container = self::container($calls, static fn (): never => self::fail('unexpected-container-lookup'));

        self::assertRejected(
            static fn (): array => Pipeline::resolveServices($container, $ids),
            'http-middleware-service-id-invalid',
        );
        self::assertSame([], $calls->getArrayCopy());
    }

    /** @return iterable<string, array{array}> */
    public static function invalidServiceIds(): iterable
    {
        yield 'map' => [['private-key' => 'middleware']];
        yield 'sparse' => [[0 => 'first', 2 => 'last']];
        yield 'nonzero-start' => [[1 => 'first']];
        yield 'empty-string' => [['first', '']];
        yield 'null' => [['first', null]];
        yield 'integer' => [['first', 42]];
        yield 'boolean' => [['first', false]];
        yield 'float' => [['first', 1.5]];
        yield 'array' => [['first', ['nested']]];
        yield 'object' => [['first', new \stdClass()]];
        yield 'stringable' => [
            [
                'first',
                new class() implements \Stringable {
                    public function __toString(): string
                    {
                        return 'middleware';
                    }
                },
            ],
        ];
    }

    #[DataProvider('unsupportedValues')]
    public function testUnsupportedResolvedValuesFailWithoutResolvingLaterServices(mixed $value): void
    {
        $calls = new \ArrayObject();
        $first = $this->createStub(MiddlewareInterface::class);
        $container = self::container($calls, static fn (string $id): mixed => $id === 'first' ? $first : $value);

        self::assertRejected(
            static fn (): array => Pipeline::resolveServices($container, ['first', 'invalid', 'unreached']),
            'http-middleware-instance-invalid',
        );
        self::assertSame([['get', 'first'], ['get', 'invalid']], $calls->getArrayCopy());
    }

    /** @return iterable<string, array{mixed}> */
    public static function unsupportedValues(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'integer' => [42];
        yield 'string' => ['private-value'];
        yield 'array' => [[]];
        yield 'object' => [new \stdClass()];
        yield 'callable' => [static fn (): null => null];
        yield 'class-name' => [MiddlewareInterface::class];
        yield 'handler-only' => [
            new class() implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new \LogicException('unexpected-handler-entry');
                }
            },
        ];
    }

    #[DataProvider('containerFailures')]
    public function testContainerFailuresPropagateUnchangedWithoutRetry(\Throwable $failure): void
    {
        $calls = new \ArrayObject();
        $first = $this->createStub(MiddlewareInterface::class);
        $container = self::container($calls, static function (string $id) use ($first, $failure): mixed {
            if ($id === 'first') {
                return $first;
            }

            throw $failure;
        });

        try {
            Pipeline::resolveServices($container, [
                'first',
                PipelineServiceResolutionAutowireCandidate::class,
                'unreached',
            ]);
            self::fail('container-failure-must-propagate');
        } catch (\Throwable $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame(
            [['get', 'first'], ['get', PipelineServiceResolutionAutowireCandidate::class]],
            $calls->getArrayCopy(),
        );
    }

    /** @return iterable<string, array{\Throwable}> */
    public static function containerFailures(): iterable
    {
        yield 'not-found' => [
            new class('missing-service') extends \RuntimeException implements NotFoundExceptionInterface {},
        ];
        yield 'container-error' => [
            new class('construction-failed', 7) extends \RuntimeException implements ContainerExceptionInterface {},
        ];
        yield 'admission' => [new \InvalidArgumentException('container-service-id-invalid')];
        yield 'same-token' => [new \InvalidArgumentException('http-middleware-instance-invalid')];
        yield 'error' => [new \Error('construction-error')];
    }

    #[DataProvider('invalidMiddlewareLists')]
    public function testConstructorRejectsInvalidMiddlewareLists(array $entries): void
    {
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');

        self::assertRejected(
            static fn (): Pipeline => new Pipeline($entries, $terminal, false, [], false),
            'http-pipeline-middleware-invalid',
        );
    }

    /** @return iterable<string, array{array}> */
    public static function invalidMiddlewareLists(): iterable
    {
        yield 'null' => [[null]];
        yield 'scalar' => [['middleware']];
        yield 'object' => [[new \stdClass()]];
    }

    public function testConstructorRejectsNonListKeysEvenForValidMiddleware(): void
    {
        $middleware = $this->createStub(MiddlewareInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        foreach ([['name' => $middleware], [1 => $middleware], [0 => $middleware, 2 => $middleware]] as $entries) {
            self::assertRejected(
                static fn (): Pipeline => new Pipeline($entries, $terminal, false, [], false),
                'http-pipeline-middleware-invalid',
            );
        }
    }

    public function testOrdinaryConstructorPreservesOccurrencesAndTerminalIdentity(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects(self::exactly(4))->method('process')->willReturnCallback(
            static fn (
                ServerRequestInterface $incoming,
                RequestHandlerInterface $next,
            ): ResponseInterface => $next->handle($incoming),
        );
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::exactly(3))->method('handle')
            ->with(self::identicalTo($request))->willReturn($response);
        $pipeline = new Pipeline([$middleware, $middleware], $terminal, false, [], false);

        self::assertSame($response, $pipeline->handle($request));
        self::assertSame($response, $pipeline->handle($request));
        self::assertSame($response, new Pipeline([], $terminal, false, [], false)->handle($request));
    }

    #[DataProvider('globalPolicies')]
    public function testReusableAndInvocationLocalCompositionsShareResolutionWithoutPolicyOrServiceCache(
        bool $guarded,
    ): void {
        $calls = new \ArrayObject();
        $events = [];
        $request = $this->createStub(ServerRequestInterface::class);
        $state = new \stdClass();
        $request->method('getAttributes')->willReturn(['test.handoff' => $state]);
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/items/%41');
        $uri->method('getHost')->willReturn('example.test');
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('GET');
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willReturnCallback(static function (ServerRequestInterface $incoming) use (
            &$events,
            $request,
            $response,
        ): ResponseInterface {
            self::assertSame($request, $incoming);
            $events[] = 'terminal';

            return $response;
        });
        $instances = [];
        $container = self::container($calls, function (string $id) use (&$events, &$instances): MiddlewareInterface {
            $middleware = $this->createStub(MiddlewareInterface::class);
            $middleware->method('process')->willReturnCallback(static function (
                ServerRequestInterface $incoming,
                RequestHandlerInterface $next,
            ) use ($id, &$events): ResponseInterface {
                $events[] = $id . ':enter';
                $response = $next->handle($incoming);
                $events[] = $id . ':leave';

                return $response;
            });
            $instances[] = $middleware;

            return $middleware;
        });
        $local = $this->createStub(RequestHandlerInterface::class);
        $local->method('handle')->willReturnCallback(static function (ServerRequestInterface $incoming) use (
            $container,
            $terminal,
        ): ResponseInterface {
            return new Pipeline(
                Pipeline::resolveServices($container, ['z.route', 'a.route']),
                $terminal,
                true,
                ['test.handoff'],
                true,
            )->handle($incoming);
        });
        $global = new Pipeline(
            Pipeline::resolveServices($container, ['global']),
            $local,
            $guarded,
            $guarded ? ['test.handoff'] : [],
            $guarded,
        );
        self::assertSame([['get', 'global']], $calls->getArrayCopy());

        for ($i = 0; $i < 2; ++$i) {
            $events = [];
            self::assertSame($response, $global->handle($request));
            self::assertSame([
                'global:enter',
                'z.route:enter',
                'a.route:enter',
                'terminal',
                'a.route:leave',
                'z.route:leave',
                'global:leave',
            ], $events);
        }
        self::assertSame([
            ['get', 'global'],
            ['get', 'z.route'],
            ['get', 'a.route'],
            ['get', 'z.route'],
            ['get', 'a.route'],
        ], $calls->getArrayCopy());
        self::assertCount(5, $instances);
        self::assertNotSame($instances[1], $instances[3]);
        self::assertNotSame($instances[2], $instances[4]);
    }

    /** @return iterable<string, array{bool}> */
    public static function globalPolicies(): iterable
    {
        yield 'ordinary-global' => [false];
        yield 'guarded-global' => [true];
    }

    /** @param array<string, string> $expected */
    private static function assertRequiredParameters(\ReflectionMethod $method, array $expected): void
    {
        self::assertTrue($method->isPublic());
        self::assertSame(\count($expected), $method->getNumberOfParameters());
        self::assertSame(\count($expected), $method->getNumberOfRequiredParameters());
        $actual = [];
        foreach ($method->getParameters() as $parameter) {
            self::assertFalse($parameter->isOptional());
            self::assertFalse($parameter->isDefaultValueAvailable());
            self::assertFalse($parameter->allowsNull());
            self::assertFalse($parameter->isPassedByReference());
            self::assertFalse($parameter->isVariadic());
            $actual[$parameter->getName()] = (string) $parameter->getType();
        }
        self::assertSame($expected, $actual);
    }

    private static function assertRejected(\Closure $operation, string $reason): void
    {
        try {
            $operation();
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(\InvalidArgumentException::class, $exception::class);
            self::assertSame($reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());

            return;
        }

        self::fail('invalid-pipeline-input-accepted');
    }

    private static function container(\ArrayObject $calls, \Closure $resolve): ContainerInterface
    {
        return new class($calls, $resolve) implements ContainerInterface {
            public function __construct(private \ArrayObject $calls, private \Closure $resolve)
            {
            }

            public function get(string $id): mixed
            {
                $this->calls->append(['get', $id]);

                return ($this->resolve)($id);
            }

            public function has(string $id): bool
            {
                $this->calls->append(['has', $id]);
                throw new \LogicException('unexpected-container-probe');
            }
        };
    }
}

final class PipelineServiceResolutionAutowireCandidate implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new \LogicException('unexpected-independent-autowire');
    }
}
