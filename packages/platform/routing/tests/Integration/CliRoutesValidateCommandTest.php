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
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Console\RoutesValidateCommand;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingDeclarationProvider;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingFilesystemProbe;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class CliRoutesValidateCommandTest extends TestCase
{
    public function testCanonicalCliValidationNeverPublishesAndSharesCompileAdmission(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    public static function exercise(string $root): void
    {
        RoutingIntegrationFixture::enableCli($root);
        $console = RoutingIntegrationFixture::source($root, AppTarget::Console);
        $name = 'routes:validate';
        RoutingIntegrationFixture::assertCommandMetadata(
            $console,
            RoutesValidateCommand::class,
            $name,
            'Validate target-local routes without publication.',
        );
        $cache = $root . '/var/routing-cache';
        foreach (['web' => 3, 'api' => 1] as $app => $count) {
            RoutingIntegrationFixture::assertCli($console, $name, ['--app=' . $app], 0, [
                'type' => 'json',
                'payload' => ['app' => $app, 'valid' => true, 'route_count' => $count],
            ]);
            self::assertFalse(\file_exists($cache . '/' . $app));
        }
        RoutingDeclarationProvider::$invalid = true;
        try {
            foreach ([$name, 'routes:compile'] as $command) {
                RoutingIntegrationFixture::assertCli(
                    $console,
                    $command,
                    ['--app=web'],
                    1,
                    RoutingIntegrationFixture::error(
                        ReservedRouteViolationException::ERROR_CODE,
                        ReservedRouteViolationException::REASON_RESERVED_ROUTE_FORBIDDEN,
                    ),
                );
                self::assertFalse(\file_exists($cache . '/web'));
            }
        } finally {
            RoutingDeclarationProvider::$invalid = false;
        }
        $routes = self::oversizedRoutes();
        $oversizedFactories = [
            'web.high' => static fn (): array => $routes,
            'web.low' => static fn (): array => [],
        ];
        RoutingDeclarationProvider::$routeFactories = $oversizedFactories;
        try {
            foreach ([$name, 'routes:compile'] as $command) {
                RoutingIntegrationFixture::assertCli(
                    $console,
                    $command,
                    ['--app=web'],
                    1,
                    RoutingIntegrationFixture::error(
                        RoutesArtifactInvalidException::ERROR_CODE,
                        RoutesArtifactInvalidException::REASON_OVERSIZED,
                    ),
                );
                self::assertFalse(\file_exists($cache . '/web'));
            }
        } finally {
            RoutingDeclarationProvider::$routeFactories = [];
        }
        $artifactRoot = $cache . '/web';
        self::assertTrue(
            RoutingIntegrationFixture::writer($artifactRoot)->write(RoutingIntegrationFixture::envelope('previous')),
        );
        $path = $artifactRoot . '/routes.php';
        $bytes = \file_get_contents($path);
        self::assertIsString($bytes);
        self::assertTrue(\touch($path, 1234567890));
        self::assertTrue(\unlink($artifactRoot . '/routes.lock'));
        foreach (['valid', 'reserved', 'oversized'] as $scenario) {
            RoutingDeclarationProvider::$invalid = $scenario === 'reserved';
            RoutingDeclarationProvider::$routeFactories = $scenario === 'oversized' ? $oversizedFactories : [];
            $record = match ($scenario) {
                'valid' => ['type' => 'json', 'payload' => ['app' => 'web', 'valid' => true, 'route_count' => 3]],
                'reserved' => RoutingIntegrationFixture::error(
                    ReservedRouteViolationException::ERROR_CODE,
                    ReservedRouteViolationException::REASON_RESERVED_ROUTE_FORBIDDEN,
                ),
                'oversized' => RoutingIntegrationFixture::error(
                    RoutesArtifactInvalidException::ERROR_CODE,
                    RoutesArtifactInvalidException::REASON_OVERSIZED,
                ),
            };
            try {
                RoutingFilesystemProbe::run(
                    $artifactRoot,
                    'none',
                    static fn () => RoutingIntegrationFixture::assertCli(
                        $console,
                        $name,
                        ['--app=web'],
                        $scenario === 'valid' ? 0 : 1,
                        $record,
                    ),
                );
                self::assertSame(0, RoutingFilesystemProbe::$artifactOpens);
                self::assertSame(0, RoutingFilesystemProbe::$lockOpens);
                self::assertSame(0, RoutingFilesystemProbe::$renames);
            } finally {
                RoutingDeclarationProvider::$invalid = false;
                RoutingDeclarationProvider::$routeFactories = [];
            }
            \clearstatcache(true, $path);
            self::assertSame($bytes, \file_get_contents($path));
            self::assertSame(1234567890, \filemtime($path));
            self::assertSame(['.', '..', 'routes.php'], \scandir($artifactRoot));
        }
        RoutingDeclarationProvider::$routeFactories = [];
        RoutingIntegrationFixture::assertCliTargetFailures($root, $console, $name);
        self::assertSame($bytes, \file_get_contents($path));
        self::assertFalse(\file_exists($cache . '/api'));
    }

    /** @return list<RouteDefinition> */
    private static function oversizedRoutes(): array
    {
        // Structural budgets remain valid, but quote escaping and indentation exceed the byte limit.
        $routes = [];
        for ($index = 0; $index < 16; ++$index) {
            $routes[] = new RouteDefinition(
                'large.' . $index,
                ['GET'],
                '/large/entry' . $index,
                'Handler::show',
                metadata: [
                    'padding' => \array_fill(0, 128, null),
                    'values' => \array_fill(0, 8, \str_repeat('"', 65_500)),
                ],
            );
        }
        $payload = [
            'app' => 'web',
            'routes' => \array_map(static fn (RouteDefinition $route): array => $route->toArray(), $routes),
        ];
        $envelope = [
            '_meta' => [
                'fingerprint' => RoutingIntegrationFixture::fingerprint($payload, 'local'),
                'generator' => 'platform/routing/artifacts',
                'name' => 'routes',
                'schemaVersion' => 1,
            ],
            'payload' => $payload,
        ];
        self::assertSame($envelope, JsonLikeNormalizer::normalize($envelope, limits: RoutesArtifactSchema::limits()));
        self::assertGreaterThan(
            RoutesArtifactSchema::MAX_ARTIFACT_BYTES,
            \strlen(new StablePhpArrayDumper()->dumpNormalized($envelope)),
        );
        return $routes;
    }
}
