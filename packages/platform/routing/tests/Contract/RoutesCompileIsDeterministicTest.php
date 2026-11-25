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

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteProviderInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Compile\RouteProviderRegistry;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Definition\RouteGroup;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use PHPUnit\Framework\TestCase;

final class RoutesCompileIsDeterministicTest extends TestCase
{
    public function testCanonicalTagAndProviderOrderProducesExactStableFingerprint(): void
    {
        $z = new RouteDefinition('z', ['GET'], '/items/{id}', 'Handler::show');
        $a = new RouteDefinition('a', ['GET'], '/items/fixed', 'Handler::fixed');
        $m = new RouteDefinition('m', ['GET'], '/other', 'Handler::other');
        $b = new RouteDefinition('b', ['GET'], '/last', 'Handler::last');
        $providers = [
            ['aa', 0, 'first.identity', [$b]],
            ['b', 10, 'second.identity', [$m]],
            ['a', 10, 'third.identity', [$z, $a]],
        ];
        $payload = ['app' => 'web', 'routes' => self::descriptors([$z, $a, $m, $b])];
        $schema = self::schema();
        $plan = self::plan('web');
        $diagnostics = new RuntimeDiagnosticsInput('local', false);
        $compiler = self::compiler($schema, $providers, $plan, $diagnostics);
        $envelope = $compiler->compile();
        self::assertSame($payload, $envelope['payload']);
        self::assertSame(self::fingerprint($payload, 'local'), $envelope['_meta']['fingerprint']);
        self::assertSame($envelope, $compiler->compile());
        self::assertSame(
            $envelope,
            self::compiler($schema, \array_reverse($providers), $plan, $diagnostics)->compile(),
        );
    }

    public function testSemanticChangesAffectArtifactIdentity(): void
    {
        $route = new RouteDefinition('detail', ['GET'], '/items/{id}', 'Handler::show', ['id' => '[0-9]+']);
        $base = self::compileRoutes([$route], 'web', 'local');
        foreach (
            [
                new RouteDefinition('changed', ['GET'], '/items/{id}', 'Handler::show', ['id' => '[0-9]+']),
                new RouteDefinition('detail', ['POST'], '/items/{id}', 'Handler::show', ['id' => '[0-9]+']),
                new RouteDefinition('detail', ['GET'], '/other/{id}', 'Handler::show', ['id' => '[0-9]+']),
                new RouteDefinition('detail', ['GET'], '/items/{id}', 'Handler::other', ['id' => '[0-9]+']),
                new RouteDefinition('detail', ['GET'], '/items/{id}', 'Handler::show', ['id' => '[A-Za-z]+']),
                new RouteDefinition(
                    'detail',
                    ['GET'],
                    '/items/{id}',
                    'Handler::show',
                    ['id' => '[0-9]+'],
                    metadata: ['flag' => true],
                ),
            ] as $changed
        ) {
            $envelope = self::compileRoutes([$changed], 'web', 'local');
            self::assertSame([$changed->toArray()], $envelope['payload']['routes']);
            self::assertNotSame($base['_meta']['fingerprint'], $envelope['_meta']['fingerprint']);
            self::assertSame(self::fingerprint($envelope['payload'], 'local'), $envelope['_meta']['fingerprint']);
        }
        foreach ([['api', 'local'], ['web', 'prod']] as [$app, $env]) {
            $envelope = self::compileRoutes([$route], $app, $env);
            self::assertNotSame($base['_meta']['fingerprint'], $envelope['_meta']['fingerprint']);
            self::assertSame(self::fingerprint($envelope['payload'], $env), $envelope['_meta']['fingerprint']);
        }
    }

    public function testFlattenedGroupsAndStandaloneDeclarationsHaveIdenticalIdentityButPrecedenceMatters(): void
    {
        $group = RouteGroup::root()->group('/teams/{team}', 'teams', ['team' => '[0-9]+'], ['scope' => 'team'])
            ->group('/members', 'members', [], ['list' => ['z', 'a']]);
        $grouped = $group->routes([
            new RouteDefinition('z', ['GET'], '/{member}', 'Handler::show'),
            new RouteDefinition('a', ['GET'], '/fixed', 'Handler::fixed'),
        ]);
        $standalone = [
            new RouteDefinition(
                'teams.members.z',
                ['GET'],
                '/teams/{team}/members/{member}',
                'Handler::show',
                ['team' => '[0-9]+'],
                metadata: ['list' => ['z', 'a'], 'scope' => 'team'],
            ),
            new RouteDefinition(
                'teams.members.a',
                ['GET'],
                '/teams/{team}/members/fixed',
                'Handler::fixed',
                ['team' => '[0-9]+'],
                metadata: ['scope' => 'team', 'list' => ['z', 'a']],
            ),
        ];
        self::assertSame(self::descriptors($standalone), self::descriptors($grouped));
        $expected = self::compileRoutes($standalone, 'web', 'local');
        self::assertSame($expected, self::compileRoutes($grouped, 'web', 'local'));
        $reordered = self::compileRoutes(\array_reverse($grouped), 'web', 'local');
        self::assertSame(self::descriptors(\array_reverse($standalone)), $reordered['payload']['routes']);
        self::assertNotSame($expected['_meta']['fingerprint'], $reordered['_meta']['fingerprint']);
        $schema = self::schema();
        self::assertSame(
            $schema->serializeEnvelope($expected, 'web', 'local'),
            $schema->serializeEnvelope(self::compileRoutes($grouped, 'web', 'local'), 'web', 'local'),
        );
    }

