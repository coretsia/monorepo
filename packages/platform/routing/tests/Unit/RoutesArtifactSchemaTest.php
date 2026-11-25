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

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteProviderInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Foundation\Serialization\Exception\PhpArraySerializationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
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
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Compile\RouteProviderRegistry;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Definition\RouteGroup;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use PHPUnit\Framework\TestCase;

final class RoutesArtifactSchemaTest extends TestCase
{
    public function testExactEnvelopeDescriptorAndFingerprintInputShapes(): void
    {
        $schema = self::schema();
        $route = new RouteDefinition(
            'detail',
            ['POST', 'GET'],
            '/items/{id}',
            'Handler::show',
            ['id' => '[0-9]+'],
            [],
            ['z' => true, 'a' => ['b', 'a']],
        );
        $descriptor = [
            'defaults' => [],
            'handler' => 'Handler::show',
            'metadata' => ['a' => ['b', 'a'], 'z' => true],
            'methods' => ['GET', 'POST'],
            'name' => 'detail',
            'pathTemplate' => '/items/{id}',
            'requirements' => ['id' => '[0-9]+'],
            'schemaVersion' => 1,
        ];
        $payload = ['app' => 'web', 'routes' => [$descriptor]];
        $input = self::fingerprintInput($payload, 'local');
        self::assertSame(
            ['appEnv', 'generator', 'name', 'payload', 'schemaVersion', 'systemRouteTable'],
            \array_keys($input),
        );
        self::assertSame(['entries', 'schemaVersion'], \array_keys($input['systemRouteTable']));
        foreach ($input['systemRouteTable']['entries'] as $entry) {
            self::assertSame(['owner', 'pattern', 'status', 'type'], \array_keys($entry));
        }
        $expected = self::envelope($payload, 'local');
        self::assertSame($expected, $schema->createEnvelope([$route], 'web', 'local'));
        self::assertSame('routes.php', RoutesArtifactSchema::BASENAME);
        self::assertSame([$descriptor], self::descriptors($schema->hydrateEnvelope($expected, 'web', 'local')));
        self::assertSame(
            new StablePhpArrayDumper()->dumpNormalized($expected),
            $schema->serializeEnvelope($expected, 'web', 'local'),
        );
        self::assertBoundedRoundTrip($schema, $expected);
    }

