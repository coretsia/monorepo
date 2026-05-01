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

use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ErrorHandlingContextMetadataIsJsonLikeContractTest extends TestCase
{
    public function test_metadata_accepts_json_like_values_and_normalizes_maps_deterministically(): void
    {
        $context = new ErrorHandlingContext(
            metadata: [
                'z' => [
                    'b' => 2,
                    'a' => 1,
                ],
                'string' => 'value',
                'null' => null,
                'list' => [
                    'first',
                    [
                        'b' => false,
                        'a' => true,
                    ],
                    3,
                ],
                'int' => 1,
                'bool' => true,
            ],
        );

        self::assertSame(
            [
                'bool' => true,
                'int' => 1,
                'list' => [
                    'first',
                    [
                        'a' => true,
                        'b' => false,
                    ],
                    3,
                ],
                'null' => null,
                'string' => 'value',
                'z' => [
                    'a' => 1,
                    'b' => 2,
                ],
            ],
            $context->metadata(),
        );
    }

    public function test_metadata_preserves_list_order(): void
    {
        $context = new ErrorHandlingContext(
            metadata: [
                'items' => [
                    'z',
                    'a',
                    'm',
                ],
            ],
        );

        self::assertSame(
            [
                'items' => [
                    'z',
                    'a',
                    'm',
                ],
            ],
            $context->metadata(),
        );
    }

    public function test_metadata_rejects_non_empty_root_lists(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorHandlingContext(
            metadata: [
                'root-list-value',
            ],
        );
    }

    public function test_metadata_rejects_floats_nan_and_infinity(): void
    {
        $invalidMetadataCases = [
            'float' => [
                'value' => 1.5,
            ],
            'nan' => [
                'value' => \NAN,
            ],
            'inf' => [
                'value' => \INF,
            ],
            'negative-inf' => [
                'value' => -\INF,
            ],
            'nested-float' => [
                'nested' => [
                    'value' => 1.5,
                ],
            ],
            'list-float' => [
                'items' => [
                    1,
                    1.5,
                ],
            ],
        ];

        foreach ($invalidMetadataCases as $label => $metadata) {
            try {
                new ErrorHandlingContext(metadata: $metadata);

                self::fail(sprintf('Expected invalid metadata case "%s" to throw.', $label));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_metadata_rejects_objects_closures_and_invalid_keys(): void
    {
        $invalidMetadataCases = [
            'object' => [
                'value' => new stdClass(),
            ],
            'closure' => [
                'value' => static fn (): string => 'not-json-like',
            ],
            'empty-key' => [
                '' => 'value',
            ],
            'nested-object' => [
                'nested' => [
                    'value' => new stdClass(),
                ],
            ],
        ];

        foreach ($invalidMetadataCases as $label => $metadata) {
            try {
                new ErrorHandlingContext(metadata: $metadata);

                self::fail(sprintf('Expected invalid metadata case "%s" to throw.', $label));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
