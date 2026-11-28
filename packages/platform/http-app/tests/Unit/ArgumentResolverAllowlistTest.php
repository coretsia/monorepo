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
use Coretsia\Contracts\HttpApp\ArgumentResolverInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Foundation\Container\Container;
use Coretsia\Platform\HttpApp\Action\ArgumentResolver;
use Coretsia\Platform\HttpApp\Action\HandlerResolver;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ArgumentResolverAllowlistTest extends TestCase
{
    #[DataProvider('sourcePolicies')]
    public function testExactNamedArgumentsAndIndependentSourcePolicies(bool $requestEnabled, bool $routeEnabled): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $service = new ArgumentResolverService();
        $action = new class() {
            public function __invoke(
                ?ServerRequestInterface $request = null,
                string $slug = 'default-slug',
                mixed $mixed = 'default-mixed',
                $untyped = 'default-untyped',
                ?ArgumentResolverService $service = null,
                string $caseSensitive = 'default-case',
                int $limit = 7,
            ): never {
                throw new \LogicException('argument-resolution-must-not-invoke-action');
            }
        };
        $container = new Container(instances: [
            'action.service' => $action,
            ArgumentResolverService::class => $service,
            ServerRequestInterface::class => $request,
        ]);
        $resolver = $this->resolver($container, [ArgumentResolverService::class], $requestEnabled, $routeEnabled);
        $match = new RouteMatch('route', '/items/{slug}', 'action', [
            'slug' => 'a%2Fb',
            'mixed' => '%41',
            'untyped' => '007',
            'casesensitive' => 'wrong-case',
        ], ['limit' => 999, 'slug' => 'metadata-value']);
        $expected = [
            'request' => $requestEnabled ? $request : null,
            'slug' => $routeEnabled ? 'a%2Fb' : 'default-slug',
            'mixed' => $routeEnabled ? '%41' : 'default-mixed',
            'untyped' => $routeEnabled ? '007' : 'default-untyped',
            'service' => $service,
            'caseSensitive' => 'default-case',
            'limit' => 7,
        ];
        self::assertSame($expected, $resolver->resolveForAction($action, $match, $request));
        $expected['request'] = null;
        self::assertInstanceOf(ArgumentResolverInterface::class, $resolver);
        self::assertSame($expected, $resolver->resolve('action', $match, [
            'request' => 'not-a-transport-object',
            'slug' => 'context-value',
            'limit' => 999,
        ]));
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function sourcePolicies(): iterable
    {
        yield 'both-enabled' => [true, true];
        yield 'request-only' => [true, false];
        yield 'route-only' => [false, true];
        yield 'both-disabled' => [false, false];
    }

    public function testRequestSourcePrecedesIncompatibleRouteParameterButOnlyForExactType(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $action = new class() {
            public function __invoke(ServerRequestInterface $request): never
            {
                throw new \LogicException('action-executed');
            }
        };
        $container = new Container(instances: ['action.service' => $action, ServerRequestInterface::class => $request]);
        $match = new RouteMatch('route', '/items/{request}', 'action', ['request' => 'private-route-value']);
        $resolver = $this->resolver($container, [], true, true);
        self::assertSame(['request' => $request], $resolver->resolveForAction($action, $match, $request));
        self::assertRejected(static fn () => $resolver->resolve('action', $match), 'http-app-argument-invalid');
        $disabled = $this->resolver($container, [], false, true);
        self::assertRejected(
            static fn () => $disabled->resolveForAction($action, $match, $request),
            'http-app-argument-invalid',
        );
        $other = new class() {
            public function __invoke(RequestInterface $request): never
            {
                throw new \LogicException('action-executed');
            }
        };
        $noRoute = new RouteMatch('route', '/items', 'action');
        self::assertRejected(
            static fn () => $resolver->resolveForAction($other, $noRoute, $request),
            'http-app-argument-unresolvable',
        );
        self::assertRejected(
            static fn () => $resolver->resolve('other', $noRoute),
            'http-app-argument-invalid',
        );
    }

    #[DataProvider('incompatibleRouteActions')]
    public function testSelectedIncompatibleRouteParameterNeverFallsThrough(object $action): void
    {
        $lookups = 0;
        $container = new Container(
            definitions: [
                ArgumentResolverService::class => static function () use (&$lookups): ArgumentResolverService {
                    ++$lookups;

                    return new ArgumentResolverService();
                },
            ],
            instances: ['action.service' => $action],
        );
        $resolver = $this->resolver($container, [ArgumentResolverService::class], false, true);
        $match = new RouteMatch('route', '/items/{value}', 'action', ['value' => '123']);
        self::assertRejected(static fn () => $resolver->resolve('action', $match), 'http-app-argument-invalid');
        self::assertSame(0, $lookups);
    }

    /** @return iterable<string, array{object}> */
    public static function incompatibleRouteActions(): iterable
    {
        yield 'int' => [
            new class() {
                public function __invoke(int $value = 7): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
        yield 'float' => [
            new class() {
                public function __invoke(float $value = 7.0): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
        yield 'bool' => [
            new class() {
                public function __invoke(bool $value = false): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
        yield 'array' => [
            new class() {
                public function __invoke(array $value = []): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
        yield 'object' => [
            new class() {
                public function __invoke(?ArgumentResolverService $value = null): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
        yield 'callable' => [
            new class() {
                public function __invoke(?callable $value = null): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
    }

    public function testConcreteTypeRequiresExactAllowlistAndExplicitRegistration(): void
    {
        $action = new class() {
            public function __invoke(ArgumentResolverService $service): never
            {
                throw new \LogicException('argument-resolution-must-not-invoke-action');
            }
        };
        $match = new RouteMatch('route', '/items', 'action');
        $container = new Container(instances: ['action.service' => $action], config: self::autowireConfig());
        self::assertTrue($container->has(ArgumentResolverService::class));
        self::assertSame(['action.service'], $container->serviceIds());
        $resolver = $this->resolver($container, [ArgumentResolverService::class], false, false);
        self::assertRejected(static fn () => $resolver->resolve('action', $match), 'http-app-argument-unresolvable');
        $service = new ArgumentResolverService();
        $calls = 0;
        $registered = new Container(
            definitions: [
                ArgumentResolverService::class => static function () use ($service, &$calls): ArgumentResolverService {
                    ++$calls;

                    return $service;
                },
            ],
            instances: ['action.service' => $action],
        );
        foreach ([[], [\strtolower(ArgumentResolverService::class)]] as $allowlist) {
            $denied = $this->resolver($registered, $allowlist, false, false);
            self::assertRejected(static fn () => $denied->resolve('action', $match), 'http-app-argument-unresolvable');
        }
        self::assertSame(0, $calls);
        $allowed = $this->resolver($registered, [ArgumentResolverService::class], false, false);
        self::assertSame(['service' => $service], $allowed->resolve('action', $match));
        self::assertSame(1, $calls);
    }

    public function testSelectedUnavailableOrIneligibleTypesCannotFallThroughToDefaults(): void
    {
        $service = new ArgumentResolverService();
        $match = new RouteMatch('route', '/items', 'action');
        $actions = [
            ArgumentResolverServiceInterface::class => new class() {
                public function __invoke(?ArgumentResolverServiceInterface $value = null): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
            ArgumentResolverAbstractService::class => new class() {
                public function __invoke(?ArgumentResolverAbstractService $value = null): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
            ArgumentResolverEnum::class => new class() {
                public function __invoke(?ArgumentResolverEnum $value = null): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
        foreach ($actions as $type => $action) {
            $calls = 0;
            $container = new Container(
                definitions: [
                    $type => static function () use ($service, &$calls): object {
                        ++$calls;

                        return $service;
                    },
                ],
                instances: ['action.service' => $action],
            );
            $resolver = $this->resolver($container, [$type], false, false);
            self::assertRejected(
                static fn () => $resolver->resolve('action', $match),
                'http-app-argument-unresolvable',
            );
            self::assertSame(0, $calls);
        }
        $optional = new class() {
            public function __invoke(?ArgumentResolverService $value = null): never
            {
                throw new \LogicException('argument-resolution-must-not-invoke-action');
            }
        };

        $unregistered = new Container(
            instances: ['action.service' => $optional],
            config: self::autowireConfig(),
        );
        self::assertTrue($unregistered->has(ArgumentResolverService::class));
        $resolver = $this->resolver($unregistered, [ArgumentResolverService::class], false, false);
        self::assertRejected(static fn () => $resolver->resolve('action', $match), 'http-app-argument-unresolvable');

        foreach (
            [
                new \stdClass(),
                null,
                static fn () => throw new \RuntimeException('private-service-failure'),
            ] as $definition
        ) {
            $container = new Container(
                definitions: [ArgumentResolverService::class => $definition],
                instances: ['action.service' => $optional],
            );
            $resolver = $this->resolver($container, [ArgumentResolverService::class], false, false);
            self::assertRejected(
                static fn () => $resolver->resolve('action', $match),
                'http-app-argument-unresolvable',
            );
        }
    }

    public function testRelativeTypeNamesAreNotExpandedForContainerInjection(): void
    {
        $action = new ArgumentResolverRelativeAction();
        $container = new Container(instances: [
            'action.service' => $action,
            ArgumentResolverRelativeAction::class => $action,
        ]);
        $resolver = $this->resolver($container, [ArgumentResolverRelativeAction::class], false, false);
        $match = new RouteMatch('route', '/items', 'action');
        self::assertRejected(static fn () => $resolver->resolve('action', $match), 'http-app-argument-unresolvable');
    }

    public function testNullableRequiredArgumentIsNotAnImplicitDefault(): void
    {
        $action = new class() {
            public function __invoke(?string $required): never
            {
                throw new \LogicException('argument-resolution-must-not-invoke-action');
            }
        };
        $resolver = $this->resolver(new Container(instances: ['action.service' => $action]), [], false, true);
        $match = new RouteMatch('route', '/items', 'action', ['Required' => 'private-value']);
        self::assertRejected(static fn () => $resolver->resolve('action', $match), 'http-app-argument-unresolvable');
    }

    #[DataProvider('unsupportedSignatures')]
    public function testUnsupportedSignaturesCannotEnterArgumentResolution(object $action): void
    {
        $resolver = $this->resolver(new Container(instances: ['action.service' => $action]), [], true, true);
        $match = new RouteMatch('route', '/items/{value}', 'action', ['value' => 'private-value']);
        self::assertRejected(static fn () => $resolver->resolve('action', $match), 'http-app-handler-invalid');
        self::assertRejected(
            static fn () => $resolver->resolveForAction($action, $match, null),
            'http-app-handler-invalid',
        );
    }

    /** @return iterable<string, array{object}> */
    public static function unsupportedSignatures(): iterable
    {
        yield 'reference' => [
            new class() {
                public function __invoke(string &$value): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
        yield 'variadic' => [
            new class() {
                public function __invoke(string ...$value): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
        yield 'union' => [
            new class() {
                public function __invoke(string|int $value): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
        yield 'intersection' => [
            new class() {
                public function __invoke(\Countable&\Iterator $value): never
                {
                    throw new \LogicException('argument-resolution-must-not-invoke-action');
                }
            },
        ];
    }

    public function testContainerTypeValidationIsClosedAndDoesNotLoadClasses(): void
    {
        $types = ['Synthetic\\NotLoaded', ArgumentResolverService::class];
        self::assertSame($types, ArgumentResolver::validateContainerTypes($types));
        foreach (
            [
                null,
                'private-type',
                ['x' => ArgumentResolverService::class],
                [null],
                [''],
                [' A'],
                [ArgumentResolverService::class, ArgumentResolverService::class],
            ] as $invalid
        ) {
            try {
                ArgumentResolver::validateContainerTypes($invalid);
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('http-app-container-types-invalid', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                continue;
            }

            self::fail('invalid-container-type-allowlist-accepted');
        }
    }

    /** @param list<class-string> $types */
    private function resolver(Container $container, array $types, bool $request, bool $route): ArgumentResolver
    {
        $config = $this->createMock(ConfigRepositoryInterface::class);
        $config->expects(self::exactly(2))->method('get')->willReturnCallback(
            static function (string $key, mixed $default = null) use ($types): array {
                self::assertNull($default);

                return match ($key) {
                    'http_app.handlers' => [['action_id' => 'action', 'service_id' => 'action.service']],
                    'http_app.arguments.allow_container_types' => $types,
                    default => throw new \LogicException('unexpected-config-key'),
                };
            },
        );

        return new ArgumentResolver(new HandlerResolver($container, $config), $container, $request, $route, $config);
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

        self::fail('invalid-argument-resolution-accepted');
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

interface ArgumentResolverServiceInterface
{
}

abstract class ArgumentResolverAbstractService implements ArgumentResolverServiceInterface
{
}

final class ArgumentResolverService extends ArgumentResolverAbstractService
{
}

final class ArgumentResolverRelativeAction
{
    public function __invoke(self $value): never
    {
        throw new \LogicException('argument-resolution-must-not-invoke-action');
    }
}

enum ArgumentResolverEnum
{
    case Value;
}