    public function testFingerprintBindsTargetFrozenEnvironmentOrderPayloadAndReservedTable(): void
    {
        $schema = self::schema();
        $a = self::route(0);
        $b = self::route(1);
        $base = $schema->createEnvelope([$a, $b], 'web', 'local');
        $fingerprint = $base['_meta']['fingerprint'];
        foreach (
            [
                $schema->createEnvelope([$a, $b], 'api', 'local'),
                $schema->createEnvelope([$a, $b], 'web', 'prod'),
                $schema->createEnvelope([$b, $a], 'web', 'local'),
                $schema->createEnvelope([self::route(0, ['value' => 1]), $b], 'web', 'local'),
            ] as $changed
        ) {
            self::assertNotSame($fingerprint, $changed['_meta']['fingerprint']);
        }
        self::assertSame($base, $schema->createEnvelope([$a, $b], 'web', 'local'));
        $input = self::fingerprintInput($base['payload'], 'local');
        self::assertSame($fingerprint, \hash('sha256', StableJsonEncoder::encodeStableMap($input)));
        foreach (\array_keys($input) as $key) {
            $changedInput = $input;
            unset($changedInput[$key]);
            self::assertNotSame($fingerprint, \hash('sha256', StableJsonEncoder::encodeStableMap($changedInput)));
        }
        $changedInput = $input;
        $changedInput['systemRouteTable']['entries'][0]['status'] = 'reserved';
        $stale = $base;
        $stale['_meta']['fingerprint'] = \hash('sha256', StableJsonEncoder::encodeStableMap($changedInput));
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($stale, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID,
        );
        self::assertNotSame($fingerprint, \hash('sha256', StableJsonEncoder::encodeStableMap($changedInput)));
        $changedInput = $input;
        $changedInput['systemRouteTable']['schemaVersion'] = 2;
        $stale = $base;
        $stale['_meta']['fingerprint'] = \hash('sha256', StableJsonEncoder::encodeStableMap($changedInput));
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($stale, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID,
        );
        self::assertNotSame($fingerprint, \hash('sha256', StableJsonEncoder::encodeStableMap($changedInput)));
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($base, 'api', 'local'),
            RoutesArtifactInvalidException::REASON_INCOMPATIBLE,
        );
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($base, 'web', 'prod'),
            RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID,
        );
        $changed = $base;
        $changed['payload']['app'] = 'api';
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($changed, 'api', 'local'),
            RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID,
        );
        $changed = $base;
        $changed['payload']['routes'] = [$b->toArray(), $a->toArray()];
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($changed, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID,
        );
    }

    public function testInvalidUtf8FingerprintInputIsRejectedSafely(): void
    {
        $schema = self::schema();

        $this->assertArtifactRejected(
            static fn () => $schema->createEnvelope(
                [self::route(0)],
                'web',
                "local\xFF",
            ),
            RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID,
        );

        $this->assertArtifactRejected(
            static fn () => $schema->createEnvelope(
                [self::route(0, ['note' => "\xFF"])],
                'web',
                'local',
            ),
            RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID,
        );
    }

    public function testMalformedHeaderPayloadAndNonCanonicalDescriptorsAreRejectedEvenWhenResigned(): void
    {
        $schema = self::schema();
        $base = $schema->createEnvelope([self::route(0)], 'web', 'local');
        foreach ([['_meta', 'extra'], ['payload', 'extra']] as [$section, $key]) {
            $changed = $base;
            $changed[$section][$key] = true;
            $changed = JsonLikeNormalizer::normalize($changed);
            self::assertArtifactRejected(
                static fn () => $schema->hydrateEnvelope($changed, 'web', 'local'),
                RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }
        foreach (['_meta', 'payload'] as $key) {
            $changed = $base;
            unset($changed[$key]);
            self::assertArtifactRejected(
                static fn () => $schema->hydrateEnvelope($changed, 'web', 'local'),
                RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }
        foreach (['name' => 'other', 'generator' => 'other', 'schemaVersion' => 2] as $key => $value) {
            $changed = $base;
            $changed['_meta'][$key] = $value;
            self::assertArtifactRejected(
                static fn () => $schema->hydrateEnvelope($changed, 'web', 'local'),
                RoutesArtifactInvalidException::REASON_INCOMPATIBLE,
            );
        }
        $changed = $base;
        $changed['_meta']['fingerprint'] = \str_repeat('A', 64);
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($changed, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_HEADER_INVALID,
        );
        $descriptor = $base['payload']['routes'][0];
        foreach (
            [
                ['methods' => ['get']],
                ['methods' => ['GET', 'GET']],
                ['methods' => ['POST', 'GET']],
                ['methods' => ['OTHER']],
                ['methods' => []],
                ['schemaVersion' => '1'],
                ['handler' => '/srv/handler.php'],
                ['name' => 'a/b'],
                ['defaults' => ['x' => 'y']],
                ['pathTemplate' => '/{id?}'],
                ['pathTemplate' => '/items/{id}', 'requirements' => ['other' => '[0-9]+']],
                ['metadata' => ['value']],
                ['metadata' => ['path' => '/srv/data']],
                ['group' => []],
            ] as $patch
        ) {
            $changed = self::envelope(['app' => 'web', 'routes' => [\array_replace($descriptor, $patch)]], 'local');
            self::assertArtifactRejected(
                static fn () => $schema->hydrateEnvelope($changed, 'web', 'local'),
                RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }
        foreach (\array_keys($descriptor) as $key) {
            $missing = $descriptor;
            unset($missing[$key]);
            $changed = self::envelope(['app' => 'web', 'routes' => [$missing]], 'local');
            self::assertArtifactRejected(
                static fn () => $schema->hydrateEnvelope($changed, 'web', 'local'),
                RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }
        $changed = self::envelope(
            ['app' => 'web', 'routes' => [\array_replace($descriptor, ['pathTemplate' => '/health'])]],
            'local',
        );
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($changed, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_UNSAFE,
        );
        $changed = $base;
        $changed['payload']['routes'][0] = \array_reverse($descriptor, true);
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($changed, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_MALFORMED,
        );
    }

    public function testRouteCollectionsMustBeFlatListsAtBothSchemaBoundaries(): void
    {
        $schema = self::schema();
        $route = self::route(0);
        $descriptor = $route->toArray();

        foreach ([['route' => $route], [1 => $route]] as $routes) {
            self::assertDefinitionRejected(
                static fn () => $schema->createEnvelope($routes, 'web', 'local'),
                RouteDefinitionInvalidException::REASON_RESOURCE_LIMIT_EXCEEDED,
            );
        }
        foreach ([null, [$route]] as $invalid) {
            self::assertDefinitionRejected(
                static fn () => $schema->createEnvelope([$invalid], 'web', 'local'),
                RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID,
            );
        }

        foreach ([['route' => $descriptor], [null], [[$descriptor]]] as $routes) {
            $envelope = self::envelope(['app' => 'web', 'routes' => $routes], 'local');
            self::assertArtifactRejected(
                static fn () => $schema->hydrateEnvelope($envelope, 'web', 'local'),
                RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        $empty = $schema->createEnvelope([], 'web', 'local');
        self::assertSame(['app' => 'web', 'routes' => []], $empty['payload']);
        self::assertSame([], $schema->hydrateEnvelope($empty, 'web', 'local'));
    }

    public function testDuplicateNamesEquivalentDomainsAndUnsupportedDeclarationsAreRejected(): void
    {
        $schema = self::schema();
        $a = new RouteDefinition('a', ['GET'], '/items/{id}', 'Handler::show', ['id' => '[0-9]+']);
        $b = new RouteDefinition('b', ['GET', 'POST'], '/items/{other}', 'Handler::show', ['other' => '[0-9]+']);
        self::assertDefinitionRejected(
            static fn () => $schema->createEnvelope([$a, $a], 'web', 'local'),
            RouteDefinitionInvalidException::REASON_ROUTE_NAME_DUPLICATE,
        );
        self::assertDefinitionRejected(
            static fn () => $schema->createEnvelope([$a, $b], 'web', 'local'),
            RouteDefinitionInvalidException::REASON_MATCHING_DOMAIN_EQUIVALENT,
        );
        $disjoint = new RouteDefinition('b', ['POST'], '/items/{other}', 'Handler::show', ['other' => '[0-9]+']);
        $overlap = new RouteDefinition('c', ['GET'], '/items/{other}', 'Handler::show');
        self::assertSame(
            self::descriptors([$a, $disjoint, $overlap]),
            $schema->createEnvelope([$a, $disjoint, $overlap], 'web', 'local')['payload']['routes'],
        );
        foreach ([['console', 'local'], ['web', ''], ['web', "dev\n"]] as [$app, $env]) {
            self::assertDefinitionRejected(
                static fn () => $schema->createEnvelope([], $app, $env),
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
        foreach (
            [
                new RouteDefinition('a', ['GET'], '/items', 'Handler::show', defaults: ['x' => 1]),
                new RouteDefinition('a', ['OTHER'], '/items', 'Handler::show'),
            ] as $route
        ) {
            self::assertDefinitionRejected(
                static fn () => $schema->createEnvelope([$route], 'web', 'local'),
                $route->defaults() !== [] ? RouteDefinitionInvalidException::REASON_DECLARATION_UNSUPPORTED : RouteDefinitionInvalidException::REASON_METHOD_INVALID,
            );
        }
    }

    public function testCanonicalRedactionRejectsSensitiveKeysCredentialsAndFilesystemPaths(): void
    {
        $schema = self::schema();
        foreach (
            [
                ['password' => null],
                ['nested' => [['access_token' => false]]],
                ['Authorization' => ''],
                ['value' => 'Bearer synthetic-secret'],
                ['value' => 'https://user:secret@example.test/path'],
                ['value' => 'tok_synthetic123'],
                ['value' => '/srv/private'],
                ['value' => 'C:\\private\\file'],
                ['value' => 'C:/private/file'],
                ['value' => '\\\\server\\share\\file'],
                ['value' => 'file:///private/file'],
                ['value' => 'FILE://server/share'],
                ['value' => [['/private/path']]],
            ] as $metadata
        ) {
            $route = self::route(0, $metadata);
            self::assertDefinitionRejected(
                static fn () => $schema->createEnvelope([$route], 'web', 'local'),
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
            $envelope = self::envelope(['app' => 'web', 'routes' => [$route->toArray()]], 'local');
            self::assertArtifactRejected(
                static fn () => $schema->hydrateEnvelope($envelope, 'web', 'local'),
                RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }
        $sensitive = new RouteDefinition(
            'a',
            ['GET'],
            '/items/{password}',
            'Handler::show',
            ['password' => '[A-Za-z]+'],
        );
        self::assertDefinitionRejected(
            static fn () => $schema->createEnvelope([$sensitive], 'web', 'local'),
            RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
        );
        $envelope = self::envelope(['app' => 'web', 'routes' => [$sensitive->toArray()]], 'local');
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($envelope, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
        );
        $safe = new RouteDefinition(
            'a',
            ['GET'],
            '/items/{id}',
            'Handler::show',
            ['id' => '[0-9]+'],
            [],
            ['uri' => 'https://example.test/public'],
        );
        self::assertSame([$safe->toArray()], $schema->createEnvelope([$safe], 'web', 'local')['payload']['routes']);
    }

    public function testSemanticAdmissionDoesNotTreatAnUnchangedRedactionResultAsAuthorization(): void
    {
        $spy = new RoutingSchemaRedactorFixture('identity');
        $schema = self::schema($spy);
        foreach (
            [
                ['password' => null],
                ['nested' => [['access_token' => false]]],
                ['value' => '/srv/private'],
                ['value' => 'C:/private/file'],
                ['value' => 'C:\\private\\file'],
                ['value' => '\\\\server\\share'],
                ['value' => 'FiLe:///private/file'],
            ] as $metadata
        ) {
            self::assertDefinitionRejected(
                static fn () => $schema->createEnvelope([self::route(0, $metadata)], 'web', 'local'),
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
        $route = new RouteDefinition('a', ['GET'], '/items/{password}', 'Handler::show', ['password' => '[0-9]+']);
        self::assertDefinitionRejected(
            static fn () => $schema->createEnvelope([$route], 'web', 'local'),
            RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
        );
    }

    public function testRedactionIsPerDescriptorRejectionOnlyAndFailuresAreSafe(): void
    {
        $routes = [
            new RouteDefinition('detail', ['GET'], '/items/{id}', 'Handler::show', ['id' => '[0-9]+']),
            self::route(1),
        ];
        $spy = new RoutingSchemaRedactorFixture('identity');
        $schema = self::schema($spy);
        $envelope = $schema->createEnvelope($routes, 'web', 'local');
        self::assertSame(self::descriptors($routes), $envelope['payload']['routes']);
        self::assertSame(self::descriptors($routes), \array_slice($spy->inputs, 0, 2));
        foreach ($spy->inputs as $index => $input) {
            self::assertSame($routes[$index % 2]->toArray(), $input);
        }
        foreach ($spy->contexts as $context) {
            self::assertSame('routing.artifact', $context->scope());
            self::assertSame(RedactionMode::Placeholder, $context->mode());
        }
        $spy->inputs = [];
        $spy->contexts = [];
        $schema->hydrateEnvelope($envelope, 'web', 'local');
        self::assertSame(self::descriptors($routes), $spy->inputs);
        foreach ($spy->contexts as $context) {
            self::assertSame('routing.artifact', $context->scope());
            self::assertSame(RedactionMode::Placeholder, $context->mode());
        }
        foreach (['throw', 'handler', 'pathTemplate', 'methods', 'requirements', 'metadata', 'non-array'] as $mode) {
            $failing = self::schema(new RoutingSchemaRedactorFixture($mode));
            self::assertDefinitionRejected(
                static fn () => $failing->createEnvelope($routes, 'web', 'local'),
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
            self::assertArtifactRejected(
                static fn () => $failing->hydrateEnvelope($envelope, 'web', 'local'),
                RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }
        $classifier = new class() implements SensitiveKeyClassifierInterface {
            public function classify(string $key): ?RedactionKind
            {
                throw new \RuntimeException('synthetic private classifier failure');
            }
        };
        $failing = self::schema(new RoutingSchemaRedactorFixture('identity'), $classifier);
        self::assertDefinitionRejected(
            static fn () => $failing->createEnvelope([self::route(0, ['value' => 1])], 'web', 'local'),
            RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
        );
    }

    public function testExactRouteCountDepthAndIndividualStringLimits(): void
    {
        $schema = self::schema();
        self::assertSame(4096, RoutesArtifactSchema::MAX_ROUTES);
        self::assertSame(32, RoutesArtifactSchema::MAX_DEPTH);
        self::assertSame(100_000, RoutesArtifactSchema::MAX_NODES);
        self::assertSame(65_536, RoutesArtifactSchema::MAX_STRING_BYTES);
        self::assertSame(8_388_608, RoutesArtifactSchema::MAX_TOTAL_STRING_BYTES);
        self::assertSame(16_777_216, RoutesArtifactSchema::MAX_ARTIFACT_BYTES);
        $routes = [];
        for ($index = 0; $index < 4096; ++$index) {
            $routes[] = self::route($index);
        }
        $exact = $schema->createEnvelope($routes, 'web', 'local');
        self::assertCount(4096, $exact['payload']['routes']);
        self::assertBoundedRoundTrip($schema, $exact);
        $routes[] = self::route(4096);
        self::assertDefinitionRejected(
            static fn () => $schema->createEnvelope($routes, 'web', 'local'),
            RouteDefinitionInvalidException::REASON_RESOURCE_LIMIT_EXCEEDED,
        );
        $nested = null;
        for ($index = 0; $index < 27; ++$index) {
            $nested = [$nested];
        }
        $exact = $schema->createEnvelope([self::route(0, ['value' => $nested])], 'web', 'local');
        self::assertSame(32, self::account($exact)[0]);
        self::assertBoundedRoundTrip($schema, $exact);
        self::assertArtifactRejected(
            static fn () => $schema->createEnvelope([self::route(0, ['value' => [$nested]])], 'web', 'local'),
            RoutesArtifactInvalidException::REASON_OVERSIZED,
        );
        $value = \str_repeat('x', 65_536);
        $exact = $schema->createEnvelope([self::route(0, ['value' => $value])], 'web', 'local');
        self::assertBoundedRoundTrip($schema, $exact);
        self::assertDefinitionRejected(
            static fn () => $schema->createEnvelope([self::route(0, ['value' => $value . 'x'])], 'web', 'local'),
            RouteDefinitionInvalidException::REASON_RESOURCE_LIMIT_EXCEEDED,
        );
    }

    public function testCompleteEnvelopeNodeAndAggregateStringBudgetsAtExactLimits(): void
    {
        $schema = self::schema();
        $base = [];
        for ($index = 0; $index < 16; ++$index) {
            $base[] = self::route($index, ['values' => []]);
        }
        $empty = self::envelope(['app' => 'web', 'routes' => self::descriptors($base)], 'local');
        $remaining = 100_000 - self::account($empty)[1];
        $routes = [];
        for ($index = 0; $index < 16; ++$index) {
            $count = \min(7000, $remaining);
            $remaining -= $count;
            $routes[] = self::route($index, ['values' => \array_fill(0, $count, null)]);
        }
        self::assertSame(0, $remaining);
        $exact = $schema->createEnvelope($routes, 'web', 'local');
        self::assertSame(100_000, self::account($exact)[1]);
        self::assertBoundedRoundTrip($schema, $exact);
        $last = $routes[15]->metadata()['values'];
        $last[] = null;
        $routes[15] = self::route(15, ['values' => $last]);
        self::assertEnvelopeOnlyOverflow($schema, $routes, PhpArraySerializationException::REASON_MAX_NODES_EXCEEDED);

        $remaining = 8_388_608 - self::account($empty)[2];
        $routes = [];
        for ($index = 0; $index < 16; ++$index) {
            $values = [];
            for ($part = 0; $part < 8; ++$part) {
                $length = \min(65_536, $remaining);
                $remaining -= $length;
                $values[] = \str_repeat('x', $length);
            }
            $routes[] = self::route($index, ['values' => $values]);
        }
        self::assertSame(0, $remaining);
        $exact = $schema->createEnvelope($routes, 'web', 'local');
        self::assertSame(8_388_608, self::account($exact)[2]);
        self::assertBoundedRoundTrip($schema, $exact);
        $values = $routes[15]->metadata()['values'];
        $values[7] .= 'x';
        $routes[15] = self::route(15, ['values' => $values]);
        self::assertEnvelopeOnlyOverflow(
            $schema,
            $routes,
            PhpArraySerializationException::REASON_TOTAL_STRING_BYTES_EXCEEDED,
        );
    }

    public function testExactSerializedByteLimitAndCompilerReaderParity(): void
    {
        $schema = self::schema();
        // Escaped quote bytes use two serialized bytes; extra scalar nodes supply overhead.
        $base = [];
        for ($index = 0; $index < 16; ++$index) {
            $base[] = self::route($index, ['padding' => \array_fill(0, 64, null), 'values' => \array_fill(0, 8, '')]);
        }
        $empty = self::envelope(['app' => 'web', 'routes' => self::descriptors($base)], 'local');
        $remaining = 16_777_216 - \strlen(new StablePhpArrayDumper()->dumpNormalized($empty));
        $routes = [];
        for ($index = 0; $index < 16; ++$index) {
            $values = [];
            for ($part = 0; $part < 8; ++$part) {
                $length = \min(65_536, \intdiv($remaining, 2));
                $remaining -= $length * 2;
                $value = \str_repeat('"', $length);
                if ($remaining === 1 && $length < 65_536) {
                    $value .= 'x';
                    --$remaining;
                }
                $values[] = $value;
            }
            $routes[] = self::route($index, ['padding' => \array_fill(0, 64, null), 'values' => $values]);
        }
        self::assertSame(0, $remaining);
        $envelope = self::compile($schema, $routes);
        self::assertSame(self::descriptors($routes), $envelope['payload']['routes']);
        $bytes = $schema->serializeEnvelope($envelope, 'web', 'local');
        self::assertSame(16_777_216, \strlen($bytes));
        self::assertReaderRoundTrip($schema, $routes, $bytes);
        $values = $routes[15]->metadata()['values'];
        $values[7] .= 'x';
        $routes[15] = self::route(15, ['padding' => \array_fill(0, 64, null), 'values' => $values]);
        $overflow = self::envelope(['app' => 'web', 'routes' => self::descriptors($routes)], 'local');
        self::assertSame($overflow, JsonLikeNormalizer::normalize($overflow, limits: RoutesArtifactSchema::limits()));
        $overflowBytes = new StablePhpArrayDumper()->dumpNormalized($overflow);
        self::assertSame(16_777_217, \strlen($overflowBytes));
        self::assertArtifactRejected(
            static fn () => $schema->serializeEnvelope($overflow, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_OVERSIZED,
        );
        self::assertArtifactRejected(
            static fn () => self::compile($schema, $routes),
            RoutesArtifactInvalidException::REASON_OVERSIZED,
        );
    }

    public function testGroupedAndStandaloneCompilerOutputIsAcceptedByBoundedReader(): void
    {
        $schema = self::schema();
        $routes = [
            ...RouteGroup::root()->group('/teams/{team}', 'teams', ['team' => '[0-9]+'], ['scope' => 'public'])->routes(
                [
                    new RouteDefinition('z', ['GET'], '/{id}', 'Handler::show'),
                    new RouteDefinition('a', ['GET'], '/', 'Handler::index'),
                ],
            ),
            self::route(0),
        ];
        $envelope = self::compile($schema, $routes);
        self::assertSame(self::descriptors($routes), $envelope['payload']['routes']);
        self::assertReaderRoundTrip($schema, $routes, $schema->serializeEnvelope($envelope, 'web', 'local'));
    }

    private static function schema(
        ?SensitiveDataRedactorInterface $redactor = null,
        ?SensitiveKeyClassifierInterface $classifier = null,
    ): RoutesArtifactSchema {
        $canonical = new SensitiveKeyClassifier();
        return new RoutesArtifactSchema(
            new ReservedRoutesGuard(),
            $classifier ?? $canonical,
            $redactor ?? new DefaultSensitiveDataRedactor(
                $canonical,
                new SensitiveValueClassifier(),
                new StableRedactionHasher(),
            ),
            new StablePhpArrayDumper(),
        );
    }

    /** @param array<string, mixed> $metadata */
    private static function route(int $index, array $metadata = []): RouteDefinition
    {
        return new RouteDefinition('route.' . $index, ['GET'], '/r' . $index, 'Handler::show', metadata: $metadata);
    }

    /**
     * @param list<RouteDefinition> $routes
     * @return list<array<string, mixed>>
     */
    private static function descriptors(array $routes): array
    {
        return \array_map(static fn (RouteDefinition $route): array => $route->toArray(), $routes);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function fingerprintInput(array $payload, string $env): array
    {
        return [
            'appEnv' => $env,
            'generator' => 'platform/routing/artifacts',
            'name' => 'routes',
            'payload' => $payload,
            'schemaVersion' => 1,
            'systemRouteTable' => [
                'entries' => SystemRouteTable::entries(),
                'schemaVersion' => SystemRouteTable::SCHEMA_VERSION,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function envelope(array $payload, string $env): array
    {
        $payload = JsonLikeNormalizer::normalize($payload);
        return [
            '_meta' => [
                'fingerprint' => \hash(
                    'sha256',
                    StableJsonEncoder::encodeStableMap(self::fingerprintInput($payload, $env)),
                ),
                'generator' => 'platform/routing/artifacts',
                'name' => 'routes',
                'schemaVersion' => 1,
            ],
            'payload' => $payload,
        ];
    }

    /** @return array{int, int, int} */
    private static function account(mixed $value): array
    {
        if (!\is_array($value)) {
            return [0, 0, \is_string($value) ? \strlen($value) : 0];
        }
        $depth = 1;
        $nodes = \count($value);
        $bytes = 0;
        foreach ($value as $key => $item) {
            [$childDepth, $childNodes, $childBytes] = self::account($item);
            $depth = \max($depth, 1 + $childDepth);
            $nodes += $childNodes;
            $bytes += (\is_string($key) ? \strlen($key) : 0) + $childBytes;
        }
        return [$depth, $nodes, $bytes];
    }

    /** @param list<RouteDefinition> $routes */
    private static function assertEnvelopeOnlyOverflow(
        RoutesArtifactSchema $schema,
        array $routes,
        string $parserReason,
    ): void {
        $payload = ['app' => 'web', 'routes' => self::descriptors($routes)];
        self::assertSame($payload, JsonLikeNormalizer::normalize($payload, limits: RoutesArtifactSchema::limits()));
        $envelope = self::envelope($payload, 'local');
        $bytes = new StablePhpArrayDumper()->dumpNormalized($envelope);
        try {
            new StablePhpArrayParser()->parseBounded($bytes, RoutesArtifactSchema::limits());
            self::fail('Expected complete envelope budget rejection by the bounded reader.');
        } catch (PhpArraySerializationException $exception) {
            self::assertSame($parserReason, $exception->reason());
        }
        self::assertArtifactRejected(
            static fn () => $schema->createEnvelope($routes, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_OVERSIZED,
        );
        self::assertArtifactRejected(
            static fn () => $schema->hydrateEnvelope($envelope, 'web', 'local'),
            RoutesArtifactInvalidException::REASON_OVERSIZED,
        );
    }

    /** @param array<string, mixed> $envelope */
    private static function assertBoundedRoundTrip(RoutesArtifactSchema $schema, array $envelope): void
    {
        $bytes = $schema->serializeEnvelope($envelope, 'web', 'local');
        self::assertSame($envelope, new StablePhpArrayParser()->parseBounded($bytes, RoutesArtifactSchema::limits()));
    }

    /**
     * @param list<RouteDefinition> $routes
     * @return array<string, mixed>
     */
    private static function compile(RoutesArtifactSchema $schema, array $routes): array
    {
        $id = new ModuleId('platform.routing');
        $plan = new ModulePlan('web', [$id], [], [$id], [new ModulePlanEntry($id, 'coretsia/platform-routing')]);
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ROUTING_ROUTE_PROVIDER, 'fixture');
        $container = new ContainerBuilder()->instance(
            'fixture',
            new class($routes) implements RouteProviderInterface {
                /** @param list<RouteDefinition> $routes */
                public function __construct(private readonly array $routes)
                {
                }

                public function id(): string
                {
                    return 'fixture';
                }

                public function routes(): array
                {
                    return $this->routes;
                }
            },
        )->build();
        return new RoutesCompiler(
            $plan,
            new RuntimeDiagnosticsInput('local', false),
            new RouteProviderRegistry($tags, $container),
            $schema,
        )->compile();
    }

    /** @param list<RouteDefinition> $routes */
    private static function assertReaderRoundTrip(RoutesArtifactSchema $schema, array $routes, string $bytes): void
    {
        $root = \sys_get_temp_dir() . '/coretsia-routing-schema-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($root, 0700));
        try {
            $paths = new RoutesArtifactPath(new RuntimePathContext($root, $root));
            self::assertSame(\strlen($bytes), \file_put_contents($paths->artifactPath(), $bytes));
            $reader = new RoutesArtifactReader(
                $paths,
                new StablePhpArrayParser(),
                $schema,
                new ModulePlan('web', [], [], [], []),
                new RuntimeDiagnosticsInput('local', true),
            );
            self::assertSame(self::descriptors($routes), self::descriptors($reader->read()));
        } finally {
            if (\is_file($root . '/routes.php')) {
                \unlink($root . '/routes.php');
            }
            \rmdir($root);
        }
    }

    private static function assertDefinitionRejected(\Closure $operation, string $reason): void
    {
        try {
            $operation();
            self::fail('Expected route definition rejection.');
        } catch (RouteDefinitionInvalidException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame(RouteDefinitionInvalidException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    private static function assertArtifactRejected(\Closure $operation, string $reason): void
    {
        try {
            $operation();
            self::fail('Expected artifact rejection.');
        } catch (RoutesArtifactInvalidException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame(RoutesArtifactInvalidException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}

final class RoutingSchemaRedactorFixture implements SensitiveDataRedactorInterface
{
    /** @var list<mixed> */
    public array $inputs = [];

    /** @var list<RedactionContext> */
    public array $contexts = [];

    public function __construct(private readonly string $mode)
    {
    }

    public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
    {
        throw new \LogicException('Unexpected scalar redaction.');
    }

    public function redactJsonLike(mixed $value, RedactionContext $context): mixed
    {
        $this->inputs[] = $value;
        $this->contexts[] = $context;
        if ($this->mode === 'throw') {
            throw new \RuntimeException('synthetic private redaction failure');
        }
        if ($this->mode === 'non-array') {
            return null;
        }
        if ($this->mode !== 'identity') {
            $value[$this->mode] = match ($this->mode) {
                'handler' => 'OtherHandler::show',
                'pathTemplate' => '/changed',
                'methods' => ['POST'],
                'requirements' => ['id' => '[A-Za-z]+'],
                'metadata' => ['changed' => true],
                default => throw new \LogicException('Unknown fixture mode.'),
            };
        }
        return $value;
    }
}
