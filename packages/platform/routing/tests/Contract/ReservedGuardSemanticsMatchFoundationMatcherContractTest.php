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

namespace Coretsia\Platform\Routing\Tests\Contract;

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Routing\ReservedPathMatcher;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactPath;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Compile\RouteTemplate;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use PHPUnit\Framework\TestCase;

final class ReservedGuardSemanticsMatchFoundationMatcherContractTest extends TestCase
{
    public function testConcreteCanonicalDeclarationsAgreeWithFoundationOwnership(): void
    {
        $statuses = [];
        foreach (SystemRouteTable::entries() as $entry) {
            $path = $entry['pattern'] . ($entry['type'] === 'prefix' ? 'details' : '');
            self::assertSame($path, ReservedPathMatcher::normalize($path));
            self::assertSame($entry, ReservedPathMatcher::match($path));
            self::assertGuardAgrees(RouteTemplate::parse($path, []), $path);
            $statuses[$entry['status']] = true;
            // Concrete slash aliases are Foundation-owned, not extra template declarations.
            self::assertSame($entry, ReservedPathMatcher::match('/' . $path));
            self::assertSame($entry, ReservedPathMatcher::match(\ltrim($path, '/')));
            $nearMiss = \rtrim($entry['pattern'], '/') . 'check';
            self::assertNull(ReservedPathMatcher::match($nearMiss));
            self::assertGuardAgrees(RouteTemplate::parse($nearMiss, []), $nearMiss);
        }
        \ksort($statuses, \SORT_STRING);
        self::assertSame(['active', 'dev_only', 'reserved'], \array_keys($statuses));
        foreach (['/', '/healthcheck', '/healthcheck/details', '/metrics/details', '/_debugger'] as $path) {
            self::assertNull(ReservedPathMatcher::match($path));
            self::assertGuardAgrees(RouteTemplate::parse($path, []), $path);
        }
    }

    public function testDynamicWitnessesBelongToBothTemplateDomainAndFoundationReservation(): void
    {
        foreach (SystemRouteTable::entries() as $entry) {
            $path = $entry['pattern'] . ($entry['type'] === 'prefix' ? 'details' : '');
            $segments = \explode('/', \substr($path, 1));
            foreach ($segments as $index => $segment) {
                $parts = $segments;
                $parts[$index] = '{part}';
                $template = RouteTemplate::parse('/' . \implode('/', $parts), []);
                self::assertSame(['part' => $segment], $template->match($path));
                self::assertSame($entry, ReservedPathMatcher::match($path));
                self::assertGuardAgrees($template, $path);
            }
            if ($entry['type'] === 'prefix') {
                foreach (['[0-9]+' => '0', '[A-Za-z]+' => 'a', '[A-Za-z0-9_-]+' => 'a'] as $requirement => $value) {
                    $template = RouteTemplate::parse($entry['pattern'] . '{id}', ['id' => $requirement]);
                    $witness = $entry['pattern'] . $value;
                    self::assertSame($value, $template->representativeSegment($template->segmentCount() - 1));
                    self::assertSame(['id' => $value], $template->match($witness));
                    self::assertSame($entry, ReservedPathMatcher::match($witness));
                    self::assertGuardAgrees($template, $witness);
                }
            }
        }
        $alpha = RouteTemplate::parse('/{name}', ['name' => '[A-Za-z]+']);
        self::assertSame(['name' => 'health'], $alpha->match('/health'));
        self::assertGuardAgrees($alpha, '/health');
        $nested = RouteTemplate::parse('/{name}/{id}', ['name' => '[A-Za-z]+', 'id' => '[0-9]+']);
        self::assertSame(['name' => 'health', 'id' => '0'], $nested->match('/health/0'));
        self::assertGuardAgrees($nested, '/health/0');
        foreach (['/{id}', '/{id}/details'] as $path) {
            $numeric = RouteTemplate::parse($path, ['id' => '[0-9]+']);
            $witness = \str_replace('{id}', '0', $path);
            self::assertSame(['id' => '0'], $numeric->match($witness));
            self::assertNull(ReservedPathMatcher::match($witness));
            self::assertGuardAgrees($numeric, $witness);
        }
    }

