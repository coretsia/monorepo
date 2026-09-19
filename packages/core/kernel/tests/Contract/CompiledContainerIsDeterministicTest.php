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

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Container\ContainerCompiler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CompiledContainerIsDeterministicTest extends TestCase
{
    public function testIdenticalCompiledContainerInputsProduceIdenticalContainerArtifactBytes(): void
    {
        $first = self::containerBytes(self::containerDefinitions());
        $second = self::containerBytes(self::containerDefinitions());

        self::assertSame($first, $second);
    }

    public function testCanonicalCompiledContainerFixtureHasPlatformIndependentSha256(): void
    {
        self::assertSame(
            '6bc85b1882fb7bfe6485254674133f6f7191f23fab24e300b7be3dd3869361a2',
            \hash(
                'sha256',
                self::containerBytes(self::containerDefinitions()),
            ),
        );
    }

    public function testCompiledContainerPayloadUsesDeterministicMapOrdering(): void
    {
        $envelope = self::containerEnvelope(self::containerDefinitions());
        $payload = $envelope['payload'] ?? null;

        self::assertIsArray($payload);
        self::assertSame(
            ['aliases', 'compiled', 'kind', 'parameters', 'services', 'tags'],
            \array_keys($payload),
        );

        self::assertIsArray($payload['services']);
        self::assertSame(
            [
                'Coretsia\\Tests\\Fixture\\AlphaService',
                'Coretsia\\Tests\\Fixture\\BetaService',
                'Coretsia\\Tests\\Fixture\\FactoryService',
                'Coretsia\\Tests\\Fixture\\GammaService',
            ],
            \array_keys($payload['services']),
        );

        self::assertIsArray($payload['parameters']);
        self::assertSame(
            ['alpha', 'nested', 'zeta'],
            \array_keys($payload['parameters']),
        );

        self::assertIsArray($payload['aliases']);
        self::assertSame(
            ['alpha.alias', 'gamma.alias'],
            \array_keys($payload['aliases']),
        );

        self::assertIsArray($payload['tags']);
        self::assertSame(
            ['kernel.reset'],
            \array_keys($payload['tags']),
        );

        self::assertSame(
            [
                [
                    'id' => 'Coretsia\\Tests\\Fixture\\AlphaService',
                    'meta' => [
                        'flags' => [
                            'a' => 1,
                            'b' => 2,
                        ],
                        'mode' => 'runtime',
                    ],
                    'priority' => 20,
                ],
                [
                    'id' => 'Coretsia\\Tests\\Fixture\\BetaService',
                    'meta' => [],
                    'priority' => 10,
                ],
            ],
            $payload['tags']['kernel.reset'],
        );
    }

    public function testContainerArtifactRejectsTagEntryWithoutMetadataField(): void
    {
        $envelope = self::containerEnvelope(self::containerDefinitions());

        unset($envelope['payload']['tags']['kernel.reset'][0]['meta']);

        $this->expectException(ArtifactInvalidException::class);
        $this->expectExceptionMessage(
            ArtifactInvalidException::ERROR_CODE
            . ': '
            . ArtifactInvalidException::REASON_SCHEMA_INVALID,
        );

        new ArtifactSchemaValidator()->validateExpected(
            envelope: $envelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
        );
    }

    public function testContainerArtifactRejectsNonCanonicalTagMetadataOrdering(): void
    {
        $envelope = self::containerEnvelope(self::containerDefinitions());

        $envelope['payload']['tags']['kernel.reset'][0]['meta'] = [
            'mode' => 'runtime',
            'flags' => [
                'a' => 1,
                'b' => 2,
            ],
        ];

        $this->expectException(ArtifactInvalidException::class);
        $this->expectExceptionMessage(
            ArtifactInvalidException::ERROR_CODE
            . ': '
            . ArtifactInvalidException::REASON_SCHEMA_INVALID,
        );

        new ArtifactSchemaValidator()->validateExpected(
            envelope: $envelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
        );
    }

    public function testContainerArtifactRejectsUnsafeTagMetadataValue(): void
    {
        $envelope = self::containerEnvelope(self::containerDefinitions());

        $envelope['payload']['tags']['kernel.reset'][0]['meta'] = [
            'weight' => 1.5,
        ];

        $this->expectException(ArtifactInvalidException::class);
        $this->expectExceptionMessage(
            ArtifactInvalidException::ERROR_CODE
            . ': '
            . ArtifactInvalidException::REASON_SCHEMA_INVALID,
        );

        new ArtifactSchemaValidator()->validateExpected(
            envelope: $envelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
        );
    }

    public function testCompiledContainerArtifactBytesAreNarrowAndStable(): void
    {
        $bytes = self::containerBytes(self::containerDefinitions());

        self::assertStringStartsWith("<?php\n\nreturn [\n", $bytes);
        self::assertStringEndsWith("\n", $bytes);
        self::assertStringNotContainsString("\r", $bytes);

        self::assertStringContainsString('"name" => "container"', $bytes);
        self::assertStringContainsString('"schemaVersion" => 1', $bytes);
        self::assertStringContainsString('"kind" => "compiled"', $bytes);
        self::assertStringContainsString('"compiled" => true', $bytes);

        self::assertStringNotContainsString('"kind" => "stub"', $bytes);
        self::assertStringNotContainsString('"compiled" => false', $bytes);
        self::assertStringNotContainsString('generatedAt', $bytes);
        self::assertStringNotContainsString('createdAt', $bytes);
        self::assertStringNotContainsString('timestamp', $bytes);
        self::assertStringNotContainsString(\sys_get_temp_dir(), $bytes);
        self::assertStringNotContainsString('Closure', $bytes);
        self::assertStringNotContainsString('function (', $bytes);
        self::assertStringNotContainsString('fn (', $bytes);
    }

    /**
     * @return array<string, mixed>
     */
    private static function containerEnvelope(
        ContainerDefinitionSet $definitions,
    ): array {
        $graph = self::compiler()->compile($definitions);

        $envelope = self::builder()->build(
            graph: $graph,
            fingerprint: self::fingerprint(),
        );

        new ArtifactSchemaValidator()->validateExpected(
            envelope: $envelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
        );

        return $envelope;
    }

    private static function containerBytes(
        ContainerDefinitionSet $definitions,
    ): string {
        return self::dumper()->dumpEnvelope(
            self::containerEnvelope($definitions),
        );
    }

    private static function compiler(): ContainerCompiler
    {
        return new ContainerCompiler(
            tracer: self::tracer(),
            meter: self::meter(),
            logger: new NullLogger(),
            stopwatch: new Stopwatch(),
        );
    }

    private static function builder(): CompiledContainerBuilder
    {
        return new CompiledContainerBuilder(
            new ArtifactEnvelopeFactory(new PayloadNormalizer()),
        );
    }

    private static function dumper(): StablePhpArrayDumper
    {
        return new StablePhpArrayDumper(new PayloadNormalizer());
    }

    private static function containerDefinitions(): ContainerDefinitionSet
    {
        return new ContainerDefinitionBuilder()
            ->parameter(
                'zeta',
                'last',
            )
            ->parameter(
                'nested',
                [
                    'z' => 3,
                    'a' => 1,
                ],
            )
            ->parameter(
                'alpha',
                'first',
            )
            ->classService(
                id: 'Coretsia\Tests\Fixture\BetaService',
                class: 'Coretsia\Tests\Fixture\BetaService',
                arguments: [
                    ContainerValueReference::parameter('alpha'),
                ],
                shared: false,
            )
            ->classService(
                id: 'Coretsia\Tests\Fixture\AlphaService',
                class: 'Coretsia\Tests\Fixture\AlphaService',
                arguments: [
                    ContainerValueReference::service(
                        'Coretsia\Tests\Fixture\BetaService',
                    ),
                ],
            )
            ->classService(
                'Coretsia\Tests\Fixture\FactoryService',
                'Coretsia\Tests\Fixture\FactoryService',
            )
            ->classMethodFactory(
                id: 'Coretsia\Tests\Fixture\GammaService',
                factoryClass: CompiledContainerIsDeterministicFactory::class,
                method: 'make',
                arguments: [
                    ContainerValueReference::class(
                        'Coretsia\Tests\Fixture\GammaService',
                    ),
                ],
            )
            ->alias(
                'gamma.alias',
                'Coretsia\Tests\Fixture\GammaService',
            )
            ->alias(
                'alpha.alias',
                'Coretsia\Tests\Fixture\AlphaService',
            )
            ->tag(
                tag: 'kernel.reset',
                serviceId: 'Coretsia\Tests\Fixture\BetaService',
                priority: 10,
            )
            ->tag(
                tag: 'kernel.reset',
                serviceId: 'Coretsia\Tests\Fixture\AlphaService',
                priority: 20,
                meta: [
                    'mode' => 'runtime',
                    'flags' => [
                        'b' => 2,
                        'a' => 1,
                    ],
                ],
            )
            ->build();
    }

    private static function fingerprint(): string
    {
        return \str_repeat('a', 64);
    }

    private static function tracer(): TracerPortInterface
    {
        return new class() implements TracerPortInterface {
            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                return CompiledContainerIsDeterministicTest::span($name);
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = CompiledContainerIsDeterministicTest::span($name);

                try {
                    return $callback($span);
                } finally {
                    $span->end();
                }
            }

            public function currentSpan(): ?SpanInterface
            {
                return null;
            }
        };
    }

    public static function span(string $name = 'kernel.test'): SpanInterface
    {
        return new class($name) implements SpanInterface {
            public function __construct(
                private readonly string $name,
            ) {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function setAttribute(string $key, mixed $value): void
            {
            }

            public function setAttributes(array $attributes): void
            {
            }

            public function addEvent(string $name, array $attributes = []): void
            {
            }

            public function recordException(\Throwable $throwable, array $attributes = []): void
            {
            }

            public function end(): void
            {
            }
        };
    }

    private static function meter(): MeterPortInterface
    {
        return new class() implements MeterPortInterface {
            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
            }

            public function observe(string $name, int $value, array $labels = []): void
            {
            }
        };
    }
}

final class CompiledContainerIsDeterministicFactory
{
    public static function make(string $class): object
    {
        return new \stdClass();
    }
}
