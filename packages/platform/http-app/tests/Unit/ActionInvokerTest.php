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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\HttpApp\ActionInvokerInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Foundation\Container\Container;
use Coretsia\Platform\HttpApp\Action\ActionInvoker;
use Coretsia\Platform\HttpApp\Action\ArgumentResolver;
use Coretsia\Platform\HttpApp\Action\HandlerResolver;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class ActionInvokerTest extends TestCase
{
    public function testExactPublicPortAndNamedInvocationPreserveDefaultsAndResult(): void
    {
        $action = new ActionInvokerNamedAction();
        [$invoker] = $this->services(new Container(instances: ['action.service' => $action]));
        self::assertInstanceOf(ActionInvokerInterface::class, $invoker);
        $method = new \ReflectionMethod($invoker, 'invoke');
        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertFalse($method->returnsReference());
        self::assertSame('mixed', (string) $method->getReturnType());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());
        [$id, $arguments] = $method->getParameters();
        self::assertSame('actionId', $id->getName());
        self::assertSame('string', (string) $id->getType());
        self::assertSame('arguments', $arguments->getName());
        self::assertSame('array', (string) $arguments->getType());
        self::assertSame([], $arguments->getDefaultValue());
        foreach ([$id, $arguments] as $parameter) {
            self::assertFalse($parameter->isPassedByReference());
            self::assertFalse($parameter->isVariadic());
            self::assertFalse($parameter->allowsNull());
        }
        $result = new \stdClass();
        self::assertSame([$result, 'encoded%2F', 7], $invoker->invoke('action', [
            'slug' => 'encoded%2F',
            'result' => $result,
        ]));
        self::assertSame([$result, 'other', 12], $invoker->invoke('action', [
            'limit' => 12,
            'slug' => 'other',
            'result' => $result,
        ]));
        self::assertSame(2, $action->calls);
    }

    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentMapsFailBeforeActionExecution(array $arguments): void
    {
        $action = new ActionInvokerNamedAction();
        [$invoker] = $this->services(new Container(instances: ['action.service' => $action]));
        self::assertRejected(static fn () => $invoker->invoke('action', $arguments), 'http-app-argument-invalid');
        self::assertSame(0, $action->calls);
    }

    /** @return iterable<string, array{array}> */
    public static function invalidArguments(): iterable
    {
        yield 'positional' => [[new \stdClass(), 'private-value']];
        yield 'mixed-keys' => [['result' => new \stdClass(), 'slug' => 'private-value', 0 => 7]];
        yield 'numeric-string-key' => [['result' => new \stdClass(), 'slug' => 'private-value', '0' => 7]];
        yield 'unknown' => [['result' => new \stdClass(), 'slug' => 'private-value', 'unknown' => 'private-argument']];
        yield 'wrong-case' => [['result' => new \stdClass(), 'slug' => 'private-value', 'Limit' => 7]];
        yield 'missing-result' => [['slug' => 'private-value']];
        yield 'missing-slug' => [['result' => new \stdClass()]];
        yield 'empty' => [[]];
    }

    #[DataProvider('unsupportedSignatures')]
    public function testDirectPortCannotBypassHandlerAdmission(object $action, array $arguments): void
    {
        [$invoker] = $this->services(new Container(instances: ['action.service' => $action]));
        self::assertRejected(static fn () => $invoker->invoke('action', $arguments), 'http-app-handler-invalid');
    }

    /** @return iterable<string, array{object, array}> */
    public static function unsupportedSignatures(): iterable
    {
        yield 'closure' => [static fn () => self::fail('unsupported-action-executed'), []];
        yield 'reference' => [
            new class() {
                public function __invoke(string &$value): never
                {
                    throw new \LogicException('unsupported-action-executed');
                }
            },
            ['value' => 'value'],
        ];
        yield 'variadic' => [
            new class() {
                public function __invoke(string ...$value): never
                {
                    throw new \LogicException('unsupported-action-executed');
                }
            },
            [],
        ];
        yield 'union' => [
            new class() {
                public function __invoke(string|int $value): never
                {
                    throw new \LogicException('unsupported-action-executed');
                }
            },
            ['value' => 'value'],
        ];
        yield 'intersection' => [
            new class() {
                public function __invoke(\Countable&\Iterator $value): never
                {
                    throw new \LogicException('unsupported-action-executed');
                }
            },
            ['value' => new \ArrayIterator()],
        ];
    }

    #[DataProvider('invalidNativeTypes')]
    public function testNativeTypeErrorsEscapeWithoutCoercionOrNormalization(mixed $limit): void
    {
        $action = new ActionInvokerNamedAction();
        [$invoker] = $this->services(new Container(instances: ['action.service' => $action]));
        try {
            $invoker->invoke('action', ['result' => new \stdClass(), 'slug' => 'value', 'limit' => $limit]);
        } catch (\TypeError $exception) {
            self::assertSame(\TypeError::class, $exception::class);
            self::assertNull($exception->getPrevious());
            self::assertSame(0, $action->calls);

            return;
        }

        self::fail('native-invocation-type-error-not-propagated');
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidNativeTypes(): iterable
    {
        yield 'numeric-string' => ['7'];
        yield 'float' => [7.0];
        yield 'bool' => [true];
        yield 'null' => [null];
    }

    #[DataProvider('actionFailures')]
    public function testActionThrowablePropagatesUnchangedThroughBothEntrypoints(\Throwable $failure): void
    {
        $action = new class($failure) {
            public int $calls = 0;

            public function __construct(private readonly \Throwable $failure)
            {
            }

            public function __invoke(): never
            {
                ++$this->calls;
                throw $this->failure;
            }
        };
        [$invoker] = $this->services(new Container(instances: ['action.service' => $action]));
        $request = $this->createStub(ServerRequestInterface::class);
        $match = new RouteMatch('route', '/items', 'action');
        foreach (
            [
                static fn () => $invoker->invoke('action'),
                static fn () => $invoker->dispatch($match, $request),
            ] as $operation
        ) {
            try {
                $operation();
                self::fail('action-failure-must-escape');
            } catch (\Throwable $caught) {
                self::assertSame($failure, $caught);
            }
        }
        self::assertSame(2, $action->calls);
    }

    /** @return iterable<string, array{\Throwable}> */
    public static function actionFailures(): iterable
    {
        yield 'exception' => [new \RuntimeException('private-action-failure')];
        yield 'error' => [new \Error('private-action-error')];
        yield 'dispatch-exception' => [HttpAppDispatchException::withReason('http-app-argument-invalid')];
    }

    public function testDispatchUsesOneResolvedInstanceAndRetainsNoInvocationState(): void
    {
        $resolutions = 0;
        $reference = null;
        $container = new Container(
            definitions: [
                'action.service' => static function () use (&$resolutions, &$reference): ActionInvokerRequestAction {
                    ++$resolutions;
                    $action = new ActionInvokerRequestAction($resolutions);
                    $reference = \WeakReference::create($action);

                    return $action;
                },
            ],
            definitionShared: ['action.service' => false],
        );
        [$invoker] = $this->services($container);
        $match = new RouteMatch('route', '/items/{slug}', 'action', ['slug' => 'a%2Fb']);
        $request = $this->createStub(ServerRequestInterface::class);
        $result = $invoker->dispatch($match, $request);
        self::assertSame(1, $resolutions);
        self::assertSame(1, $result->sequence);
        self::assertSame($request, $result->request);
        self::assertSame('a%2Fb', $result->slug);
        self::assertInstanceOf(\WeakReference::class, $reference);
        self::assertNull($reference->get());
        $resultReference = \WeakReference::create($result);
        unset($result, $request);
        self::assertNull($resultReference->get());
        $next = $invoker->dispatch($match, $this->createStub(ServerRequestInterface::class));
        self::assertSame(2, $resolutions);
        self::assertSame(2, $next->sequence);
        self::assertNull($reference->get());
        $class = new \ReflectionClass($invoker);
        self::assertTrue($class->isReadOnly());
        $properties = \array_map(static fn (\ReflectionProperty $p): string => $p->getName(), $class->getProperties());
        \sort($properties);
        self::assertSame(['argumentResolver', 'handlerResolver'], $properties);
        $resolver = new \ReflectionProperty($invoker, 'argumentResolver')->getValue($invoker);
        $resolverClass = new \ReflectionClass($resolver);
        self::assertTrue($resolverClass->isReadOnly());
        $properties = \array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            $resolverClass->getProperties(),
        );
        \sort($properties);
        self::assertSame(
            ['allowRequest', 'allowRouteParams', 'container', 'containerTypes', 'handlerResolver'],
            $properties
        );
    }

    public function testDirectInvocationDoesNotRetainArgumentsOrResult(): void
    {
        $action = new ActionInvokerNamedAction();
        [$invoker] = $this->services(new Container(instances: ['action.service' => $action]));
        $argument = new \stdClass();
        $reference = \WeakReference::create($argument);
        $result = $invoker->invoke('action', ['result' => $argument, 'slug' => 'value']);
        self::assertSame($argument, $result[0]);
        unset($result, $argument);
        self::assertNull($reference->get());
        self::assertSame(1, $action->calls);
    }

    public function testIndependentFormatNeutralPortsResolveNonSharedActionsSeparately(): void
    {
        $resolutions = 0;
        $container = new Container(
            definitions: [
                'action.service' => static function () use (&$resolutions): ActionInvokerSequenceAction {
                    return new ActionInvokerSequenceAction(++$resolutions);
                },
            ],
            definitionShared: ['action.service' => false],
        );
        [$invoker, $resolver] = $this->services($container);
        $match = new RouteMatch('route', '/items/{slug}', 'action', ['slug' => '%41']);
        $arguments = $resolver->resolve('action', $match);
        self::assertSame(['slug' => '%41'], $arguments);
        self::assertSame(1, $resolutions);
        self::assertSame([2, '%41'], $invoker->invoke('action', $arguments));
        self::assertSame(2, $resolutions);
    }

    /** @return array{ActionInvoker, ArgumentResolver} */
    private function services(Container $container): array
    {
        $config = $this->createMock(ConfigRepositoryInterface::class);
        $config->expects(self::exactly(2))->method('get')->willReturnCallback(
            static fn (string $key): array => match ($key) {
                'http_app.handlers' => [['action_id' => 'action', 'service_id' => 'action.service']],
                'http_app.arguments.allow_container_types' => [],
                default => throw new \LogicException('unexpected-config-key'),
            },
        );
        $handlers = new HandlerResolver($container, $config);
        $arguments = new ArgumentResolver($handlers, $container, true, true, $config);

        return [new ActionInvoker($handlers, $arguments), $arguments];
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

        self::fail('invalid-invocation-accepted');
    }
}

final class ActionInvokerNamedAction
{
    public int $calls = 0;

    /** @return array{object, string, int} */
    public function __invoke(object $result, string $slug, int $limit = 7): array
    {
        ++$this->calls;

        return [$result, $slug, $limit];
    }
}

final readonly class ActionInvokerRequestAction
{
    public function __construct(private int $sequence)
    {
    }

    public function __invoke(ServerRequestInterface $request, string $slug): object
    {
        return (object) ['sequence' => $this->sequence, 'request' => $request, 'slug' => $slug];
    }
}

final readonly class ActionInvokerSequenceAction
{
    public function __construct(private int $sequence)
    {
    }

    /** @return array{int, string} */
    public function __invoke(string $slug): array
    {
        return [$this->sequence, $slug];
    }
}
