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
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SpikeFingerprintGoldenHashLockTest extends TestCase
{
    public function testRepoMinFingerprintInputKeepsPhaseZeroGoldenHash(): void
    {
        $input = [
            'schemaVersion' => 1,
            'fixture' => 'repo_min',
            'paths' => self::expectedRepoMinPaths(),
            'trackedEnv' => self::trackedEnvAllowlist(),
        ];

        self::assertSame(
            '4be9f7ebb2b9dadd7c19abbf5127e1fc91c9fcc716a958c610740c894c47e3b8',
            self::calculator()->calculate($input),
        );
    }

    public function testGoldenHashIsStableWhenMapInsertionOrderChanges(): void
    {
        $input = [
            'trackedEnv' => self::trackedEnvAllowlist(),
            'paths' => self::expectedRepoMinPaths(),
            'fixture' => 'repo_min',
            'schemaVersion' => 1,
        ];

        self::assertSame(
            '4be9f7ebb2b9dadd7c19abbf5127e1fc91c9fcc716a958c610740c894c47e3b8',
            self::calculator()->calculate($input),
        );
    }

    /**
     * @return list<non-empty-string>
     */
    private static function expectedRepoMinPaths(): array
    {
        $path = self::repoRoot() . '/framework/tools/spikes/fixtures/repo_min/expected_paths.txt';

        self::assertFileExists($path);

        $bytes = \file_get_contents($path);

        self::assertIsString($bytes);

        $paths = [];

        foreach (\preg_split('/\r?\n/', \trim($bytes)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            $paths[] = $line;
        }

        return $paths;
    }

    /**
     * @return list<non-empty-string>
     */
    private static function trackedEnvAllowlist(): array
    {
        $path = self::repoRoot() . '/framework/tools/spikes/fixtures/repo_min/tracked_env_allowlist.php';

        self::assertFileExists($path);

        $allowlist = require $path;

        self::assertIsArray($allowlist);

        foreach ($allowlist as $value) {
            self::assertIsString($value);
            self::assertNotSame('', $value);
        }

        /** @var list<non-empty-string> $allowlist */
        return $allowlist;
    }

    private static function calculator(): FingerprintCalculator
    {
        return new FingerprintCalculator(
            payloadNormalizer: new PayloadNormalizer(),
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
                return SpikeFingerprintGoldenHashLockTest::span($name);
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = SpikeFingerprintGoldenHashLockTest::span($name);

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

    public static function span(string $name): SpanInterface
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

    private static function repoRoot(): string
    {
        return \dirname(__DIR__, 6);
    }
}
