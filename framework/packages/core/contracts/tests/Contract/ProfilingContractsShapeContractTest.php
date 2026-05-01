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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Observability\Profiling\ProfileArtifact;
use Coretsia\Contracts\Observability\Profiling\ProfileExporterInterface;
use Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use stdClass;

final class ProfilingContractsShapeContractTest extends TestCase
{
    public function test_profile_artifact_shape_is_stable_and_payload_is_opaque(): void
    {
        $artifact = new ProfileArtifact(
            name: 'core.profile',
            metadata: [
                'z' => [
                    'b' => false,
                    'a' => true,
                ],
                'duration_ms' => 123,
            ],
            payload: 'raw-private-profile-payload',
        );

        self::assertSame('core.profile', $artifact->name());
        self::assertSame(
            [
                'duration_ms' => 123,
                'z' => [
                    'a' => true,
                    'b' => false,
                ],
            ],
            $artifact->metadata(),
        );
        self::assertSame('raw-private-profile-payload', $artifact->payload());

        self::assertSame(
            [
                'metadata' => [
                    'duration_ms' => 123,
                    'z' => [
                        'a' => true,
                        'b' => false,
                    ],
                ],
                'name' => 'core.profile',
                'payload' => null,
            ],
            $artifact->toArray(),
        );

        self::assertSame(
            [
                'metadata',
                'name',
                'payload',
            ],
            array_keys($artifact->toArray()),
        );

        self::assertNotContains('raw-private-profile-payload', $artifact->toArray());
    }

    public function test_profile_artifact_rejects_invalid_metadata(): void
    {
        $invalidMetadataCases = [
            'root-list' => [
                'value',
            ],
            'float' => [
                'value' => 1.5,
            ],
            'nan' => [
                'value' => \NAN,
            ],
            'inf' => [
                'value' => \INF,
            ],
            'object' => [
                'value' => new stdClass(),
            ],
            'closure' => [
                'value' => static fn (): string => 'not-json-like',
            ],
            'empty-key' => [
                '' => 'value',
            ],
        ];

        foreach ($invalidMetadataCases as $label => $metadata) {
            try {
                new ProfileArtifact(
                    name: 'core.profile',
                    metadata: $metadata,
                );

                self::fail(sprintf('Expected profile metadata case "%s" to throw.', $label));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_profiler_port_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(ProfilerPortInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('start'));
        self::assertTrue($reflection->hasMethod('stop'));

        $start = $reflection->getMethod('start');

        self::assertTrue($start->isPublic());
        self::assertSame(2, $start->getNumberOfParameters());
        self::assertSame(1, $start->getNumberOfRequiredParameters());

        $startParameters = $start->getParameters();

        self::assertSame('name', $startParameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $startParameters[0]->getType());
        self::assertSame('string', $startParameters[0]->getType()->getName());

        self::assertSame('metadata', $startParameters[1]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $startParameters[1]->getType());
        self::assertSame('array', $startParameters[1]->getType()->getName());
        self::assertTrue($startParameters[1]->isDefaultValueAvailable());
        self::assertSame([], $startParameters[1]->getDefaultValue());

        self::assertMethodReturnType($start, 'void', false);

        $stop = $reflection->getMethod('stop');

        self::assertTrue($stop->isPublic());
        self::assertSame(0, $stop->getNumberOfParameters());
        self::assertMethodReturnType($stop, ProfileArtifact::class, true);
    }

    public function test_profile_exporter_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(ProfileExporterInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertTrue($reflection->hasMethod('export'));

        $method = $reflection->getMethod('export');

        self::assertTrue($method->isPublic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('artifact', $parameters[0]->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame(ProfileArtifact::class, $parameters[0]->getType()->getName());

        self::assertMethodReturnType($method, 'void', false);
    }

    public function test_profiler_and_exporter_implementations_can_compose_through_contracts(): void
    {
        $profiler = new class() implements ProfilerPortInterface {
            private ?ProfileArtifact $artifact = null;

            /**
             * @param array<string,mixed> $metadata
             */
            public function start(string $name, array $metadata = []): void
            {
                $this->artifact = new ProfileArtifact(
                    name: $name,
                    metadata: $metadata,
                    payload: 'raw-private-profile-payload',
                );
            }

            public function stop(): ?ProfileArtifact
            {
                return $this->artifact;
            }
        };

        $exporter = new class() implements ProfileExporterInterface {
            public ?ProfileArtifact $exported = null;

            public function export(ProfileArtifact $artifact): void
            {
                $this->exported = $artifact;
            }
        };

        $profiler->start('core.profile', ['operation' => 'test']);

        $artifact = $profiler->stop();

        self::assertInstanceOf(ProfileArtifact::class, $artifact);

        $exporter->export($artifact);

        self::assertSame($artifact, $exporter->exported);
        self::assertSame('raw-private-profile-payload', $artifact->payload());
        self::assertNull($artifact->toArray()['payload']);
    }

    private static function assertMethodReturnType(
        ReflectionMethod $method,
        string $expectedName,
        bool $expectedAllowsNull,
    ): void {
        $type = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }
}
