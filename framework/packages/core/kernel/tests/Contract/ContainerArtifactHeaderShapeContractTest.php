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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use PHPUnit\Framework\TestCase;

final class ContainerArtifactHeaderShapeContractTest extends TestCase
{
    public function testContainerArtifactUsesCanonicalHeaderShape(): void
    {
        $envelope = self::compiledContainerBuilder()->build(
            graph: DefinitionGraph::empty(),
            fingerprint: self::fingerprint(),
        );

        self::assertSame(['_meta', 'payload'], \array_keys($envelope));
        self::assertArrayHasKey('_meta', $envelope);
        self::assertArrayHasKey('payload', $envelope);

        $header = $envelope['_meta'];

        self::assertIsArray($header);
        self::assertSame(
            ['fingerprint', 'generator', 'name', 'schemaVersion'],
            \array_keys($header),
        );

        self::assertSame(ArtifactEnvelopeFactory::ARTIFACT_CONTAINER, $header['name']);
        self::assertSame(ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER, $header['schemaVersion']);
        self::assertSame(self::fingerprint(), $header['fingerprint']);
        self::assertSame('core/kernel/artifacts', $header['generator']);

        self::assertArrayNotHasKey('timestamp', $header);
        self::assertArrayNotHasKey('createdAt', $header);
        self::assertArrayNotHasKey('generatedAt', $header);
        self::assertArrayNotHasKey('path', $header);
        self::assertArrayNotHasKey('absolutePath', $header);
        self::assertArrayNotHasKey('host', $header);
        self::assertArrayNotHasKey('hostname', $header);
        self::assertArrayNotHasKey('user', $header);
        self::assertArrayNotHasKey('pid', $header);

        (new ArtifactSchemaValidator())->validateExpected(
            envelope: $envelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
        );
    }

    public function testDumpedContainerArtifactReturnsCanonicalEnvelopeWithCanonicalHeader(): void
    {
        $envelope = self::compiledContainerBuilder()->build(
            graph: DefinitionGraph::empty(),
            fingerprint: self::fingerprint(),
        );

        $bytes = self::dumper()->dumpEnvelope($envelope);

        self::assertStringStartsWith("<?php\n\nreturn [\n", $bytes);
        self::assertStringEndsWith("\n", $bytes);
        self::assertStringNotContainsString("\r", $bytes);
        self::assertStringNotContainsString('timestamp', $bytes);
        self::assertStringNotContainsString('generatedAt', $bytes);
        self::assertStringNotContainsString('createdAt', $bytes);

        $returned = self::includePhpReturn($bytes);

        self::assertSame(['_meta', 'payload'], \array_keys($returned));

        $header = $returned['_meta'] ?? null;

        self::assertIsArray($header);
        self::assertSame(
            ['fingerprint', 'generator', 'name', 'schemaVersion'],
            \array_keys($header),
        );

        self::assertSame(ArtifactEnvelopeFactory::ARTIFACT_CONTAINER, $header['name']);
        self::assertSame(ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER, $header['schemaVersion']);
        self::assertSame(self::fingerprint(), $header['fingerprint']);
        self::assertSame('core/kernel/artifacts', $header['generator']);

        (new ArtifactSchemaValidator())->validateExpected(
            envelope: $returned,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
        );
    }

    public function testContainerPayloadUsesRealCompiledContainerShape(): void
    {
        $envelope = self::compiledContainerBuilder()->build(
            graph: DefinitionGraph::empty(),
            fingerprint: self::fingerprint(),
        );

        $payload = $envelope['payload'] ?? null;

        self::assertIsArray($payload);
        self::assertSame(
            ['aliases', 'compiled', 'kind', 'parameters', 'services', 'tags'],
            \array_keys($payload),
        );

        self::assertSame([], $payload['aliases']);
        self::assertTrue($payload['compiled']);
        self::assertSame('compiled', $payload['kind']);
        self::assertSame([], $payload['parameters']);
        self::assertSame([], $payload['services']);
        self::assertSame([], $payload['tags']);

        self::assertNotSame('stub', $payload['kind']);
        self::assertNotFalse($payload['compiled']);
    }

    private static function compiledContainerBuilder(): CompiledContainerBuilder
    {
        return new CompiledContainerBuilder(
            new ArtifactEnvelopeFactory(new PayloadNormalizer()),
        );
    }

    private static function dumper(): StablePhpArrayDumper
    {
        return new StablePhpArrayDumper(new PayloadNormalizer());
    }

    private static function fingerprint(): string
    {
        return \str_repeat('a', 64);
    }

    /**
     * @return array<string, mixed>
     */
    private static function includePhpReturn(string $bytes): array
    {
        $path = \tempnam(\sys_get_temp_dir(), 'coretsia-container-artifact-');

        if ($path === false) {
            self::fail('Failed to create temporary artifact file.');
        }

        try {
            \file_put_contents($path, $bytes);

            $returned = (static function (string $__path): mixed {
                return include $__path;
            })(
                $path
            );

            self::assertIsArray($returned);

            /** @var array<string, mixed> $returned */
            return $returned;
        } finally {
            if (\is_file($path)) {
                \unlink($path);
            }
        }
    }
}
