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
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use PHPUnit\Framework\TestCase;

final class RoutesArtifactHeaderShapeContractTest extends TestCase
{
    public function testRoutesUseTheGlobalEnvelopeAndExactOwnerHeader(): void
    {
        $schema = self::schema();
        $route = new RouteDefinition('index', ['GET'], '/', 'Handler::index');
        foreach (['web', 'api'] as $app) {
            $envelope = $schema->createEnvelope([$route], $app, 'local');
            self::assertSame(['_meta', 'payload'], \array_keys($envelope));
            self::assertSame(['fingerprint', 'generator', 'name', 'schemaVersion'], \array_keys($envelope['_meta']));
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $envelope['_meta']['fingerprint']);
            self::assertSame('platform/routing/artifacts', $envelope['_meta']['generator']);
            self::assertSame('routes', $envelope['_meta']['name']);
            self::assertSame(1, $envelope['_meta']['schemaVersion']);
            self::assertSame(['app' => $app, 'routes' => [$route->toArray()]], $envelope['payload']);
            self::assertArrayNotHasKey('requires', $envelope['_meta']);
            $bytes = $schema->serializeEnvelope($envelope, $app, 'local');
            self::assertSame(
                $envelope,
                new StablePhpArrayParser()->parseBounded($bytes, RoutesArtifactSchema::limits()),
            );
        }
        self::assertSame('routes', RoutesArtifactSchema::ARTIFACT_NAME);
        self::assertSame('platform/routing/artifacts', RoutesArtifactSchema::GENERATOR);
        self::assertSame(1, RoutesArtifactSchema::SCHEMA_VERSION);
        self::assertSame('routes.php', RoutesArtifactSchema::BASENAME);
    }

    public function testMissingExtraAndUnsupportedEnvelopeAndHeaderMembersAreRejected(): void
    {
        $schema = self::schema();
        $base = $schema->createEnvelope([], 'web', 'local');
        $invalid = [];
        foreach (\array_keys($base) as $key) {
            $changed = $base;
            unset($changed[$key]);
            $invalid[] = [$changed, RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID];
        }
        foreach (\array_keys($base['_meta']) as $key) {
            $changed = $base;
            unset($changed['_meta'][$key]);
            $invalid[] = [$changed, RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID];
        }
        foreach (['requires' => [], 'timestamp' => 1, 'appEnv' => 'local', 'extra' => true] as $key => $value) {
            $changed = $base;
            $changed['_meta'][$key] = $value;
            $invalid[] = [$changed, RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID];
        }
        $invalid[] = [$base + ['requires' => []], RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID];
        $invalid[] = [$base['payload'], RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID];
        foreach (['_meta', 'payload'] as $key) {
            $changed = $base;
            $changed[$key] = 'invalid';
            $invalid[] = [$changed, RoutesArtifactInvalidException::REASON_MALFORMED];
        }
        $invalid[] = [
            [0 => $base['_meta'], 1 => $base['payload']],
            RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
        ];
        foreach (['name' => 'other', 'generator' => 'other/generator', 'schemaVersion' => 2] as $key => $value) {
            $changed = $base;
            $changed['_meta'][$key] = $value;
            $invalid[] = [$changed, RoutesArtifactInvalidException::REASON_INCOMPATIBLE];
        }
        $changed = $base;
        $changed['_meta']['schemaVersion'] = '1';
        $invalid[] = [$changed, RoutesArtifactInvalidException::REASON_INCOMPATIBLE];
        foreach ([null, 1, '', \str_repeat('A', 64), \str_repeat('a', 63)] as $fingerprint) {
            $changed = $base;
            $changed['_meta']['fingerprint'] = $fingerprint;
            $invalid[] = [$changed, RoutesArtifactInvalidException::REASON_HEADER_INVALID];
        }
        foreach ($invalid as [$envelope, $reason]) {
            $envelope = JsonLikeNormalizer::normalize($envelope);
            foreach (
                [
                    static fn () => $schema->hydrateEnvelope($envelope, 'web', 'local'),
                    static fn () => $schema->serializeEnvelope($envelope, 'web', 'local'),
                ] as $operation
            ) {
                try {
                    $operation();
                    self::fail('Expected invalid artifact header or envelope rejection.');
                } catch (RoutesArtifactInvalidException $exception) {
                    self::assertSame($reason, $exception->reason());
                    self::assertSame(
                        RoutesArtifactInvalidException::ERROR_CODE . ': ' . $reason,
                        $exception->getMessage(),
                    );
                }
            }
        }
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
