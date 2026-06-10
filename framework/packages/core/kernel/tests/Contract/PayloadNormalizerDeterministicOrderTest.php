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

use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class PayloadNormalizerDeterministicOrderTest extends TestCase
{
    public function testNormalizesMapOrderRecursivelyAndPreservesListOrder(): void
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
                false,
                42,
            ],
            'emptyList' => [],
        ];

        $expected = [
            'alpha' => [
                'a' => 'nested-first',
                'z' => 'nested-last',
            ],
            'emptyList' => [],
            'items' => [
                [
                    'a' => 1,
                    'b' => 2,
                ],
                'kept-in-list-order',
                null,
                false,
                42,
            ],
            'zeta' => 'last',
        ];

        self::assertSame(
            $expected,
            PayloadNormalizer::normalizePayload($payload),
        );
    }

    public function testPayloadNormalizerMatchesFoundationBaselineNormalization(): void
    {
        $payload = [
            'zeta' => true,
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
            'emptyList' => [],
        ];

        self::assertSame(
            JsonLikeNormalizer::normalize($payload, 'payload'),
            PayloadNormalizer::normalizePayload($payload),
            'Kernel artifact payload normalization must stay aligned with Foundation json-like normalization.',
        );
    }

    public function testNormalizeMapRejectsRootListsButPlainNormalizePreservesLists(): void
    {
        $list = [
            [
                'z' => 'nested-last',
                'a' => 'nested-first',
            ],
            'kept-in-list-order',
        ];

        self::assertSame(
            [
                [
                    'a' => 'nested-first',
                    'z' => 'nested-last',
                ],
                'kept-in-list-order',
            ],
            PayloadNormalizer::normalizePayload($list),
        );

        $this->expectExceptionMessage('artifact-payload-type-forbidden at payload');

        PayloadNormalizer::normalizePayloadMap($list);
    }
}
