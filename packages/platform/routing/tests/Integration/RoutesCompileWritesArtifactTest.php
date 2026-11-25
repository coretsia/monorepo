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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class RoutesCompileWritesArtifactTest extends TestCase
{
    public function testExplicitTargetLocalSourceCompilationPublishesOnlyAtTheAuthoritativeRoot(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    public static function exercise(string $root): void
    {
        foreach ([AppTarget::Web, AppTarget::Api] as $target) {
            $source = RoutingIntegrationFixture::source($root, $target);
            $paths = $source->get(RuntimePathContext::class);
            $plan = $source->get(ModulePlan::class);
            $diagnostics = $source->get(RuntimeDiagnosticsInput::class);
            $config = $source->get(ConfigRepositoryInterface::class);
            self::assertSame($target->value, $plan->app());
            self::assertTrue($plan->hasEnabledModule('platform.routing-fixture-' . $target->value));
            self::assertFalse(
                $plan->hasEnabledModule('platform.routing-fixture-' . ($target === AppTarget::Web ? 'api' : 'web')),
            );
            self::assertSame($target !== AppTarget::Web, $config->get('routing.logging.enabled'));
            self::assertSame($target !== AppTarget::Api, $config->get('routing.metrics.enabled'));
            self::assertSame('local', $diagnostics->appEnv());
            self::assertFalse($diagnostics->artifactGenerationLoaded());
            $artifactRoot = $root . '/var/routing-cache/' . $target->value;
            self::assertSame($artifactRoot, $paths->artifactRoot());
            $envelope = $source->get(RoutesCompiler::class)->compile();
            self::assertFalse(\file_exists($artifactRoot));
            self::assertSame(['_meta', 'payload'], \array_keys($envelope));
            self::assertSame(['app', 'routes'], \array_keys($envelope['payload']));
            $expectedRoutes = $target === AppTarget::Web ? [
                new RouteDefinition('z.web', ['GET'], '/items/{id}', 'Handler::show'),
                new RouteDefinition('a.web', ['GET'], '/items/fixed', 'Handler::fixed'),
                new RouteDefinition('m.web', ['POST'], '/other', 'Handler::other'),
            ] : [new RouteDefinition('api.index', ['GET'], '/api-items', 'Handler::index')];
            self::assertSame([
                'app' => $target->value,
                'routes' => \array_map(static fn (RouteDefinition $route): array => $route->toArray(), $expectedRoutes),
            ], $envelope['payload']);
            self::assertSame([
                'fingerprint' => RoutingIntegrationFixture::fingerprint($envelope['payload'], 'local'),
                'generator' => 'platform/routing/artifacts',
                'name' => 'routes',
                'schemaVersion' => 1,
            ], $envelope['_meta']);
            self::assertTrue($source->get(RoutesArtifactWriter::class)->write($envelope));
            $bytes = \file_get_contents($artifactRoot . '/routes.php');
            self::assertIsString($bytes);
            self::assertSame(
                $envelope,
                new StablePhpArrayParser()->parseBounded($bytes, RoutesArtifactSchema::limits()),
            );
            self::assertSame(
                $envelope['payload']['routes'],
                \array_map(
                    static fn (RouteDefinition $route): array => $route->toArray(),
                    $source->get(RoutesArtifactReader::class)->read(),
                ),
            );
            self::assertFileExists($artifactRoot . '/routes.lock');
            self::assertFileDoesNotExist($artifactRoot . '/current');
        }
        self::assertFileDoesNotExist($root . '/var/cache/web/routes.php');
        self::assertFileDoesNotExist($root . '/var/routing-cache/console/routes.php');
    }
}
