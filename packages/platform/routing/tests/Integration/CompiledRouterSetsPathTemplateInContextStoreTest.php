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

namespace Coretsia\Platform\Routing\Tests\Integration;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Context\ContextWriterInterface;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class CompiledRouterSetsPathTemplateInContextStoreTest extends TestCase
{
    public function testCanonicalSharedContextSurvivesRoutingAndIsResetByKernelUow(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    public static function exercise(string $root): void
    {
        $source = RoutingIntegrationFixture::source($root, AppTarget::Web);
        self::assertTrue(
            $source->get(RoutesArtifactWriter::class)->write($source->get(RoutesCompiler::class)->compile()),
        );
        $store = $source->get(ContextStore::class);
        $router = $source->get(RouterInterface::class);
        self::assertSame($store, $source->get(ContextWriterInterface::class));
        self::assertSame($store, $source->get(ContextAccessorInterface::class));
        self::assertSame($store, new \ReflectionProperty(CompiledRouter::class, 'contextWriter')->getValue($router));
        $runtime = $source->get(KernelRuntimeInterface::class);
        $runtime->runUnitOfWork('http', static function () use ($store, $router): void {
            $store->set(ContextKeys::PATH, '/upstream-owned');
            $store->set(ContextKeys::HOST, 'upstream.example');
            $store->set(ContextKeys::REQUEST_ID, 'existing-request');
            $base = $store->all();
            self::assertSame(['GET'], $router->allowedMethods('/items/42', 'private.example'));
            self::assertSame($base, $store->all());
            foreach (
                [
                    ['GET', '/items/42', true],
                    ['GET', '/missing', false],
                    ['GET', '/items/99', true],
                    ['HEAD', '/items/99', false],
                    ['GET', '/health', false],
                ] as [$method, $path, $matched]
            ) {
                $match = $router->match($method, $path, 'private.example');
                self::assertSame($matched, $match !== null);
                $expected = $matched ? $base + [ContextKeys::PATH_TEMPLATE => '/items/{id}'] : $base;
                self::assertSame($expected, $store->all());
            }
            self::assertSame('z.web', $router->match('GET', '/items/42')?->name());
            self::assertSame('/items/{id}', $store->get(ContextKeys::PATH_TEMPLATE));
            foreach (
                [
                    ['/other', ['POST']],
                    ['/missing', []],
                    ['/health', []],
                    ['//items/42', []],
                ] as [$path, $methods]
            ) {
                self::assertSame($methods, $router->allowedMethods($path));
                self::assertSame($base + [ContextKeys::PATH_TEMPLATE => '/items/{id}'], $store->all());
            }
        });
        self::assertSame([], $store->all());
        self::assertSame($store, $source->get(ContextWriterInterface::class));
        self::assertSame($store, $source->get(ContextAccessorInterface::class));
        $runtime->runUnitOfWork('http', static function () use ($store, $router): void {
            self::assertFalse($store->has(ContextKeys::PATH_TEMPLATE));
            self::assertNull($router->match('GET', '/missing'));
        });
        self::assertSame([], $store->all());
    }

    public function testWriterOnlyPortRecordsExactMutationSequence(): void
    {
        $root = RoutingIntegrationFixture::root();
        try {
            self::assertTrue(
                RoutingIntegrationFixture::writer($root)->write(RoutingIntegrationFixture::envelope('detail')),
            );
            $writer = new class() implements ContextWriterInterface {
                /** @var list<array<mixed>> */
                public array $calls = [];

                public function set(string $key, mixed $value): void
                {
                    $this->calls[] = ['set', $key, $value];
                }

                public function remove(string $key): void
                {
                    $this->calls[] = ['remove', $key];
                }
            };
            $router = new CompiledRouter(
                new CompiledRouteTable(RoutingIntegrationFixture::reader($root)),
                $writer,
                new RoutingMatchObserver(
                    new NoopTracer(),
                    new NoopMeter(),
                    new NoopLogger(),
                    new Stopwatch(),
                    true,
                    true,
                    true,
                ),
            );
            self::assertSame('detail', $router->match('GET', '/items/private-value', 'private.example')?->name());
            self::assertSame(
                [['remove', ContextKeys::PATH_TEMPLATE], ['set', ContextKeys::PATH_TEMPLATE, '/items/{id}']],
                $writer->calls,
            );
            $writer->calls = [];
            foreach (
                [
                    ['/items/private-value', ['GET']],
                    ['/missing', []],
                    ['/health', []],
                    ['//items/42', []],
                ] as [$path, $methods]
            ) {
                self::assertSame($methods, $router->allowedMethods($path, 'private.example'));
                self::assertSame([], $writer->calls);
            }
            foreach (
                [
                    ['GET', '/missing'],
                    ['GET', '/health'],
                    ['GET', '//items/42'],
                    ['OTHER', '/items/42'],
                ] as [$method, $path]
            ) {
                $writer->calls = [];
                self::assertNull($router->match($method, $path));
                self::assertSame([['remove', ContextKeys::PATH_TEMPLATE]], $writer->calls);
            }
        } finally {
            RoutingIntegrationFixture::remove($root);
        }
    }
}
