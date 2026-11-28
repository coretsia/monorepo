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
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouteMiddlewareSelector;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteMiddlewareSelectorTest extends TestCase
{
    public function testAdmissionIsAnImmutableConfigSnapshotAndSelectionPreservesRouteOrder(): void
    {
        $ids = [
            'z.middleware',
            'A.middleware',
            'a.middleware',
            'route:custom',
            \str_repeat('m', 256),
            '0e123',
            '0e456',
            'items',
            'action.show',
        ];
        self::assertSame($ids, RouteMiddlewareSelector::validateServiceIds($ids));
        self::assertSame([], RouteMiddlewareSelector::validateServiceIds([]));
        $selector = $this->selector($ids);
        self::assertSame('http_app_middleware', RouteMiddlewareSelector::METADATA_KEY);
        self::assertSame([], $selector->select(self::match(['other' => ['z.middleware']])));
        self::assertSame([], $selector->select(self::match(['HTTP_APP_MIDDLEWARE' => ['z.middleware']])));
        self::assertSame([], $selector->select(self::match(['http_app_middleware' => []])));
        foreach (
            [
                ['a.middleware', 'z.middleware', 'A.middleware'],
                ['route:custom', $ids[4]],
                ['0e456', '0e123'],
                $ids,
            ] as $selected
        ) {
            self::assertSame($selected, $selector->select(self::match(['http_app_middleware' => $selected])));
        }
        $reflection = new \ReflectionClass($selector);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame(
            ['serviceIds'],
            \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            )
        );
        $constructor = $reflection->getConstructor();
        self::assertInstanceOf(\ReflectionMethod::class, $constructor);
        self::assertSame(1, $constructor->getNumberOfParameters());
        self::assertSame(1, $constructor->getNumberOfRequiredParameters());
        self::assertSame(ConfigRepositoryInterface::class, (string) $constructor->getParameters()[0]->getType());
        // These opaque ids do not need to denote existing classes or container services.
        self::assertSame(['z.middleware'], $selector->select(self::match(['http_app_middleware' => ['z.middleware']])));
    }

    #[DataProvider('invalidConfiguredLists')]
    public function testInvalidAdmissionIsRejectedBothStaticallyAndAtConstruction(mixed $value): void
    {
        foreach (
            [
                static fn () => RouteMiddlewareSelector::validateServiceIds($value),
                fn () => $this->selector($value),
            ] as $operation
        ) {
            try {
                $operation();
            } catch (\InvalidArgumentException $exception) {
                self::assertSame(\InvalidArgumentException::class, $exception::class);
                self::assertSame('http-app-route-middleware-invalid', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                continue;
            }

            self::fail('invalid-route-middleware-admission-accepted');
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidConfiguredLists(): iterable
    {
        yield from self::invalidMetadataLists();
        yield 'sparse' => [[1 => 'z.middleware']];
        yield 'float' => [[1.5]];
        yield 'object' => [[new \stdClass()]];
        yield 'control-byte' => [["private\x00value"]];
        yield 'delete-byte' => [["private\x7fvalue"]];
        yield 'invalid-utf8' => [["private\xff"]];
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidMetadataLists(): iterable
    {
        yield 'null' => [null];
        yield 'scalar' => ['private-value'];
        yield 'bool' => [false];
        yield 'map' => [['id' => 'z.middleware']];
        yield 'nested-list' => [[['z.middleware']]];
        yield 'null-entry' => [[null]];
        yield 'integer-entry' => [[42]];
        yield 'boolean-entry' => [[true]];
        yield 'empty-id' => [['']];
        yield 'whitespace' => [['private value']];
        yield 'tab' => [["private\tvalue"]];
        yield 'line-feed' => [["private\nvalue"]];
        yield 'numeric-id' => [['0']];
        yield 'long-id' => [[\str_repeat('m', 257)]];
        yield 'duplicate' => [['z.middleware', 'z.middleware']];
        yield 'router' => [[RouterMiddleware::class]];
        yield 'dispatch' => [[ActionDispatchMiddleware::class]];
        yield 'outer-boundary' => [
            ['Coretsia\\Platform\\ProblemDetails\\Http\\Middleware\\HttpErrorHandlingMiddleware'],
        ];
    }

    #[DataProvider('invalidMetadataLists')]
    public function testPresentInvalidMetadataFailsClosed(mixed $value): void
    {
        $selector = $this->selector(['z.middleware']);
        self::assertRejected($selector, self::match(['http_app_middleware' => $value]));
    }

    public function testUnknownAndCaseChangedIdsAreNotAdmitted(): void
    {
        $selector = $this->selector(['z.middleware', 'A.middleware', '0e123']);
        foreach (
            [
                ['private.unknown'],
                ['z.middleware', 'private.unknown'],
                ['a.middleware'],
                ['0e456'],
            ] as $ids
        ) {
            self::assertRejected($selector, self::match(['http_app_middleware' => $ids]));
        }
        self::assertSame(['A.middleware'], $selector->select(self::match(['http_app_middleware' => ['A.middleware']])));
        self::assertSame([
            RouterMiddleware::class,
            ActionDispatchMiddleware::class,
            'Coretsia\\Platform\\ProblemDetails\\Http\\Middleware\\HttpErrorHandlingMiddleware',
        ], RouteMiddlewareSelector::FORBIDDEN_ROLE_IDS);
    }

    public function testSelectionDoesNotLoadOrInstantiateConfiguredClassNames(): void
    {
        $id = 'Synthetic\\UnloadedRouteMiddleware';
        $loads = 0;
        $loader = static function (string $class) use ($id, &$loads): void {
            if ($class === $id) {
                ++$loads;
            }
        };
        \spl_autoload_register($loader);
        try {
            $selector = $this->selector([$id]);
            self::assertSame([$id], $selector->select(self::match(['http_app_middleware' => [$id]])));
            self::assertSame(0, $loads);
        } finally {
            \spl_autoload_unregister($loader);
        }
    }

    private function selector(mixed $value): RouteMiddlewareSelector
    {
        $config = $this->createMock(ConfigRepositoryInterface::class);
        $config->expects(self::once())->method('get')
            ->with('http_app.route_middleware', self::identicalTo(null))->willReturn($value);
        $config->expects(self::never())->method('all');
        $config->expects(self::never())->method('has');

        return new RouteMiddlewareSelector($config);
    }

    /** @param array<string, mixed> $metadata */
    private static function match(array $metadata): RouteMatch
    {
        return new RouteMatch('items', '/items', 'action.show', metadata: $metadata);
    }

    private static function assertRejected(RouteMiddlewareSelector $selector, RouteMatch $match): void
    {
        try {
            $selector->select($match);
        } catch (HttpAppDispatchException $exception) {
            self::assertSame('http-app-route-middleware-invalid', $exception->reason());
            self::assertSame('http-app-route-middleware-invalid', $exception->getMessage());
            self::assertNull($exception->getPrevious());

            return;
        }

        self::fail('invalid-route-middleware-selection-accepted');
    }
}
