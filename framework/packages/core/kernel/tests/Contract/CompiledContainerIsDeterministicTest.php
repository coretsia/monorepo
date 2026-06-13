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
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
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
        $first = self::containerBytes(self::containerDescriptors());
        $second = self::containerBytes(self::containerDescriptors());

        self::assertSame($first, $second);
    }

    public function testCompiledContainerPayloadUsesDeterministicMapOrdering(): void
    {
        $envelope = self::containerEnvelope(self::containerDescriptors());
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
                    'priority' => 20,
                ],
                [
                    'id' => 'Coretsia\\Tests\\Fixture\\BetaService',
                    'priority' => 10,
                ],
            ],
            $payload['tags']['kernel.reset'],
        );
    }

    public function testCompiledContainerArtifactBytesAreNarrowAndStable(): void
    {
        $bytes = self::containerBytes(self::containerDescriptors());

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
     * @param iterable<array<string, mixed>> $descriptors
     *
     * @return array<string, mixed>
     */
    private static function containerEnvelope(iterable $descriptors): array
    {
        $graph = self::compiler()->compile($descriptors);

        $envelope = self::builder()->build(
            graph: $graph,
            fingerprint: self::fingerprint(),
        );

        (new ArtifactSchemaValidator())->validateExpected(
            envelope: $envelope,
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
        );

        return $envelope;
    }

    /**
     * @param iterable<array<string, mixed>> $descriptors
     */
    private static function containerBytes(iterable $descriptors): string
    {
        return self::dumper()->dumpEnvelope(self::containerEnvelope($descriptors));
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

    /**
     * @return list<array<string, mixed>>
     */
    private static function containerDescriptors(): array
    {
        return [
            [
                'kind' => 'parameter',
                'name' => 'zeta',
                'value' => 'last',
            ],
            [
                'kind' => 'parameters',
                'values' => [
                    'nested' => [
                        'z' => 3,
                        'a' => 1,
                    ],
                    'alpha' => 'first',
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => 'Coretsia\\Tests\\Fixture\\BetaService',
                'class' => 'Coretsia\\Tests\\Fixture\\BetaService',
                'shared' => false,
                'arguments' => [
                    [
                        'name' => 'alpha',
                        'type' => 'parameter',
                    ],
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => 'Coretsia\\Tests\\Fixture\\AlphaService',
                'class' => 'Coretsia\\Tests\\Fixture\\AlphaService',
                'arguments' => [
                    [
                        'id' => 'Coretsia\\Tests\\Fixture\\BetaService',
                        'type' => 'service',
                    ],
                ],
            ],
            [
                'kind' => 'service.class',
                'id' => 'Coretsia\\Tests\\Fixture\\FactoryService',
                'class' => 'Coretsia\\Tests\\Fixture\\FactoryService',
            ],
            [
                'kind' => 'service.factory.class-method',
                'id' => 'Coretsia\\Tests\\Fixture\\GammaService',
                'factoryClass' => 'Coretsia\\Tests\\Fixture\\GammaFactory',
                'method' => 'make',
                'arguments' => [
                    [
                        'class' => 'Coretsia\\Tests\\Fixture\\GammaService',
                        'type' => 'class',
                    ],
                ],
            ],
            [
                'kind' => 'alias',
                'alias' => 'gamma.alias',
                'serviceId' => 'Coretsia\\Tests\\Fixture\\GammaService',
            ],
            [
                'kind' => 'alias',
                'alias' => 'alpha.alias',
                'serviceId' => 'Coretsia\\Tests\\Fixture\\AlphaService',
            ],
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => 'Coretsia\\Tests\\Fixture\\BetaService',
                'priority' => 10,
                'meta' => [],
            ],
            [
                'kind' => 'tag',
                'tag' => 'kernel.reset',
                'serviceId' => 'Coretsia\\Tests\\Fixture\\AlphaService',
                'priority' => 20,
                'meta' => [],
            ],
        ];
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
