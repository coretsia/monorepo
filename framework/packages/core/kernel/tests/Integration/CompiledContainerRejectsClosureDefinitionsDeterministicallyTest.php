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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CompiledContainerRejectsClosureDefinitionsDeterministicallyTest extends TestCase
{
    public function testRejectsClosureDescriptorValueWithDeterministicFailure(): void
    {
        $absolutePath = \sys_get_temp_dir()
            . '/coretsia-closure-leak-'
            . \bin2hex(\random_bytes(8))
            . '/config.php';

        $rawConfigValue = 'raw-secret-config-value';
        $sourceSnippet = '<?php function leaked_secret() { return getenv("SECRET_TOKEN"); }';

        $closure = static function () use ($absolutePath, $rawConfigValue, $sourceSnippet): string {
            return $absolutePath . $rawConfigValue . $sourceSnippet;
        };

        try {
            self::compiler()->compile([
                [
                    'kind' => 'service.class',
                    'id' => CompiledContainerRejectsClosureDefinitionsSubject::class,
                    'class' => CompiledContainerRejectsClosureDefinitionsSubject::class,
                    'arguments' => [
                        $closure,
                    ],
                ],
            ]);

            self::fail('Expected compiled-container closure definition failure.');
        } catch (ContainerCompileFailedException $exception) {
            self::assertSame(
                ContainerCompileFailedException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ContainerCompileFailedException::MESSAGE_TOKEN,
                $exception->messageToken(),
            );
            self::assertSame(
                ContainerCompileFailedException::REASON_CLOSURE_DEFINITION,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_CONTAINER_COMPILE_FAILED: container-compile-failed',
                $exception->getMessage(),
            );

            self::assertSafeCompileFailureMessage(
                exception: $exception,
                absolutePath: $absolutePath,
                rawConfigValue: $rawConfigValue,
                sourceSnippet: $sourceSnippet,
            );
        }
    }

    public function testRejectsRawCallableArrayDescriptorValueWithDeterministicFailure(): void
    {
        try {
            self::compiler()->compile([
                [
                    'kind' => 'service.class',
                    'id' => CompiledContainerRejectsClosureDefinitionsSubject::class,
                    'class' => CompiledContainerRejectsClosureDefinitionsSubject::class,
                    'arguments' => [
                        [
                            CompiledContainerRejectsClosureDefinitionsCallableTarget::class,
                            'make',
                        ],
                    ],
                ],
            ]);

            self::fail('Expected compiled-container raw callable definition failure.');
        } catch (ContainerCompileFailedException $exception) {
            self::assertSame(
                ContainerCompileFailedException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ContainerCompileFailedException::MESSAGE_TOKEN,
                $exception->messageToken(),
            );
            self::assertSame(
                ContainerCompileFailedException::REASON_CALLABLE_DEFINITION,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_CONTAINER_COMPILE_FAILED: container-compile-failed',
                $exception->getMessage(),
            );

            self::assertStringNotContainsString(
                CompiledContainerRejectsClosureDefinitionsCallableTarget::class,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('make', $exception->getMessage());
            self::assertStringNotContainsString('callable', $exception->getMessage());
        }
    }

    private static function assertSafeCompileFailureMessage(
        ContainerCompileFailedException $exception,
        string $absolutePath,
        string $rawConfigValue,
        string $sourceSnippet,
    ): void {
        self::assertStringNotContainsString($absolutePath, $exception->getMessage());
        self::assertStringNotContainsString(\dirname($absolutePath), $exception->getMessage());
        self::assertStringNotContainsString(\sys_get_temp_dir(), $exception->getMessage());

        self::assertStringNotContainsString($rawConfigValue, $exception->getMessage());
        self::assertStringNotContainsString($sourceSnippet, $exception->getMessage());

        self::assertStringNotContainsString('Closure', $exception->getMessage());
        self::assertStringNotContainsString('function', $exception->getMessage());
        self::assertStringNotContainsString('<?php', $exception->getMessage());
        self::assertStringNotContainsString('getenv', $exception->getMessage());
        self::assertStringNotContainsString('SECRET_TOKEN', $exception->getMessage());

        self::assertStringNotContainsString('raw_config', $exception->getMessage());
        self::assertStringNotContainsString('raw env', $exception->getMessage());
        self::assertStringNotContainsString('stack trace', $exception->getMessage());
        self::assertStringNotContainsString('Stack trace', $exception->getMessage());
        self::assertStringNotContainsString('previous', $exception->getMessage());
        self::assertStringNotContainsString('Throwable', $exception->getMessage());
        self::assertStringNotContainsString('Exception', $exception->getMessage());
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

    private static function tracer(): TracerPortInterface
    {
        return new class() implements TracerPortInterface {
            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                return CompiledContainerRejectsClosureDefinitionsDeterministicallyTest::span($name);
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = CompiledContainerRejectsClosureDefinitionsDeterministicallyTest::span($name);

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

final class CompiledContainerRejectsClosureDefinitionsSubject
{
}

final class CompiledContainerRejectsClosureDefinitionsCallableTarget
{
    public static function make(): object
    {
        return new \stdClass();
    }
}
