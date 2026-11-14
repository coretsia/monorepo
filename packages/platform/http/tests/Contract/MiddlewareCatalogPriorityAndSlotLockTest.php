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

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\JsonBodyParserMiddleware;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware;
use Coretsia\Platform\Http\Middleware\RequestContextMiddleware;
use Coretsia\Platform\Http\Middleware\TraceContextMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedHostMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use PHPUnit\Framework\TestCase;

final class MiddlewareCatalogPriorityAndSlotLockTest extends TestCase
{
    private const string PROBLEM_DETAILS = 'Coretsia\\Platform\\ProblemDetails\\Http\\Middleware\\HttpErrorHandlingMiddleware';

    /** @var array<string, array{priority: int, meta: array<string, string>}> */
    private const array BASELINE = [
        TraceContextMiddleware::class => ['priority' => 930, 'meta' => ['toggle' => 'http.tracing.enabled']],
        TrustedProxyMiddleware::class => ['priority' => 880, 'meta' => ['toggle' => 'http.proxy.enabled']],
        RequestContextMiddleware::class => ['priority' => 870, 'meta' => ['toggle' => 'http.context.enrich.enabled']],
        TrustedHostMiddleware::class => ['priority' => 860, 'meta' => ['toggle' => 'http.hosts.enabled']],
        RequestBodySizeLimitMiddleware::class => ['priority' => 830, 'meta' => []],
        JsonBodyParserMiddleware::class => ['priority' => 800, 'meta' => ['toggle' => 'http.request.json.enabled']],
    ];

    public function testPackageContributesOnlySixCurrentMiddlewareAtExactCanonicalPriorities(): void
    {
        $operations = self::definitions();
        $actual = [];
        $httpTags = self::httpTags();
        foreach ($operations as $operation) {
            if (($operation['kind'] ?? null) !== 'tag') {
                continue;
            }
            $tag = $operation['tag'];
            if (!\in_array($tag, $httpTags, true)) {
                continue;
            }
            self::assertSame(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, $tag);
            $id = $operation['serviceId'];
            self::assertArrayNotHasKey($id, $actual);
            $actual[$id] = ['priority' => $operation['priority'], 'meta' => $operation['meta']];
        }

        $expected = self::BASELINE;
        \ksort($expected);
        \ksort($actual);
        self::assertSame($expected, $actual);
        self::assertCount(6, $actual);
        self::assertArrayNotHasKey(self::PROBLEM_DETAILS, $actual);

        $registered = [];
        foreach ($operations as $operation) {
            if (!\in_array($operation['kind'] ?? null, [
                'service.class',
                'service.factory.class-method',
                'service.factory.service-method',
            ], true)) {
                continue;
            }

            $id = $operation['id'] ?? null;
            if (\is_string($id) && \str_starts_with($id, 'Coretsia\\Platform\\Http\\Middleware\\')) {
                $registered[] = $id;
            }
        }

        self::assertEqualsCanonicalizing([
            HttpMiddlewareStackBuilder::class,
            Pipeline::class,
            ...\array_keys(self::BASELINE),
        ], $registered);
    }

    public function testTagRegistryPriorityAndServiceIdentityRemainTheOnlyOrderingAuthority(): void
    {
        $tags = new TagRegistry();
        $tag = ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE;
        $tags->add($tag, 'App\\Zeta', 700);
        $tags->add($tag, 'App\\Alpha', 700);
        foreach (self::BASELINE as $id => $descriptor) {
            $tags->add($tag, $id, $descriptor['priority'], $descriptor['meta']);
        }
        $tags->add($tag, TraceContextMiddleware::class, 999); // First tag registration wins.

        $ordered = \array_map(static fn ($entry): string => $entry->id(), $tags->all($tag));
        self::assertSame([
            TraceContextMiddleware::class,
            TrustedProxyMiddleware::class,
            RequestContextMiddleware::class,
            TrustedHostMiddleware::class,
            RequestBodySizeLimitMiddleware::class,
            JsonBodyParserMiddleware::class,
            'App\\Alpha',
            'App\\Zeta',
        ], $ordered);

        $http = require __DIR__ . '/../../config/http.php';
        $http['proxy']['enabled'] = true;
        $http['hosts']['enabled'] = true;
        self::assertSame($ordered, new HttpMiddlewareStackBuilder($tags)->build($http));
    }

    public function testExternallySelectedProblemDetailsHasExclusiveOutermostPriority(): void
    {
        $tags = new TagRegistry();
        foreach (self::BASELINE as $id => $descriptor) {
            $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, $id, $descriptor['priority'], $descriptor['meta']);
        }
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, self::PROBLEM_DETAILS, 1000, []);
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST, 'App\\PostDecorator', 10);

        $http = require __DIR__ . '/../../config/http.php';
        $http['proxy']['enabled'] = true;
        $http['hosts']['enabled'] = true;
        $effective = new HttpMiddlewareStackBuilder($tags)->build($http);

        self::assertSame(self::PROBLEM_DETAILS, $effective[0]);
        self::assertSame('App\\PostDecorator', $effective[1]);
        self::assertSame(
            [self::PROBLEM_DETAILS, 'App\\PostDecorator', ...\array_keys(self::BASELINE)],
            $effective,
        );
        self::assertSame(self::PROBLEM_DETAILS, $tags->all(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE)[0]->id());
    }

    public function testNonOwnerCannotOccupyTheReservedErrorBoundary(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, 'App\\NotErrorBoundary', 1000, []);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http-middleware-error-boundary-invalid');
        new HttpMiddlewareStackBuilder($tags)->build(require __DIR__ . '/../../config/http.php');
    }

    /** @return list<array<string, mixed>> */
    private static function definitions(): array
    {
        $http = require __DIR__ . '/../../config/http.php';
        $builder = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($builder, new ContainerDefinitionContext(['http' => $http]));

        return $builder->build()->toDescriptorStream();
    }

    /** @return list<string> */
    private static function httpTags(): array
    {
        return [
            ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
            ReservedTags::HTTP_MIDDLEWARE_SYSTEM,
            ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST,
            ReservedTags::HTTP_MIDDLEWARE_APP_PRE,
            ReservedTags::HTTP_MIDDLEWARE_APP,
            ReservedTags::HTTP_MIDDLEWARE_APP_POST,
            ReservedTags::HTTP_MIDDLEWARE_ROUTE_PRE,
            ReservedTags::HTTP_MIDDLEWARE_ROUTE,
            ReservedTags::HTTP_MIDDLEWARE_ROUTE_POST,
        ];
    }
}
