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
use Coretsia\Foundation\Routing\ReservedPathMatcher;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Compile\RouteTemplate;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use PHPUnit\Framework\TestCase;

final class ReservedRoutesGuardRejectsReservedPathsTest extends TestCase
{
    public function testEveryCanonicalDeclarationAndStatusRejectsStaticAndDynamicIntersections(): void
    {
        $statuses = [];
        $types = [];
        foreach (SystemRouteTable::entries() as $entry) {
            $statuses[$entry['status']] = true;
            $types[$entry['type']] = true;
            $path = $entry['pattern'] . ($entry['type'] === 'prefix' ? 'details' : '');
            self::assertSame($entry, ReservedPathMatcher::match($path));
            self::assertForbidden(RouteTemplate::parse($path, []));
            $segments = \explode('/', \substr($path, 1));
            foreach ($segments as $index => $_) {
                $dynamic = $segments;
                $dynamic[$index] = '{part}';
                self::assertForbidden(RouteTemplate::parse('/' . \implode('/', $dynamic), []));
            }
            if ($entry['type'] === 'prefix') {
                self::assertForbidden(RouteTemplate::parse($entry['pattern'] . '{id}', ['id' => '[0-9]+']));
                self::assertForbidden(
                    RouteTemplate::parse($entry['pattern'] . '{name}/details', ['name' => '[A-Za-z]+']),
                );
            }
        }
        \ksort($statuses, \SORT_STRING);
        \ksort($types, \SORT_STRING);
        self::assertSame(['active', 'dev_only', 'reserved'], \array_keys($statuses));
        self::assertSame(['exact', 'prefix'], \array_keys($types));
    }

    public function testDomainIntersectionsAndSegmentBoundaryNearMisses(): void
    {
        foreach (
            [
                ['/{name}', []],
                ['/{name}/details', []],
                ['/{name}', ['name' => '[A-Za-z]+']],
                ['/{name}/details', ['name' => '[A-Za-z]+']],
                ['/health/{id}', ['id' => '[0-9]+']],
                ['/{name}/{id}', ['name' => '[A-Za-z0-9_-]+', 'id' => '[0-9]+']],
            ] as [$path, $requirements]
        ) {
            self::assertForbidden(RouteTemplate::parse($path, $requirements));
        }
        $guard = new ReservedRoutesGuard();
        foreach (
            [
                ['/', []],
                ['/{id}', ['id' => '[0-9]+']],
                ['/{id}/details', ['id' => '[0-9]+']],
                ['/healthcheck', []],
                ['/healthcheck/details', []],
                ['/_debugger', []],
                ['/_alive/details', []],
                ['/metrics/details', []],
                ['/error/details', []],
                ['/ping/details', []],
                ['/application/{name}', []],
            ] as [$path, $requirements]
        ) {
            $template = RouteTemplate::parse($path, $requirements);
            $guard->assertAllowed($template);
            self::assertSame($path, $template->template());
        }
    }

    public function testReservationOwnershipIsIndependentOfMethodAndEnvironment(): void
    {
        $classifier = new SensitiveKeyClassifier();
        $schema = new RoutesArtifactSchema(
            new ReservedRoutesGuard(),
            $classifier,
            new DefaultSensitiveDataRedactor($classifier, new SensitiveValueClassifier(), new StableRedactionHasher()),
            new StablePhpArrayDumper(),
        );
        foreach (SystemRouteTable::entries() as $entry) {
            $path = $entry['pattern'] . ($entry['type'] === 'prefix' ? 'details' : '');
            foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'] as $method) {
                foreach (['local', 'prod'] as $environment) {
                    try {
                        $schema->createEnvelope(
                            [new RouteDefinition('reserved', [$method], $path, 'Handler::show')],
                            'web',
                            $environment,
                        );
                        self::fail('Expected method-independent reserved route rejection.');
                    } catch (ReservedRouteViolationException $exception) {
                        self::assertSame(
                            ReservedRouteViolationException::REASON_RESERVED_ROUTE_FORBIDDEN,
                            $exception->reason(),
                        );
                    }
                }
            }
        }
    }

    private static function assertForbidden(RouteTemplate $template): void
    {
        try {
            new ReservedRoutesGuard()->assertAllowed($template);
            self::fail('Expected reserved route rejection: ' . $template->template());
        } catch (ReservedRouteViolationException $exception) {
            self::assertSame(ReservedRouteViolationException::REASON_RESERVED_ROUTE_FORBIDDEN, $exception->reason());
            self::assertSame(
                ReservedRouteViolationException::ERROR_CODE . ': ' . $exception->reason(),
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
        }
    }
}
