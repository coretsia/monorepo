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

namespace Coretsia\Platform\Routing\Tests\Unit;

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Compile\RouteProviderRegistry;
use Coretsia\Platform\Routing\Definition\RouteGroup;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class RouteProviderRegistryTest extends TestCase
{
    public function testTagOrderFirstRegistrationAndLazyResolutionPreserveProviderRouteOrder(): void
    {
        $tags = new TagRegistry();
        $tag = ReservedTags::ROUTING_ROUTE_PROVIDER;
        $tags->add($tag, 'aa', 0);
        $tags->add($tag, 'b', 10);
        $tags->add($tag, 'a', 10);
        $tags->add($tag, 'a', -100, ['ignored' => true]);
        $tags->add(ReservedTags::CLI_COMMAND, 'unrelated', 100);
        $routes = RouteGroup::root()->group('/group', 'group', [], [])->routes([
            self::route('z'),
            self::route('a'),
        ]);
        $b = [self::route('m'), self::route('b')];
        $c = [self::route('c')];
        $container = self::container([
            'a' => self::provider('z.provider', $routes),
            'b' => self::provider('a.provider', $b),
            'aa' => self::provider('m.provider', $c),
        ]);
        $registry = new RouteProviderRegistry($tags, $container);
        self::assertSame([], $container->calls);
        self::assertSame([...$routes, ...$b, ...$c], $registry->routes());
        self::assertSame(['a', 'b', 'aa'], $container->calls);
        self::assertSame([...$routes, ...$b, ...$c], $registry->routes());
        self::assertSame(['a', 'b', 'aa', 'a', 'b', 'aa'], $container->calls);
    }

    public function testNonEmptyFirstMetadataIsRejectedBeforeResolution(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ROUTING_ROUTE_PROVIDER, 'provider', 0, ['invalid' => true]);
        $tags->add(ReservedTags::ROUTING_ROUTE_PROVIDER, 'provider', 100, []);
        $container = self::container(['provider' => self::provider('provider', [])]);
        self::assertRejected(
            new RouteProviderRegistry($tags, $container),
            RouteDefinitionInvalidException::REASON_PROVIDER_INVALID,
        );
        self::assertSame([], $container->calls);
    }

    public function testInvalidServicesIdentitiesAndProviderOutputsAreRejected(): void
    {
        foreach ([null, new \stdClass(), new \RuntimeException('private service failure')] as $service) {
            self::assertRejected(
                self::registry(['provider' => $service]),
                RouteDefinitionInvalidException::REASON_PROVIDER_INVALID,
            );
        }
        foreach (['', '1provider', 'a b', "a\n", '/private/path', \str_repeat('a', 257)] as $id) {
            self::assertRejected(
                self::registry(['provider' => self::provider($id, [])]),
                RouteDefinitionInvalidException::REASON_PROVIDER_INVALID,
            );
        }
        foreach (
            [
                ['key' => self::route('a')],
                [1 => self::route('a')],
                [new \stdClass()],
                [RouteGroup::root()],
                [[self::route('a')]],
            ] as $routes
        ) {
            self::assertRejected(
                self::registry(['provider' => self::provider('valid', $routes)]),
                RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID,
            );
        }
        self::assertRejected(
            self::registry(['provider' => self::provider('valid', [self::route('a'), self::route('a')])]),
            RouteDefinitionInvalidException::REASON_ROUTE_NAME_DUPLICATE,
        );
        self::assertRejected(self::registry([
            'a' => self::provider('same', []),
            'b' => self::provider('same', []),
        ]), RouteDefinitionInvalidException::REASON_PROVIDER_IDENTITY_DUPLICATE);
        self::assertRejected(self::registry([
            'provider' => new class() implements RouteProviderInterface {
                public function id(): string
                {
                    return 'valid';
                }

                public function routes(): array
                {
                    throw new \RuntimeException('private output failure');
                }
            },
        ]), RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID);
        self::assertRejected(self::registry([
            'provider' => new class() implements RouteProviderInterface {
                public function id(): string
                {
                    throw new \RuntimeException('private identity failure');
                }

                public function routes(): array
                {
                    return [];
                }
            },
        ]), RouteDefinitionInvalidException::REASON_PROVIDER_INVALID);
    }

    public function testEmptyRegistryAndEmptyProviderOutputAreValid(): void
    {
        self::assertSame([], self::registry([])->routes());
        self::assertSame([], self::registry(['empty' => self::provider('Empty:provider_1', [])])->routes());
    }

    public function testFlattenedRouteLimitIsEnforcedAcrossProvidersDuringAggregation(): void
    {
        $routes = [];
        for ($index = 1; $index < RoutesArtifactSchema::MAX_ROUTES; ++$index) {
            $routes[] = self::route('r' . $index);
        }

        $tags = new TagRegistry();
        $tag = ReservedTags::ROUTING_ROUTE_PROVIDER;
        $tags->add($tag, 'a', 10);
        $tags->add($tag, 'b', 0);
        $tags->add($tag, 'c', -10);
        $last = self::route('last');

        $container = self::container([
            'a' => self::provider('a', $routes),
            'b' => self::provider('b', [$last]),
            'c' => self::provider('c', []),
        ]);
        self::assertSame([...$routes, $last], new RouteProviderRegistry($tags, $container)->routes());
        self::assertSame(['a', 'b', 'c'], $container->calls);

        $container = self::container([
            'a' => self::provider('a', $routes),
            'b' => self::provider('b', [$last, self::route('overflow')]),
            'c' => new \RuntimeException('private provider failure'),
        ]);
        self::assertRejected(
            new RouteProviderRegistry($tags, $container),
            RouteDefinitionInvalidException::REASON_RESOURCE_LIMIT_EXCEEDED,
        );
        self::assertSame(['a', 'b'], $container->calls);
    }

    private static function route(string $name): RouteDefinition
    {
        return new RouteDefinition($name, ['GET'], '/' . $name, 'Handler::show');
    }

    /** @param array<mixed> $routes */
    private static function provider(string $id, array $routes): RouteProviderInterface
    {
        return new class($id, $routes) implements RouteProviderInterface {
            public function __construct(private readonly string $identity, private readonly array $output)
            {
            }

            public function id(): string
            {
                return $this->identity;
            }

            public function routes(): array
            {
                return $this->output;
            }
        };
    }

    /** @param array<string, mixed> $services */
    private static function container(array $services): RoutingRegistryContainerFixture
    {
        return new RoutingRegistryContainerFixture($services);
    }

    /** @param array<string, mixed> $services */
    private static function registry(array $services): RouteProviderRegistry
    {
        $tags = new TagRegistry();
        foreach (\array_keys($services) as $id) {
            $tags->add(ReservedTags::ROUTING_ROUTE_PROVIDER, $id);
        }
        return new RouteProviderRegistry($tags, self::container($services));
    }

    private static function assertRejected(RouteProviderRegistry $registry, string $reason): void
    {
        try {
            $registry->routes();
            self::fail('Expected provider rejection.');
        } catch (RouteDefinitionInvalidException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame(RouteDefinitionInvalidException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}

final class RoutingRegistryContainerFixture implements ContainerInterface
{
    /** @var list<string> */
    public array $calls = [];

    /** @param array<string, mixed> $services */
    public function __construct(private readonly array $services)
    {
    }

    public function get(string $id): mixed
    {
        $this->calls[] = $id;
        $service = $this->services[$id] ?? null;
        if ($service instanceof \Throwable) {
            throw $service;
        }
        return $service;
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
