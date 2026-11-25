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
use Coretsia\Foundation\Filesystem\AtomicFileWriter;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactPath;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Compile\RouteProviderRegistry;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use PHPUnit\Framework\TestCase;

final class ArtifactBytesRerunNoDiffContractTest extends TestCase
{
    public function testRepeatedCompilationAndPublicationAreByteIdenticalAcrossRootsAndMapInsertionOrders(): void
    {
        $schema = self::schema();
        $plan = self::plan('web');
        $diagnostics = new RuntimeDiagnosticsInput('local', false);
        $metadata = ['z' => ['z' => 2, 'a' => 1], 'a' => ['second', 'first', 'second']];
        $reordered = ['a' => ['second', 'first', 'second'], 'z' => ['a' => 1, 'z' => 2]];
        $expected = null;
        for ($run = 0; $run < 2; $run++) {
            $root = \sys_get_temp_dir() . '/coretsia-routes-rerun-' . \bin2hex(\random_bytes(8));
            self::assertTrue(\mkdir($root, 0700));
            try {
                $routes = [
                    new RouteDefinition(
                        'z',
                        $run === 0 ? ['POST', 'GET'] : ['GET', 'POST'],
                        '/items/{id}',
                        'Handler::show',
                        ['id' => '[0-9]+'],
                        metadata: $run === 0 ? $metadata : $reordered,
                    ),
                    new RouteDefinition('a', ['GET'], '/items/fixed', 'Handler::fixed'),
                ];
                $compiler = self::compiler($schema, [['provider', 0, 'provider', $routes]], $plan, $diagnostics);
                $paths = new RoutesArtifactPath(new RuntimePathContext($root, $root));
                $writer = new RoutesArtifactWriter(
                    $schema,
                    $paths,
                    new ScopedFileLock(),
                    new AtomicFileWriter(),
                    $plan,
                    $diagnostics,
                );
                $envelope = $compiler->compile();
                self::assertTrue($writer->write($envelope));
                $bytes = \file_get_contents($paths->artifactPath());
                self::assertIsString($bytes);
                self::assertSame($schema->serializeEnvelope($envelope, 'web', 'local'), $bytes);
                self::assertFalse($writer->write($compiler->compile()));
                self::assertSame($bytes, \file_get_contents($paths->artifactPath()));
                if ($expected !== null) {
                    self::assertSame($expected, $bytes);
                }
                $expected = $bytes;
                self::assertStringNotContainsString("\r", $bytes);
                self::assertStringEndsWith(";\n", $bytes);
                self::assertFalse(\str_ends_with($bytes, "\n\n"));
                self::assertStringNotContainsString($root, $bytes);
                self::assertStringNotContainsString(\str_replace('\\', '\\\\', $root), $bytes);
                self::assertDoesNotMatchRegularExpression('/\b(?:19|20)[0-9]{2}-[0-9]{2}-[0-9]{2}T/', $bytes);
                self::assertDoesNotMatchRegularExpression(
                    '/"(?:timestamp|generatedAt|createdAt|requires)"\s*=>/',
                    $bytes,
                );
                $decoded = new StablePhpArrayParser()->parseBounded($bytes, RoutesArtifactSchema::limits());
                self::assertSame($envelope, $decoded);
                self::assertSame(['_meta', 'payload'], \array_keys($decoded));
                self::assertSame(['fingerprint', 'generator', 'name', 'schemaVersion'], \array_keys($decoded['_meta']));
                self::assertSame([
                    'app' => 'web',
                    'routes' => \array_map(static fn (RouteDefinition $route): array => $route->toArray(), $routes),
                ], $decoded['payload']);
                self::assertSame(['z', 'a'], \array_column($decoded['payload']['routes'], 'name'));
                self::assertSame(
                    ['a' => ['second', 'first', 'second'], 'z' => ['a' => 1, 'z' => 2]],
                    $decoded['payload']['routes'][0]['metadata'],
                );
                self::assertSame(['GET', 'POST'], $decoded['payload']['routes'][0]['methods']);
                self::assertStringContainsString('"a" => [', $bytes);
                self::assertStringContainsString('"second",' . "\n" . \str_repeat(' ', 24) . '"first",', $bytes);

                foreach (
                    [
                        ['password' => 'fixture-private-secret'],
                        ['location' => $root . '/private'],
                        ['value' => 'Bearer fixture-private-secret'],
                    ] as $unsafe
                ) {
                    $bad = new RouteDefinition('unsafe', ['GET'], '/unsafe', 'Handler::show', metadata: $unsafe);
                    try {
                        self::compiler($schema, [['provider', 0, 'provider', [$bad]]], $plan, $diagnostics)->compile();
                        self::fail('Expected unsafe source declaration rejection.');
                    } catch (RouteDefinitionInvalidException $exception) {
                        self::assertSame(RouteDefinitionInvalidException::REASON_METADATA_UNSAFE, $exception->reason());
                        self::assertStringNotContainsString('fixture-private-secret', $exception->getMessage());
                        self::assertStringNotContainsString($root, $exception->getMessage());
                    }
                    self::assertSame($bytes, \file_get_contents($paths->artifactPath()));
                }
                self::assertStringNotContainsString('fixture-private-secret', $bytes);
            } finally {
                foreach (['routes.php', 'routes.lock'] as $file) {
                    if (\is_file($root . '/' . $file)) {
                        \unlink($root . '/' . $file);
                    }
                }
                \rmdir($root);
            }
        }
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
