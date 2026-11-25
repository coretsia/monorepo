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

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingDeclarationProvider;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class RoutesArtifactRerunNoDiffTest extends TestCase
{
    public function testRerunFromFreshSourceHostsIsByteIdenticalAndFailedCompilationPreservesBytes(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    public static function exercise(string $root): void
    {
        $source = RoutingIntegrationFixture::source($root, AppTarget::Web);
        $first = $source->get(RoutesCompiler::class)->compile();
        self::assertTrue($source->get(RoutesArtifactWriter::class)->write($first));
        $path = $root . '/var/routing-cache/web/routes.php';
        $bytes = \file_get_contents($path);
        self::assertIsString($bytes);
        $next = RoutingIntegrationFixture::source($root, AppTarget::Web);
        $second = $next->get(RoutesCompiler::class)->compile();
        self::assertSame($first, $second);
        self::assertFalse($next->get(RoutesArtifactWriter::class)->write($second));
        self::assertSame($bytes, \file_get_contents($path));
        RoutingDeclarationProvider::$invalid = true;
        try {
            $failed = RoutingIntegrationFixture::source($root, AppTarget::Web);
            $envelope = $failed->get(RoutesCompiler::class)->compile();
            $failed->get(RoutesArtifactWriter::class)->write($envelope);
            self::fail('Expected reserved declaration rejection.');
        } catch (ReservedRouteViolationException $exception) {
            self::assertSame(ReservedRouteViolationException::REASON_RESERVED_ROUTE_FORBIDDEN, $exception->reason());
        } finally {
            RoutingDeclarationProvider::$invalid = false;
        }
        self::assertSame($bytes, \file_get_contents($path));
        self::assertSame(['.', '..', 'routes.lock', 'routes.php'], \scandir(\dirname($path)));
    }
}