    public function testEncodedOctetsCaseAndSegmentBoundariesRemainFoundationOwned(): void
    {
        foreach (
            [
                ['/health/', '/health/', true],
                ['//health//details', '/health/details', true],
                ['/Health', '/Health', false],
                ['/healthcheck', '/healthcheck', false],
                ['/health%2Fdetails', '/health%2Fdetails', false],
                ['/health%2fdetails', '/health%2fdetails', false],
                ['/%68ealth', '/%68ealth', false],
                ['/health/%2e', '/health/%2e', true],
            ] as [$path, $normalized, $reserved]
        ) {
            self::assertSame($normalized, ReservedPathMatcher::normalize($path));
            self::assertSame($reserved, ReservedPathMatcher::match($path) !== null);
        }
        foreach (['/health/..', '/health/.', '/health/%GG', '/health?x', '/health\\x', '*'] as $path) {
            try {
                ReservedPathMatcher::match($path);
                self::fail('Expected invalid concrete path rejection.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('http-reserved-path-invalid', $exception->getMessage());
            }
        }
    }

    public function testRuntimeMatchingUsesFoundationConcretePathAdmissionWithoutAliases(): void
    {
        $routes = [
            new RouteDefinition('root', ['GET'], '/', 'Handler::index'),
            new RouteDefinition('detail', ['GET'], '/application/{part}', 'Handler::show'),
            new RouteDefinition('near', ['GET'], '/healthcheck', 'Handler::near'),
            new RouteDefinition('numeric', ['GET'], '/{id}', 'Handler::numeric', ['id' => '[0-9]+']),
        ];
        $table = self::table($routes);
        $router = new CompiledRouter(
            $table,
            new ContextStore(),
            new RoutingMatchObserver(
                new NoopTracer(),
                new NoopMeter(),
                new NoopLogger(),
                new Stopwatch(),
                false,
                false,
                false,
            ),
        );
        $paths = [
            '/',
            '/0',
            '/healthcheck',
            '/application/ABC',
            '/application/%2F',
            '/application/%2f',
            '/application/%2e',
            '/application/%2E%2E',
            '/application/a.b',
            '/Application/ABC',
            '',
            'application/ABC',
            '//application/ABC',
            '/application//ABC',
            '/application/ABC/',
            '/application/.',
            '/application/..',
            '/application/%GG',
            '/application/a?b',
            '/application/a#b',
            '/application/a\\b',
            '/application/é',
            '*',
            '/health%2Fdetails',
            '/Health',
        ];
        foreach (SystemRouteTable::entries() as $entry) {
            $paths[] = $entry['pattern'] . ($entry['type'] === 'prefix' ? 'details' : '');
        }
        foreach ($paths as $path) {
            $expected = null;
            try {
                if (
                    ReservedPathMatcher::normalize($path) === $path
                    && ($path === '/' || !\str_ends_with($path, '/'))
                    && ReservedPathMatcher::match($path) === null
                ) {
                    $expected = $table->match('GET', $path);
                }
            } catch (\InvalidArgumentException) {
                // The canonical concrete-path matcher rejects the input.
            }
            self::assertSame($expected?->toArray(), $router->match('GET', $path)?->toArray(), $path);
        }
        self::assertSame(['part' => '%2F'], $router->match('GET', '/application/%2F')?->parameters());
        self::assertSame(['part' => '%2f'], $router->match('GET', '/application/%2f')?->parameters());
        self::assertSame('near', $router->match('GET', '/healthcheck')?->name());
    }

    /** @param list<RouteDefinition> $routes */
    private static function table(array $routes): CompiledRouteTable
    {
        $classifier = new SensitiveKeyClassifier();
        $schema = new RoutesArtifactSchema(
            new ReservedRoutesGuard(),
            $classifier,
            new DefaultSensitiveDataRedactor($classifier, new SensitiveValueClassifier(), new StableRedactionHasher()),
            new StablePhpArrayDumper(),
        );
        $root = \sys_get_temp_dir() . '/coretsia-routing-foundation-parity-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($root, 0700));
        try {
            $paths = new RoutesArtifactPath(new RuntimePathContext($root, $root));
            $bytes = $schema->serializeEnvelope($schema->createEnvelope($routes, 'web', 'local'), 'web', 'local');
            self::assertSame(\strlen($bytes), \file_put_contents($paths->artifactPath(), $bytes));

            return new CompiledRouteTable(
                new RoutesArtifactReader(
                    $paths,
                    new StablePhpArrayParser(),
                    $schema,
                    new ModulePlan('web', [], [], [], []),
                    new RuntimeDiagnosticsInput('local', true),
                ),
            );
        } finally {
            if (\is_file($root . '/routes.php')) {
                \unlink($root . '/routes.php');
            }
            \rmdir($root);
        }
    }

    private static function assertGuardAgrees(RouteTemplate $template, string $witness): void
    {
        self::assertNotSame(null, $template->match($witness));
        $reserved = ReservedPathMatcher::match($witness) !== null;
        $rejected = false;
        try {
            new ReservedRoutesGuard()->assertAllowed($template);
        } catch (ReservedRouteViolationException $exception) {
            $rejected = true;
            self::assertSame(ReservedRouteViolationException::REASON_RESERVED_ROUTE_FORBIDDEN, $exception->reason());
        }
        self::assertSame($reserved, $rejected, $template->template());
    }
}
