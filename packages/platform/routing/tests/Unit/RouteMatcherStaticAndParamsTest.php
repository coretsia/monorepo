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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
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
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use PHPUnit\Framework\TestCase;

final class RouteMatcherStaticAndParamsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = \sys_get_temp_dir() . '/coretsia-route-matcher-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        if (\is_file($this->root . '/routes.php')) {
            \unlink($this->root . '/routes.php');
        }
        \rmdir($this->root);
        parent::tearDown();
    }

    public function testStaticRootAndPlaceholderMatchingReturnExactCanonicalFields(): void
    {
        $router = $this->router([
            new RouteDefinition('index', ['GET'], '/', 'Handler::index'),
            new RouteDefinition('static', ['GET'], '/items/fixed', 'Handler::fixed'),
            new RouteDefinition(
                'detail',
                ['GET', 'POST'],
                '/items/{id}/{part}',
                'Handler::show',
                metadata: ['z' => true, 'a' => ['b', 'a']],
            ),
        ], new ContextStore());
        self::assertSame([
            'handler' => 'Handler::index',
            'metadata' => [],
            'name' => 'index',
            'parameters' => [],
            'pathTemplate' => '/',
            'schemaVersion' => 1,
        ], $router->match('GET', '/')?->toArray());
        self::assertSame('static', $router->match('GET', '/items/fixed')?->name());
        self::assertSame([
            'handler' => 'Handler::show',
            'metadata' => ['a' => ['b', 'a'], 'z' => true],
            'name' => 'detail',
            'parameters' => ['id' => '%2F', 'part' => 'Alice%2f'],
            'pathTemplate' => '/items/{id}/{part}',
            'schemaVersion' => 1,
        ], $router->match('POST', '/items/%2F/Alice%2f', 'unrelated.example')?->toArray());
        self::assertSame('detail', $router->match('GET', '/items/ABC/%2e')?->name());
        self::assertNull($router->match('GET', '/Items/ABC/%2e'));
        self::assertNull($router->match('GET', '/items/ABC'));
        self::assertNull($router->match('GET', '/items/ABC/a/b'));

        self::assertSame(['GET'], $router->allowedMethods('/'));
        self::assertSame(['GET'], $router->allowedMethods('/items/fixed'));
        self::assertSame(['GET', 'POST'], $router->allowedMethods('/items/%2F/Alice%2f'));
        self::assertSame(['GET', 'POST'], $router->allowedMethods('/items/%2F/Alice%2f', 'unrelated.example'));
        self::assertSame([], $router->allowedMethods('/Items/ABC/%2e'));
        self::assertSame([], $router->allowedMethods('/items/ABC'));
        self::assertSame([], $router->allowedMethods('/items/ABC/a/b'));
    }

    public function testCompleteSegmentRequirementsAndCanonicalMethodMatching(): void
    {
        $router = $this->router([
            new RouteDefinition('numeric', ['GET'], '/n/{id}', 'Handler::show', ['id' => '[0-9]+']),
            new RouteDefinition('alphabetic', ['GET'], '/a/{id}', 'Handler::show', ['id' => '[A-Za-z]+']),
            new RouteDefinition('word', ['GET'], '/w/{id}', 'Handler::show', ['id' => '[A-Za-z0-9_-]+']),
            new RouteDefinition(
                'all',
                ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'],
                '/all',
                'Handler::all',
            ),
        ], new ContextStore());
        foreach ([['/n/001', '001'], ['/a/Az', 'Az'], ['/w/Az_09-', 'Az_09-']] as [$path, $value]) {
            self::assertSame(['id' => $value], $router->match('GET', $path)?->parameters());
            self::assertSame(['GET'], $router->allowedMethods($path));
        }
        foreach (
            [
                '/n/a1',
                '/n/1a',
                '/n/%31',
                '/n/-1',
                '/a/A1',
                '/a/A-',
                '/a/%41',
                '/w/a.b',
                '/w/%41',
                '/w/a+b',
            ] as $path
        ) {
            self::assertNull($router->match('GET', $path));
            self::assertSame([], $router->allowedMethods($path), $path);
        }
        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'] as $method) {
            self::assertSame('all', $router->match($method, '/all')?->name());
        }
        self::assertSame(
            ['CONNECT', 'DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'],
            $router->allowedMethods('/all'),
        );
        foreach (['get', ' Get', 'GET ', '', 'OTHER', 'BREW', "GET\n"] as $method) {
            self::assertNull($router->match($method, '/all'));
        }
        foreach (['HEAD', 'OPTIONS', 'POST'] as $method) {
            self::assertNull($router->match($method, '/n/1'));
        }
    }

    public function testFirstMatchWinsWithoutStaticOrSpecificityPrecedence(): void
    {
        $generic = new RouteDefinition('z.generic', ['GET'], '/items/{id}', 'Handler::generic');
        $numeric = new RouteDefinition(
            'm.numeric',
            ['GET'],
            '/items/{number}',
            'Handler::numeric',
            ['number' => '[0-9]+'],
        );
        $static = new RouteDefinition('a.static', ['GET'], '/items/fixed', 'Handler::fixed');
        $router = $this->router([$generic, $numeric, $static], new ContextStore());
        self::assertSame('z.generic', $router->match('GET', '/items/fixed')?->name());
        self::assertSame('z.generic', $router->match('GET', '/items/42')?->name());
        $reversed = $this->router([$static, $numeric, $generic], new ContextStore());
        self::assertSame('a.static', $reversed->match('GET', '/items/fixed')?->name());
        self::assertSame('m.numeric', $reversed->match('GET', '/items/42')?->name());
        self::assertSame('z.generic', $reversed->match('GET', '/items/other')?->name());
        self::assertSame('z.generic', $router->match('GET', '/items/fixed')?->name());
    }

    public function testAllowedMethodsDeduplicateAndSortAcrossAllOverlappingDomains(): void
    {
        $generic = new RouteDefinition('z.generic', ['POST', 'GET'], '/items/{id}', 'Handler::generic');
        $numeric = new RouteDefinition(
            'm.numeric',
            ['HEAD', 'GET', 'DELETE'],
            '/items/{number}',
            'Handler::numeric',
            ['number' => '[0-9]+'],
        );
        $static = new RouteDefinition('a.static', ['PUT', 'OPTIONS', 'GET'], '/items/fixed', 'Handler::fixed');
        foreach ([[$generic, $numeric, $static], [$static, $numeric, $generic]] as $routes) {
            $router = $this->router($routes, new ContextStore());
            self::assertSame(['GET', 'OPTIONS', 'POST', 'PUT'], $router->allowedMethods('/items/fixed'));
            self::assertSame(['DELETE', 'GET', 'HEAD', 'POST'], $router->allowedMethods('/items/42'));
            self::assertSame(['GET', 'POST'], $router->allowedMethods('/items/other'));
        }
    }

    public function testInvalidReservedAndUnmatchedInputsNeverBecomeAliases(): void
    {
        $context = new ContextStore();
        $router = $this->router([
            new RouteDefinition('root', ['GET'], '/', 'Handler::root'),
            new RouteDefinition('numeric', ['GET'], '/{id}', 'Handler::show', ['id' => '[0-9]+']),
            new RouteDefinition('param', ['GET'], '/items/{id}', 'Handler::show'),
            new RouteDefinition('near', ['GET'], '/healthcheck', 'Handler::near'),
        ], $context);
        $invalid = [
            '',
            'items/1',
            '//items/1',
            '/items//1',
            '/items/1/',
            '/items/.',
            '/items/..',
            '/items/%',
            '/items/%2',
            '/items/%GG',
            '/items/a?b',
            '/items/a#b',
            '/items/a\\b',
            '/items/é',
            'http://example.test/items/1',
            '*',
            '/unmatched',
        ];
        foreach (SystemRouteTable::entries() as $entry) {
            $invalid[] = $entry['pattern'] . ($entry['type'] === 'prefix' ? 'details' : '');
        }
        foreach ($invalid as $path) {
            self::assertSame('param', $router->match('GET', '/items/123')?->name());
            self::assertSame('/items/{id}', $context->get(ContextKeys::PATH_TEMPLATE));
            self::assertNull($router->match('GET', $path), $path);
            self::assertFalse($context->has(ContextKeys::PATH_TEMPLATE));
            self::assertSame([], $router->allowedMethods($path), $path);
        }
        self::assertSame('near', $router->match('GET', '/healthcheck')?->name());
        self::assertSame(['GET'], $router->allowedMethods('/healthcheck'));
    }

    public function testUnsupportedMatchingDeclarationsAreRejectedBeforeRuntime(): void
    {
        foreach (
            [
                [
                    new RouteDefinition('unsupported', ['OTHER'], '/items', 'Handler::show'),
                    RouteDefinitionInvalidException::REASON_METHOD_INVALID,
                ],
                [
                    new RouteDefinition('unsupported', ['GET'], '/items/{id}', 'Handler::show', ['id' => '.*']),
                    RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID,
                ],
                [
                    new RouteDefinition(
                        'unsupported',
                        ['GET'],
                        '/items/{id}',
                        'Handler::show',
                        defaults: ['id' => '1'],
                    ),
                    RouteDefinitionInvalidException::REASON_DECLARATION_UNSUPPORTED,
                ],
                [
                    new RouteDefinition('unsupported', ['GET'], '/items/*', 'Handler::show'),
                    RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
                ],
            ] as [$route, $reason]
        ) {
            try {
                $this->router([$route], new ContextStore());
                self::fail('Expected unsupported declaration rejection.');
            } catch (RouteDefinitionInvalidException $exception) {
                self::assertSame($reason, $exception->reason());
            }
        }
    }

    /** @param list<RouteDefinition> $routes */
    private function router(array $routes, ContextStore $context): CompiledRouter
    {
        $classifier = new SensitiveKeyClassifier();
        $schema = new RoutesArtifactSchema(
            new ReservedRoutesGuard(),
            $classifier,
            new DefaultSensitiveDataRedactor($classifier, new SensitiveValueClassifier(), new StableRedactionHasher()),
            new StablePhpArrayDumper(),
        );
        $envelope = $schema->createEnvelope($routes, 'web', 'local');
        $bytes = $schema->serializeEnvelope($envelope, 'web', 'local');
        $paths = new RoutesArtifactPath(new RuntimePathContext($this->root, $this->root));
        self::assertSame(\strlen($bytes), \file_put_contents($paths->artifactPath(), $bytes));
        $table = new CompiledRouteTable(
            new RoutesArtifactReader(
                $paths,
                new StablePhpArrayParser(),
                $schema,
                new ModulePlan('web', [], [], [], []),
                new RuntimeDiagnosticsInput('local', false),
            ),
        );
        return new CompiledRouter(
            $table,
            $context,
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
    }
}
