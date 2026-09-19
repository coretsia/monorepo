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
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class KernelArtifactsReuseFoundationStableJsonEncoderContractTest extends TestCase
{
    public function testKernelPayloadNormalizationMatchesFoundationJsonLikeNormalization(): void
    {
        $payload = [
            'zeta' => 'last',
            'alpha' => [
                'z' => 'nested-last',
                'a' => 'nested-first',
            ],
            'items' => [
                [
                    'b' => 2,
                    'a' => 1,
                ],
                'kept-in-list-order',
                null,
                true,
                42,
            ],
        ];

        self::assertSame(
            JsonLikeNormalizer::normalize($payload, 'artifact'),
            PayloadNormalizer::normalizePayload($payload, 'artifact'),
        );
    }

    public function testFoundationStableJsonEncoderBytesAreTheCanonicalHashInput(): void
    {
        $fingerprintInput = [
            'zeta' => 'last',
            'alpha' => [
                'z' => 'nested-last',
                'a' => 'nested-first',
            ],
            'items' => [
                [
                    'b' => 2,
                    'a' => 1,
                ],
                'kept-in-list-order',
            ],
        ];

        $normalized = PayloadNormalizer::normalizePayloadMap($fingerprintInput, 'fingerprintInput');
        $expectedFingerprint = \hash('sha256', StableJsonEncoder::encodeStable($normalized));

        self::assertSame(
            $expectedFingerprint,
            self::fingerprintCalculator()->calculate($fingerprintInput),
        );
    }

    public function testFoundationStableJsonEncoderEmitsStableLfTerminatedJsonForKernelNormalizedPayloads(): void
    {
        $payload = [
            'zeta' => 'last',
            'alpha' => [
                'z' => 'nested-last',
                'a' => 'nested-first',
            ],
            'items' => [
                [
                    'b' => 2,
                    'a' => 1,
                ],
                'kept-in-list-order',
            ],
        ];

        $normalized = PayloadNormalizer::normalizePayloadMap($payload, 'artifact');
        $json = StableJsonEncoder::encodeStable($normalized);

        self::assertSame(
            "{\"alpha\":{\"a\":\"nested-first\",\"z\":\"nested-last\"},\"items\":[{\"a\":1,\"b\":2},\"kept-in-list-order\"],\"zeta\":\"last\"}\n",
            $json,
        );

        self::assertStringNotContainsString("\r", $json);
        self::assertStringEndsWith("\n", $json);
        self::assertFalse(
            \str_ends_with($json, "\n\n"),
            'Stable JSON bytes must end with exactly one final LF.',
        );
    }

    private static function fingerprintCalculator(): FingerprintCalculator
    {
        $testCase = new self('runTest');

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
