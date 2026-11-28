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
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Definition\RouteGroup;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingDeclarationProvider;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class GroupedRoutesCompilationAndMatchingTest extends TestCase
{
    public function testActualGroupedSourceCompilationAndArtifactOnlyMatching(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    public static function exercise(string $root): void
    {
        $middleware = ['app.middleware.z', 'app.middleware.a'];
        $group = RouteGroup::root()->group(
            '/teams/{team}',
            'teams',
            ['team' => '[0-9]+'],
            ['scope' => 'team', 'http_app_middleware' => $middleware],
        );
        $standalone = [
            new RouteDefinition(
                'teams.members.z',
                ['GET'],
                '/teams/{team}/members/{member}/{id}',
                'Handler::show',
                ['team' => '[0-9]+', 'member' => '[A-Za-z]+'],
                metadata: [
                    'flag' => true,
                    'scope' => 'team',
                    'mode' => 'read',
                    'http_app_middleware' => $middleware,
                ],
            ),
            new RouteDefinition(
                'teams.a',
                ['GET'],
                '/teams/{team}/members/alice/fixed',
                'Handler::fixed',
                ['team' => '[0-9]+'],
                metadata: ['scope' => 'team', 'http_app_middleware' => $middleware],
            ),
        ];
        $low = new RouteDefinition('standalone', ['GET'], '/teams/{team}/members/alice/fixed', 'Handler::other');
        // Different requirement language makes this overlapping domain valid, not equivalent.
        RoutingDeclarationProvider::$routeFactories = [
            'web.high' => static fn (): array => [
                $group->group('/members/{member}', 'members', ['member' => '[A-Za-z]+'], ['flag' => true])
                    ->route(new RouteDefinition('z', ['GET'], '/{id}', 'Handler::show', metadata: ['mode' => 'read'])),
                $group->route(new RouteDefinition('a', ['GET'], '/members/alice/fixed', 'Handler::fixed')),
            ],
            'web.low' => static fn (): array => [$low],
        ];
        $source = RoutingIntegrationFixture::source($root, AppTarget::Web);
        $compiler = $source->get(RoutesCompiler::class);
        $writer = $source->get(RoutesArtifactWriter::class);
        $envelope = $compiler->compile();
        self::assertSame(
            \array_map(static fn (RouteDefinition $route): array => $route->toArray(), [...$standalone, $low]),
            $envelope['payload']['routes'],
        );
        self::assertTrue($writer->write($envelope));
        $artifactRoot = $root . '/var/routing-cache/web';
        $bytes = \file_get_contents($artifactRoot . '/routes.php');
        self::assertIsString($bytes);
        $grouped = RoutingDeclarationProvider::$routeFactories;
        RoutingDeclarationProvider::$routeFactories['web.high'] = static fn (): array => $standalone;
        $equivalent = RoutingIntegrationFixture::source($root, AppTarget::Web)->get(RoutesCompiler::class)->compile();
        self::assertSame($envelope, $equivalent);
        self::assertFalse($writer->write($equivalent));
        self::assertSame($bytes, \file_get_contents($artifactRoot . '/routes.php'));
        RoutingDeclarationProvider::$routeFactories = $grouped;
        foreach (
            [
                [
                    static fn (): array => [
                        RouteGroup::root()->group('/health', 'bad', [], [])
                            ->route(new RouteDefinition('item', ['GET'], '/{id}', 'Handler::show')),
                    ],
                    ReservedRouteViolationException::class,
                    ReservedRouteViolationException::REASON_RESERVED_ROUTE_FORBIDDEN,
                ],
                [
                    static fn (): array => [
                        $group->route(new RouteDefinition('members.z', ['GET'], '/different', 'Handler::show')),
                    ],
                    RouteDefinitionInvalidException::class,
                    RouteDefinitionInvalidException::REASON_ROUTE_NAME_DUPLICATE,
                ],
                [
                    static fn (): array => [
                        RouteGroup::root()->group('/teams/{other}', 'duplicate', ['other' => '[0-9]+'], [])
                            ->route(
                                new RouteDefinition(
                                    'domain',
                                    ['GET'],
                                    '/members/{person}/{value}',
                                    'Handler::show',
                                    ['person' => '[A-Za-z]+'],
                                ),
                            ),
                    ],
                    RouteDefinitionInvalidException::class,
                    RouteDefinitionInvalidException::REASON_MATCHING_DOMAIN_EQUIVALENT,
                ],
            ] as [$factory, $type, $reason]
        ) {
            RoutingDeclarationProvider::$routeFactories['web.low'] = $factory;
            try {
                RoutingIntegrationFixture::source($root, AppTarget::Web)->get(RoutesCompiler::class)->compile();
                self::fail('Expected invalid flattened declarations to be rejected.');
            } catch (ReservedRouteViolationException|RouteDefinitionInvalidException $exception) {
                self::assertSame($type, $exception::class);
                self::assertSame($reason, $exception->reason());
            }
            self::assertSame($bytes, \file_get_contents($artifactRoot . '/routes.php'));
        }
        RoutingDeclarationProvider::$routeFactories = $grouped;
        self::assertSame(
            'success',
            $source->get(KernelOpsInterface::class)->compileConfig(new KernelOpsRequest('web'))->outcome(),
        );
        $generation = RoutingIntegrationFixture::generationBytes($artifactRoot);
        RoutingIntegrationFixture::write(
            $root . '/config/app.php',
            '<?php throw new \LogicException("unexpected source load");',
        );
        RoutingIntegrationFixture::runArtifactRuntimeScenario($root, self::class);
        self::assertSame($bytes, \file_get_contents($artifactRoot . '/routes.php'));
        self::assertSame($generation, RoutingIntegrationFixture::generationBytes($artifactRoot));
    }

    public static function exerciseRuntime(string $root): void
    {
        self::assertFalse(\class_exists(RouteGroup::class, false));
        self::assertTrue(\class_exists(RoutingDeclarationProvider::class));
        RoutingDeclarationProvider::$forbidSource = true;
        $artifactRoot = $root . '/var/routing-cache/web';
        $runtime = new ArtifactRuntimeBooter()->boot(new ArtifactRuntimeInput($root, $artifactRoot));
        $router = $runtime->get(RouterInterface::class);
        $context = $runtime->get(ContextAccessorInterface::class);
        $match = $router->match('GET', '/teams/42/members/alice/fixed');
        self::assertSame([
            'handler' => 'Handler::show',
            'metadata' => [
                'flag' => true,
                'http_app_middleware' => ['app.middleware.z', 'app.middleware.a'],
                'mode' => 'read',
                'scope' => 'team',
            ],
            'name' => 'teams.members.z',
            'parameters' => ['id' => 'fixed', 'member' => 'alice', 'team' => '42'],
            'pathTemplate' => '/teams/{team}/members/{member}/{id}',
            'schemaVersion' => 1,
        ], $match?->toArray());
        self::assertSame('/teams/{team}/members/{member}/{id}', $context->get(ContextKeys::PATH_TEMPLATE));
        self::assertNull($router->match('GET', '/teams/42/members/a1/value'));
        self::assertNull($router->match('GET', '/teams/no/members/alice/value'));
        self::assertFalse($context->has(ContextKeys::PATH_TEMPLATE));
        self::assertSame('standalone', $router->match('GET', '/teams/no/members/alice/fixed')?->name());
        self::assertSame('/teams/{team}/members/alice/fixed', $context->get(ContextKeys::PATH_TEMPLATE));
        self::assertFalse(\class_exists(RouteGroup::class, false));
        self::assertSame(0, RoutingDeclarationProvider::$definitions);
        self::assertSame(0, RoutingDeclarationProvider::$constructed);
        self::assertSame(0, RoutingDeclarationProvider::$identityReads);
        self::assertSame(0, RoutingDeclarationProvider::$invoked);
    }
}
