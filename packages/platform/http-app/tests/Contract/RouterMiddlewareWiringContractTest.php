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

namespace Coretsia\Platform\HttpApp\Tests\Contract;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\HttpApp\Error\HttpAppProblemMapper;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterMiddlewareWiringContractTest extends TestCase
{
    public function testProviderContributesExactlyTheCanonicalMatchingDispatchAndMapperRoles(): void
    {
        $config = require \dirname(__DIR__, 2) . '/config/http_app.php';
        $config['route_middleware'] = ['route.z', 'route.a'];
        $definitions = new ContainerDefinitionBuilder();
        new HttpAppServiceProvider()->define($definitions, new ContainerDefinitionContext(['http_app' => $config]));
        $set = $definitions->build();
        $operations = $set->toDescriptorStream();
        $tags = \array_values(\array_filter($operations, static fn (array $op): bool => $op['kind'] === 'tag'));
        self::assertSame('http.middleware.app', ReservedTags::HTTP_MIDDLEWARE_APP);
        self::assertSame('http.middleware.route', ReservedTags::HTTP_MIDDLEWARE_ROUTE);
        self::assertSame('error.mapper', ReservedTags::ERROR_MAPPER);
        self::assertSame([
            [
                'kind' => 'tag',
                'meta' => [],
                'priority' => 100,
                'serviceId' => RouterMiddleware::class,
                'tag' => ReservedTags::HTTP_MIDDLEWARE_APP,
            ],
            [
                'kind' => 'tag',
                'meta' => [],
                'priority' => -1000,
                'serviceId' => ActionDispatchMiddleware::class,
                'tag' => ReservedTags::HTTP_MIDDLEWARE_ROUTE,
            ],
            [
                'kind' => 'tag',
                'meta' => [],
                'priority' => 800,
                'serviceId' => HttpAppProblemMapper::class,
                'tag' => ReservedTags::ERROR_MAPPER,
            ],
        ], $tags);
        foreach ($operations as $operation) {
            if ($operation['kind'] === 'service.class') {
                self::assertNotSame(Pipeline::class, $operation['id']);
                self::assertNotSame(RequestHandlerInterface::class, $operation['id']);
            }
        }
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = new Pipeline([], $terminal, false, [], false);
        $builder = new ContainerBuilder(['http_app' => $config]);
        $builder->instance(Pipeline::class, $pipeline);
        $builder->instance(RequestHandlerInterface::class, $terminal);
        $builder->register(new HttpAppServiceProvider());
        $container = $builder->build();
        self::assertSame($pipeline, $container->get(Pipeline::class));
        self::assertSame($terminal, $container->get(RequestHandlerInterface::class));
        foreach ($tags as $tag) {
            $entries = $builder->tagRegistry()->all($tag['tag']);
            self::assertCount(1, $entries);
            self::assertSame($tag['serviceId'], $entries[0]->id());
            self::assertSame($tag['priority'], $entries[0]->priority());
            self::assertSame([], $entries[0]->meta());
        }
    }

    public function testProductionCodeDoesNotMirrorOrUseReservedTagStringLiterals(): void
    {
        $reserved = ReservedTags::all();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 2) . '/src'));
        $checked = 0;
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$checked;
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            foreach (\token_get_all($source) as $token) {
                if (\is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    self::assertFalse(\in_array(\substr($token[1], 1, -1), $reserved, true));
                }
            }
        }
        self::assertGreaterThan(0, $checked);
    }
}
