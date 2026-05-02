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
use Coretsia\Contracts\Observability\Profiling\ProfilingSessionInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class ProfilingSessionInterfaceShapeContractTest extends TestCase
{
    public function test_profiling_session_interface_shape_is_stable(): void
    {
        $reflection = new ReflectionClass(ProfilingSessionInterface::class);

        self::assertTrue($reflection->isInterface());

        $methodNames = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );

        self::assertSame(['stop'], $methodNames);

        $stop = $reflection->getMethod('stop');

        self::assertTrue($stop->isPublic());
        self::assertSame(0, $stop->getNumberOfParameters());
        self::assertSame(0, $stop->getNumberOfRequiredParameters());

        self::assertMethodReturnType($stop, ProfileArtifact::class, true);
    }

    public function test_session_stop_can_return_artifact_then_null(): void
    {
        $artifact = new ProfileArtifact(
            name: 'core.profile',
            payload: 'raw-private-profile-payload',
        );

        $session = new class($artifact) implements ProfilingSessionInterface {
            public function __construct(
                private ?ProfileArtifact $artifact,
            ) {
            }

            public function stop(): ?ProfileArtifact
            {
                $artifact = $this->artifact;
                $this->artifact = null;

                return $artifact;
            }
        };

        self::assertSame($artifact, $session->stop());
        self::assertNull($session->stop());
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
