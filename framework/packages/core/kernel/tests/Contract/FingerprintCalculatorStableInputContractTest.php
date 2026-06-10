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
use Psr\Log\LoggerInterface;

final class FingerprintCalculatorStableInputContractTest extends TestCase
{
    public function testSameNormalizedInputProducesSameLowercaseSha256(): void
    {
        $calculator = self::calculator($this);
        $input = self::fingerprintInputFixture();

        $first = $calculator->calculate($input);
        $second = $calculator->calculate($input);

        self::assertSame($first, $second);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $first);
    }

    public function testMapKeyInsertionOrderDoesNotAffectFingerprint(): void
    {
        $calculator = self::calculator($this);

        $left = [
            'compiledConfig' => [
                'roots' => [
                    'kernel',
                    'custom',
                ],
                'valueFingerprints' => [
                    'zeta' => [
                        'type' => 'string',
                        'hash' => \str_repeat('a', 64),
                        'len' => 4,
                    ],
                    'alpha' => [
                        'type' => 'string',
                        'hash' => \str_repeat('b', 64),
                        'len' => 5,
                    ],
                ],
            ],
            'schemaVersion' => 1,
        ];

        $right = [
            'schemaVersion' => 1,
            'compiledConfig' => [
                'valueFingerprints' => [
                    'alpha' => [
                        'len' => 5,
                        'hash' => \str_repeat('b', 64),
                        'type' => 'string',
                    ],
                    'zeta' => [
                        'len' => 4,
                        'hash' => \str_repeat('a', 64),
                        'type' => 'string',
                    ],
                ],
                'roots' => [
                    'kernel',
                    'custom',
                ],
            ],
        ];

        self::assertSame(
            $calculator->calculate($left),
            $calculator->calculate($right),
        );
    }

    public function testListOrderAffectsFingerprint(): void
    {
        $calculator = self::calculator($this);

        $first = [
            'schemaVersion' => 1,
            'modulePlan' => [
                'enabled' => [
                    'core.kernel',
                    'core.foundation',
                ],
            ],
        ];

        $second = [
            'schemaVersion' => 1,
            'modulePlan' => [
                'enabled' => [
                    'core.foundation',
                    'core.kernel',
                ],
            ],
        ];

        self::assertNotSame(
            $calculator->calculate($first),
            $calculator->calculate($second),
        );
    }

    public function testRawConfigAndEnvValuesAreAbsentFromNormalizedFingerprintInputFixture(): void
    {
        $fixture = self::fingerprintInputFixture();
        $encoded = \json_encode($fixture, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('raw-config-secret-value', $encoded);
        self::assertStringNotContainsString('raw-env-secret-value', $encoded);
        self::assertStringNotContainsString('/private/absolute/path', $encoded);
    }

    /**
     * @return array<string, mixed>
     */
    private static function fingerprintInputFixture(): array
    {
        return [
            'schemaVersion' => 1,
            'bootstrap' => [
                'appEnv' => 'local',
                'appTarget' => 'api',
                'debug' => false,
                'envSourcePolicy' => 'strict_dotenv',
                'preset' => 'micro',
            ],
            'compiledConfig' => [
                'roots' => [
                    'custom',
                    'kernel',
                ],
                'valueFingerprints' => [
                    'custom.enabled' => [
                        'hash' => \str_repeat('a', 64),
                        'len' => 4,
                        'type' => 'bool',
                    ],
                    'kernel.safe' => [
                        'hash' => \str_repeat('b', 64),
                        'len' => 32,
                        'type' => 'string',
                    ],
                ],
                'validationSubjects' => [
                    'unvalidated' => [
                        [
                            'ownership' => 'user_owned',
                            'root' => 'custom',
                            'validation' => 'unvalidated',
                        ],
                    ],
                    'validated' => [
                        [
                            'ownership' => 'ruleset_owned',
                            'root' => 'kernel',
                            'validation' => 'validated',
                        ],
                    ],
                ],
            ],
            'envOverlay' => [
                'sources' => [
                    'KERNEL_SAFE' => [
                        'hasSource' => true,
                        'source' => [
                            'keyPath' => 'kernel.safe',
                            'root' => 'kernel',
                            'sourceId' => 'env/KERNEL_SAFE',
                            'type' => 'env',
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function calculator(TestCase $testCase): FingerprintCalculator
    {
        $span = $testCase->createStub(SpanInterface::class);

        $tracer = $testCase->createStub(TracerPortInterface::class);
        $tracer
            ->method('startSpan')
            ->willReturn($span);
        $tracer
            ->method('currentSpan')
            ->willReturn(null);
        $tracer
            ->method('inSpan')
            ->willReturnCallback(
                static fn (
                    string $_name,
                    callable $callback,
                    array $_attributes = [],
                ): mixed => $callback($span),
            );

        return new FingerprintCalculator(
            payloadNormalizer: new PayloadNormalizer(),
            tracer: $tracer,
            meter: $testCase->createStub(MeterPortInterface::class),
            logger: $testCase->createStub(LoggerInterface::class),
            stopwatch: new Stopwatch(),
        );
    }
}
