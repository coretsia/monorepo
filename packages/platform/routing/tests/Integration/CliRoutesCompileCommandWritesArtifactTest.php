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

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Console\RoutesCompileCommand;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use Coretsia\Platform\Routing\Exception\RoutesArtifactWriteFailedException;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingDeclarationProvider;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class CliRoutesCompileCommandWritesArtifactTest extends TestCase
{
    public function testCanonicalCliCatalogAndRunnerPublishTargetLocalArtifacts(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    public static function exercise(string $root): void
    {
        RoutingIntegrationFixture::enableCli($root);
        $console = RoutingIntegrationFixture::source($root, AppTarget::Console);
        $name = 'routes:compile';
        RoutingIntegrationFixture::assertCommandMetadata(
            $console,
            RoutesCompileCommand::class,
            $name,
            'Compile and publish a target-local routes artifact.',
        );
        foreach (['web' => ['z.web', 'a.web', 'm.web'], 'api' => ['api.index']] as $app => $names) {
            RoutingDeclarationProvider::$configurations = [];
            $before = RoutingDeclarationProvider::$invoked;
            RoutingIntegrationFixture::assertCli($console, $name, ['--app=' . $app], 0, [
                'type' => 'json',
                'payload' => ['app' => $app, 'artifact' => 'routes.php', 'route_count' => \count($names)],
            ]);
            self::assertSame($before + ($app === 'web' ? 2 : 1), RoutingDeclarationProvider::$invoked);
            self::assertSame([
                [
                    'logging' => ['enabled' => $app !== 'web'],
                    'metrics' => ['enabled' => $app !== 'api'],
                    'tracing' => ['enabled' => true],
                ],
            ], RoutingDeclarationProvider::$configurations);
            $target = RoutingIntegrationFixture::source($root, AppTarget::from($app));
            self::assertSame(
                $names,
                \array_map(
                    static fn (RouteDefinition $route): string => $route->name(),
                    $target->get(RoutesArtifactReader::class)->read(),
                ),
            );
            self::assertFileExists($root . '/var/routing-cache/' . $app . '/routes.php');
        }
        self::assertFileDoesNotExist($root . '/var/routing-cache/console/routes.php');
        $path = $root . '/var/routing-cache/web/routes.php';
        $bytes = \file_get_contents($path);
        RoutingDeclarationProvider::$invalid = true;
        try {
            RoutingIntegrationFixture::assertCli(
                $console,
                $name,
                ['--app=web'],
                1,
                RoutingIntegrationFixture::error(
                    ReservedRouteViolationException::ERROR_CODE,
                    ReservedRouteViolationException::REASON_RESERVED_ROUTE_FORBIDDEN,
                ),
            );
        } finally {
            RoutingDeclarationProvider::$invalid = false;
        }
        self::assertSame($bytes, \file_get_contents($path));
        $lock = $root . '/var/routing-cache/web/routes.lock';
        self::assertTrue(\unlink($lock));
        self::assertTrue(\mkdir($lock));
        try {
            RoutingIntegrationFixture::assertCli(
                $console,
                $name,
                ['--app=web'],
                1,
                RoutingIntegrationFixture::error(
                    RoutesArtifactWriteFailedException::ERROR_CODE,
                    RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
                ),
            );
            self::assertSame($bytes, \file_get_contents($path));
        } finally {
            self::assertTrue(\rmdir($lock));
        }
        RoutingIntegrationFixture::assertCliTargetFailures($root, $console, $name);
        self::assertSame($bytes, \file_get_contents($path));
    }
}
