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
use Coretsia\Platform\Routing\Definition\RouteGroup;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use PHPUnit\Framework\TestCase;

final class RouteGroupTest extends TestCase
{
    public function testRootIdentityAndImmutableNestedComposition(): void
    {
        $root = RouteGroup::root();
        $route = new RouteDefinition(
            'show',
            ['POST', 'GET'],
            '/{item}',
            'Handler::show',
            ['item' => '[A-Za-z0-9_-]+'],
            ['mode' => 'default'],
            ['z' => true],
        );
        self::assertSame($route->toArray(), $root->route($route)->toArray());
        self::assertSame([], $root->routes([]));
        $parent = $root->group('/teams/{team}', 'teams', ['team' => '[0-9]+'], ['b' => ['z' => 2, 'a' => 1]]);
        $before = $parent->route($route)->toArray();
        $child = $parent->group(
            '/members/{member}',
            'members',
            ['member' => '[A-Za-z]+'],
            ['a' => ['second', 'first']],
        );
        $flattened = $child->route($route);
        self::assertSame([
            'defaults' => ['mode' => 'default'],
            'handler' => 'Handler::show',
            'metadata' => ['a' => ['second', 'first'], 'b' => ['a' => 1, 'z' => 2], 'z' => true],
            'methods' => ['GET', 'POST'],
            'name' => 'teams.members.show',
            'pathTemplate' => '/teams/{team}/members/{member}/{item}',
            'requirements' => ['item' => '[A-Za-z0-9_-]+', 'member' => '[A-Za-z]+', 'team' => '[0-9]+'],
            'schemaVersion' => 1,
        ], $flattened->toArray());
        self::assertSame($before, $parent->route($route)->toArray());
        self::assertSame($route->toArray(), $root->route($route)->toArray());
        self::assertSame($flattened->toArray(), $child->group('/', '', [], [])->route($route)->toArray());
    }

    public function testRootChildPathsEmptyNamePrefixesAndListOrder(): void
    {
        $root = RouteGroup::root();
        $group = $root->group('/v1', '', [], [])->group('/', 'api', [], [])->group('/users', '', [], []);
        $index = new RouteDefinition('z.index', ['GET'], '/', 'Handler::index');
        $show = new RouteDefinition('a.show', ['GET'], '/{id}', 'Handler::show');
        $routes = $group->routes([$index, $show]);
        self::assertSame(
            ['api.z.index', 'api.a.show'],
            \array_map(static fn (RouteDefinition $route): string => $route->name(), $routes),
        );
        self::assertSame(
            ['/v1/users', '/v1/users/{id}'],
            \array_map(static fn (RouteDefinition $route): string => $route->pathTemplate(), $routes),
        );
        self::assertSame('/', $root->route($index)->pathTemplate());
        self::assertSame('z.index', $root->group('/v1', '', [], [])->route($index)->name());
    }

    public function testInvalidPrefixesAndFlattenedPathsAreRejected(): void
    {
        $root = RouteGroup::root();
        foreach (['relative', '/a/', '/A', '/a//b', '/{id?}', '/a/{id}/{id}', '/a/..'] as $path) {
            self::assertRejected(
                static fn () => $root->group($path, '', [], []),
                RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
            );
        }
        foreach (['.a', 'a.', 'a..b', 'a/b', 'a b', '1a'] as $name) {
            self::assertRejected(
                static fn () => $root->group('/', $name, [], []),
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
        $long = $root->group('/' . \str_repeat('a', 254), '', [], []);
        self::assertRejected(
            static fn () => $long->group('/b', '', [], []),
            RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
        );
        self::assertRejected(
            static fn () => $long->route(new RouteDefinition('show', ['GET'], '/b', 'Handler::show')),
            RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
        );
        foreach (['/A', '/a/', '/{id?}', '/a//b'] as $path) {
            self::assertRejected(
                static fn () => $root->route(new RouteDefinition('show', ['GET'], $path, 'Handler::show')),
                RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
            );
        }
    }

    public function testInheritedPlaceholdersRequirementsAndMetadataCannotBeOverridden(): void
    {
        $parent = RouteGroup::root()->group('/teams/{id}', 'teams', ['id' => '[0-9]+'], ['scope' => null]);
        self::assertRejected(
            static fn () => $parent->group('/{id}', '', [], []),
            RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
        );
        self::assertRejected(
            static fn () => $parent->group('/{id}', '', ['id' => '[A-Za-z]+'], []),
            RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
        );
        self::assertRejected(
            static fn () => $parent->group('/members', '', ['id' => '[A-Za-z]+'], []),
            RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID,
        );
        self::assertRejected(
            static fn () => $parent->route(
                new RouteDefinition('show', ['GET'], '/{id}', 'Handler::show', ['id' => '[A-Za-z]+']),
            ),
            RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
        );
        self::assertRejected(
            static fn () => $parent->route(
                new RouteDefinition('show', ['GET'], '/members', 'Handler::show', ['id' => '[A-Za-z]+']),
            ),
            RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID,
        );
        self::assertRejected(
            static fn () => $parent->group('/members', '', [], ['scope' => true]),
            RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
        );
        self::assertRejected(
            static fn () => $parent->route(
                new RouteDefinition('show', ['GET'], '/members', 'Handler::show', metadata: ['scope' => true]),
            ),
            RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
        );
        foreach ([[true], ['invalid' => new \stdClass()], ['invalid' => 1.5]] as $metadata) {
            self::assertRejected(
                static fn () => $parent->group('/', '', [], $metadata),
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
    }

    public function testInheritedOrderedRouteMiddlewareMetadataCannotBeOverridden(): void
    {
        $middleware = ['app.middleware.z', 'app.middleware.a'];
        $parent = RouteGroup::root()->group('/teams', 'teams', [], ['http_app_middleware' => $middleware]);
        $child = $parent->group('/members', 'members', [], ['scope' => 'team']);
        $route = new RouteDefinition('show', ['GET'], '/{id}', 'Handler::show', metadata: ['flag' => true]);
        self::assertSame([
            'flag' => true,
            'http_app_middleware' => $middleware,
            'scope' => 'team',
        ], $child->route($route)->metadata());
        self::assertSame(['flag' => true], $route->metadata());
        foreach ([$middleware, \array_reverse($middleware), []] as $duplicate) {
            self::assertRejected(
                static fn () => $child->group('/detail', '', [], ['http_app_middleware' => $duplicate]),
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
            self::assertRejected(
                static fn () => $child->route(
                    new RouteDefinition('show', ['GET'], '/{id}', 'Handler::show', metadata: [
                        'http_app_middleware' => $duplicate,
                    ]),
                ),
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
    }

    public function testOnlyFlatRouteDefinitionListsAreAccepted(): void
    {
        $group = RouteGroup::root();
        $route = new RouteDefinition('index', ['GET'], '/', 'Handler::index');
        foreach ([['key' => $route], [1 => $route], [$group], [[$route]], [null]] as $routes) {
            self::assertRejected(
                static fn () => $group->routes($routes),
                RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID,
            );
        }
    }

    private static function assertRejected(\Closure $operation, string $reason): void
    {
        try {
            $operation();
            self::fail('Expected route group rejection.');
        } catch (RouteDefinitionInvalidException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame(RouteDefinitionInvalidException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
        }
    }
}