    public function testDuplicateProviderNamesAndEquivalentDomainsAreRejectedAcrossProviders(): void
    {
        $a = new RouteDefinition('a', ['GET'], '/items/{id}', 'Handler::show');
        foreach (
            [
                [
                    'one',
                    'one',
                    new RouteDefinition('b', ['GET'], '/other', 'Handler::other'),
                    RouteDefinitionInvalidException::REASON_PROVIDER_IDENTITY_DUPLICATE,
                ],
                [
                    'one',
                    'two',
                    new RouteDefinition('a', ['GET'], '/other', 'Handler::other'),
                    RouteDefinitionInvalidException::REASON_ROUTE_NAME_DUPLICATE,
                ],
                [
                    'one',
                    'two',
                    new RouteDefinition('b', ['POST', 'GET'], '/items/{name}', 'Handler::other'),
                    RouteDefinitionInvalidException::REASON_MATCHING_DOMAIN_EQUIVALENT,
                ],
            ] as [$first, $second, $b, $reason]
        ) {
            $compiler = self::compiler(
                self::schema(),
                [
                    ['a', 10, $first, [$a]],
                    ['b', 0, $second, [$b]],
                ],
                self::plan('web'),
                new RuntimeDiagnosticsInput('local', false),
            );
            try {
                $compiler->compile();
                self::fail('Expected global duplicate rejection.');
            } catch (RouteDefinitionInvalidException $exception) {
                self::assertSame($reason, $exception->reason());
                self::assertSame(
                    RouteDefinitionInvalidException::ERROR_CODE . ': ' . $reason,
                    $exception->getMessage(),
                );
            }
        }
        $b = new RouteDefinition('b', ['POST'], '/items/{name}', 'Handler::other');
        self::assertSame(
            self::descriptors([$a, $b]),
            self::compileRoutes([$a, $b], 'web', 'local')['payload']['routes'],
        );
    }

    /**
     * @param list<RouteDefinition> $routes
     * @return array<string, mixed>
     */
    private static function compileRoutes(array $routes, string $app, string $env): array
    {
        return self::compiler(
            self::schema(),
            [['fixture', 0, 'fixture', $routes]],
            self::plan($app),
            new RuntimeDiagnosticsInput($env, false),
        )->compile();
    }

    /**
     * @param list<RouteDefinition> $routes
     * @return list<array<string, mixed>>
     */
    private static function descriptors(array $routes): array
    {
        return \array_map(static fn (RouteDefinition $route): array => $route->toArray(), $routes);
    }

    /** @param array<string, mixed> $payload */
    private static function fingerprint(array $payload, string $env): string
    {
        return \hash('sha256', StableJsonEncoder::encodeStableMap([
            'appEnv' => $env,
            'generator' => 'platform/routing/artifacts',
            'name' => 'routes',
            'payload' => $payload,
            'schemaVersion' => 1,
            'systemRouteTable' => [
                'entries' => SystemRouteTable::entries(),
                'schemaVersion' => SystemRouteTable::SCHEMA_VERSION,
            ],
        ]));
    }

    /** @param list<array{string, int, string, list<RouteDefinition>}> $providers */
    private static function compiler(
        RoutesArtifactSchema $schema,
        array $providers,
        ModulePlan $plan,
        RuntimeDiagnosticsInput $diagnostics,
    ): RoutesCompiler {
        $tags = new TagRegistry();
        $builder = new ContainerBuilder();
        foreach ($providers as [$serviceId, $priority, $identity, $routes]) {
            $tags->add(ReservedTags::ROUTING_ROUTE_PROVIDER, $serviceId, $priority);
            $builder->instance(
                $serviceId,
                new class($identity, $routes) implements RouteProviderInterface {
                    /** @param list<RouteDefinition> $routes */
                    public function __construct(private readonly string $identity, private readonly array $routes)
                    {
                    }

                    public function id(): string
                    {
                        return $this->identity;
                    }

                    public function routes(): array
                    {
                        return $this->routes;
                    }
                },
            );
        }

        return new RoutesCompiler($plan, $diagnostics, new RouteProviderRegistry($tags, $builder->build()), $schema);
    }

    private static function plan(string $app): ModulePlan
    {
        $id = new ModuleId('platform.routing');

        return new ModulePlan($app, [$id], [], [$id], [new ModulePlanEntry($id, 'coretsia/platform-routing')]);
    }

    private static function schema(): RoutesArtifactSchema
    {
        $classifier = new SensitiveKeyClassifier();

        return new RoutesArtifactSchema(
            new ReservedRoutesGuard(),
            $classifier,
            new DefaultSensitiveDataRedactor($classifier, new SensitiveValueClassifier(), new StableRedactionHasher()),
            new StablePhpArrayDumper(),
        );
    }
}
