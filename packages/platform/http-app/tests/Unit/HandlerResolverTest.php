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
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Platform\HttpApp\Action\HandlerResolver;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HandlerResolverTest extends TestCase
{
    public function testExplicitLogicalIdentifiersAndCanonicalAliases(): void
    {
        $action = new HandlerResolverInheritedAction();
        $builder = new ContainerBuilder(self::autowireConfig());
        $builder->instance(HandlerResolverInheritedAction::class, $action);
        $container = $builder->applyDefinitions(
            new ContainerDefinitionBuilder()->alias('action.alias', HandlerResolverInheritedAction::class)->build(),
        )->build();
        $ids = ['_', '_show', 'Show.item-edit:detail', 'show.item-edit:detail', \str_repeat('a', 256)];
        $registrations = [];
        foreach ($ids as $id) {
            $registrations[] = ['service_id' => 'action.alias', 'action_id' => $id];
        }
        $registrations[] = ['action_id' => 'direct', 'service_id' => HandlerResolverInheritedAction::class];
        $resolver = $this->resolver($container, $registrations);
        self::assertSame($action, $container->get('action.alias'));
        foreach ([...$ids, 'direct'] as $id) {
            self::assertSame($action, $resolver->resolve($id));
        }
        self::assertSame(0, $action->calls);

        foreach (
            [
                'action.alias',
                HandlerResolverInheritedAction::class,
                'SHOW.item-edit:detail',
                ' _show',
                '_show ',
            ] as $id
        ) {
            self::assertRejected(static fn () => $resolver->resolve($id), 'http-app-handler-unmapped');
        }
    }

    #[DataProvider('invalidRegistrations')]
    public function testRejectsInvalidRegistrationRecords(mixed $registrations): void
    {
        $container = new Container();
        foreach (
            [
                static fn () => HandlerResolver::validateRegistrations($registrations),
                fn () => $this->resolver($container, $registrations),
            ] as $operation
        ) {
            try {
                $operation();
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('http-app-handlers-invalid', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                continue;
            }

            self::fail('invalid-handler-registration-accepted');
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidRegistrations(): iterable
    {
        yield 'null' => [null];
        yield 'scalar' => ['private-registration'];
        yield 'map' => [['action' => 'service']];
        yield 'sparse-list' => [[1 => ['action_id' => 'action', 'service_id' => 'service']]];
        yield 'scalar-record' => [['private-record']];
        yield 'missing-action' => [[['service_id' => 'service']]];
        yield 'missing-service' => [[['action_id' => 'action']]];
        yield 'extra-key' => [[['action_id' => 'action', 'service_id' => 'service', 'extra' => true]]];
        yield 'wrong-key' => [[['action_id' => 'action', 'service' => 'service']]];
        yield 'positional-record' => [[['action', 'service']]];
        foreach (
            [
                '',
                '1action',
                '.action',
                '-action',
                ':action',
                'a b',
                "a\n",
                'é',
                'A\\B',
                \str_repeat('a', 257),
                null,
                1,
            ] as $i => $id
        ) {
            yield 'action-' . $i => [[['action_id' => $id, 'service_id' => 'service']]];
        }
        foreach (
            [
                '',
                ' service',
                'service ',
                '123',
                \str_repeat('s', 257),
                "private\nservice",
                "private\x7f",
                null,
                1,
            ] as $i => $id
        ) {
            yield 'service-' . $i => [[['action_id' => 'action', 'service_id' => $id]]];
        }
        foreach (['service', 'other'] as $id) {
            yield 'duplicate-' . $id => [
                [
                    ['action_id' => 'action', 'service_id' => 'service'],
                    ['action_id' => 'action', 'service_id' => $id],
                ],
            ];
        }
    }

    public function testUnregisteredConcreteAndMissingServiceNeverUseAutowire(): void
    {
        $container = new Container(config: self::autowireConfig());
        self::assertTrue($container->has(HandlerResolverInheritedAction::class));
        self::assertSame([], $container->serviceIds());
        $resolver = $this->resolver($container, [
            ['action_id' => 'concrete', 'service_id' => HandlerResolverInheritedAction::class],
            ['action_id' => 'missing', 'service_id' => 'private.missing'],
        ]);
        foreach (['concrete', 'missing'] as $id) {
            self::assertRejected(static fn () => $resolver->resolve($id), 'http-app-handler-unavailable');
        }
        self::assertRejected(
            static fn () => $resolver->resolve(HandlerResolverInheritedAction::class),
            'http-app-handler-unmapped',
        );
        self::assertSame([], $container->serviceIds());
    }

    #[DataProvider('invalidActions')]
    public function testRejectsUnsupportedResolvedHandlersWithoutExecution(mixed $action): void
    {
        $container = new Container(instances: ['private.service' => $action]);
        $resolver = $this->resolver($container, [['action_id' => 'private.action', 'service_id' => 'private.service']]);
        self::assertRejected(static fn () => $resolver->resolve('private.action'), 'http-app-handler-invalid');
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidActions(): iterable
    {
        yield 'null' => [null];
        yield 'scalar' => ['strlen'];
        yield 'array' => [[HandlerResolverInheritedAction::class, '__invoke']];
        yield 'object' => [new \stdClass()];
        yield 'closure' => [static fn () => self::fail('closure-executed')];
        yield 'magic-only' => [
            new class() {
                public function __call(string $name, array $arguments): never
                {
                    throw new \LogicException('magic-executed');
                }
            },
        ];
        yield 'reference' => [
            new class() {
                public function __invoke(string &$value): never
                {
                    throw new \LogicException('action-executed');
                }
            },
        ];
        yield 'variadic' => [
            new class() {
                public function __invoke(string ...$values): never
                {
                    throw new \LogicException('action-executed');
                }
            },
        ];
        yield 'union' => [
            new class() {
                public function __invoke(string|int $value): never
                {
                    throw new \LogicException('action-executed');
                }
            },
        ];
        yield 'intersection' => [
            new class() {
                public function __invoke(\Countable&\Iterator $value): never
                {
                    throw new \LogicException('action-executed');
                }
            },
        ];
    }

    public function testContainerFailureIsSanitizedAndNonSharedActionsAreNotRetained(): void
    {
        $calls = 0;
        $container = new Container(
            definitions: [
                'broken' => static fn () => throw new \RuntimeException('private-construction-details'),
                'fresh' => static function () use (&$calls): HandlerResolverInheritedAction {
                    ++$calls;

                    return new HandlerResolverInheritedAction();
                },
            ],
            definitionShared: ['fresh' => false],
        );
        $resolver = $this->resolver($container, [
            ['action_id' => 'broken', 'service_id' => 'broken'],
            ['action_id' => 'fresh', 'service_id' => 'fresh'],
        ]);
        self::assertSame(0, $calls);
        self::assertRejected(static fn () => $resolver->resolve('broken'), 'http-app-handler-unavailable');
        $first = $resolver->resolve('fresh');
        $second = $resolver->resolve('fresh');
        self::assertNotSame($first, $second);
        self::assertSame(2, $calls);
        $reference = \WeakReference::create($first);
        unset($first);
        self::assertNull($reference->get());
        $class = new \ReflectionClass($resolver);
        self::assertTrue($class->isReadOnly());
        $properties = \array_map(static fn (\ReflectionProperty $p): string => $p->getName(), $class->getProperties());
        \sort($properties);
        self::assertSame(['container', 'registrations'], $properties);
    }

    private function resolver(Container $container, mixed $registrations): HandlerResolver
    {
        $config = $this->createMock(ConfigRepositoryInterface::class);
        $config->expects(self::once())->method('get')
            ->with('http_app.handlers', self::identicalTo(null))->willReturn($registrations);
        $config->expects(self::never())->method('all');
        $config->expects(self::never())->method('has');

        return new HandlerResolver($container, $config);
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

        self::fail('invalid-handler-accepted');
    }

    /** @return array<string, mixed> */
    private static function autowireConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
            ],
        ];
    }
}

class HandlerResolverParentAction
{
    public int $calls = 0;

    public function __invoke(string $value = 'default'): string
    {
        ++$this->calls;

        return $value;
    }
}

final class HandlerResolverInheritedAction extends HandlerResolverParentAction
{
}
