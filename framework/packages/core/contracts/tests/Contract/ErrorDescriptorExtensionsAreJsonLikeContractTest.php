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

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ErrorDescriptorExtensionsAreJsonLikeContractTest extends TestCase
{
    public function test_extensions_accept_json_like_values_and_normalize_maps_deterministically(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            severity: ErrorSeverity::Error,
            extensions: [
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
            $descriptor->extensions(),
        );
    }

    public function test_extensions_preserve_list_order(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            severity: ErrorSeverity::Error,
            extensions: [
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
            $descriptor->extensions(),
        );
    }

    public function test_extensions_reject_non_empty_root_lists(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            severity: ErrorSeverity::Error,
            extensions: [
                'root-list-value',
            ],
        );
    }

    public function test_extensions_reject_floats_nan_and_infinity(): void
    {
        $invalidExtensions = [
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

        foreach ($invalidExtensions as $label => $extensions) {
            try {
                new ErrorDescriptor(
                    code: 'core.example',
                    message: 'Example message.',
                    severity: ErrorSeverity::Error,
                    extensions: $extensions,
                );

                self::fail(sprintf('Expected invalid extensions case "%s" to throw.', $label));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_extensions_reject_objects_closures_and_invalid_keys(): void
    {
        $invalidExtensions = [
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

        foreach ($invalidExtensions as $label => $extensions) {
            try {
                new ErrorDescriptor(
                    code: 'core.example',
                    message: 'Example message.',
                    severity: ErrorSeverity::Error,
                    extensions: $extensions,
                );

                self::fail(sprintf('Expected invalid extensions case "%s" to throw.', $label));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
