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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Config\ConfigDirective;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveTypeMismatchException;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DirectivesAppendRemoveListLikeOnlyTest extends TestCase
{
    public function testAppendAcceptsListAndAppendsAtMergeTime(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $base = [
            'middleware' => [
                'app' => [
                    'AuthMiddleware',
                ],
            ],
        ];

        $patch = $processor->processRootSubtree(
            root: 'http',
            subtree: [
                'middleware' => [
                    'app' => [
                        '@append' => [
                            'AuditMiddleware',
                            'ResponseHeaderMiddleware',
                        ],
                    ],
                ],
            ],
        );

        self::assertSame(
            [
                'middleware' => [
                    'app' => [
                        'AuthMiddleware',
                        'AuditMiddleware',
                        'ResponseHeaderMiddleware',
                    ],
                ],
            ],
            $merger->merge($base, $patch),
        );
    }

    public function testPrependAcceptsListAndPrependsAtMergeTime(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $base = [
            'middleware' => [
                'app' => [
                    'AuthMiddleware',
                    'ControllerMiddleware',
                ],
            ],
        ];

        $patch = $processor->processRootSubtree(
            root: 'http',
            subtree: [
                'middleware' => [
                    'app' => [
                        '@prepend' => [
                            'RequestIdMiddleware',
                        ],
                    ],
                ],
            ],
        );

        self::assertSame(
            [
                'middleware' => [
                    'app' => [
                        'RequestIdMiddleware',
                        'AuthMiddleware',
                        'ControllerMiddleware',
                    ],
                ],
            ],
            $merger->merge($base, $patch),
        );
    }

    public function testRemoveAcceptsListAndRemovesMatchingItemsAtMergeTime(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $base = [
            'middleware' => [
                'app' => [
                    'RequestIdMiddleware',
                    'AuthMiddleware',
                    'DebugToolbarMiddleware',
                    'ControllerMiddleware',
                ],
            ],
        ];

        $patch = $processor->processRootSubtree(
            root: 'http',
            subtree: [
                'middleware' => [
                    'app' => [
                        '@remove' => [
                            'DebugToolbarMiddleware',
                        ],
                    ],
                ],
            ],
        );

        self::assertSame(
            [
                'middleware' => [
                    'app' => [
                        'RequestIdMiddleware',
                        'AuthMiddleware',
                        'ControllerMiddleware',
                    ],
                ],
            ],
            $merger->merge($base, $patch),
        );
    }

    public function testAppendAcceptsEmptyListAsNoNewItems(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $base = [
            'middleware' => [
                'app' => [
                    'AuthMiddleware',
                ],
            ],
        ];

        $patch = $processor->processRootSubtree(
            root: 'http',
            subtree: [
                'middleware' => [
                    'app' => [
                        '@append' => [],
                    ],
                ],
            ],
        );

        self::assertSame($base, $merger->merge($base, $patch));
    }

    public function testPrependAcceptsEmptyListAsNoNewItems(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $base = [
            'middleware' => [
                'app' => [
                    'AuthMiddleware',
                ],
            ],
        ];

        $patch = $processor->processRootSubtree(
            root: 'http',
            subtree: [
                'middleware' => [
                    'app' => [
                        '@prepend' => [],
                    ],
                ],
            ],
        );

        self::assertSame($base, $merger->merge($base, $patch));
    }

    public function testRemoveAcceptsEmptyListAsNoRemovedItems(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $base = [
            'middleware' => [
                'app' => [
                    'AuthMiddleware',
                    'ControllerMiddleware',
                ],
            ],
        ];

        $patch = $processor->processRootSubtree(
            root: 'http',
            subtree: [
                'middleware' => [
                    'app' => [
                        '@remove' => [],
                    ],
                ],
            ],
        );

        self::assertSame($base, $merger->merge($base, $patch));
    }

    #[DataProvider('invalidListDirectivePayloadProvider')]
    public function testListDirectivesRejectNonListPayloads(
        ConfigDirective $directive,
        mixed $payload,
    ): void {
        $processor = self::processor();

        try {
            $processor->processRootSubtree(
                root: 'http',
                subtree: [
                    'middleware' => [
                        'app' => [
                            $directive->key() => $payload,
                        ],
                    ],
                ],
            );

            self::fail('Expected ConfigDirectiveTypeMismatchException was not thrown.');
        } catch (ConfigDirectiveTypeMismatchException $exception) {
            self::assertSame(
                ConfigDirectiveTypeMismatchException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ConfigDirectiveTypeMismatchException::REASON_LIST_DIRECTIVE_VALUE_MUST_BE_LIST,
                $exception->reason(),
            );
            self::assertSame($directive->key(), $exception->directiveKey());
            self::assertSame(ConfigDirectiveTypeMismatchException::EXPECTED_LIST, $exception->expectedType());
            self::assertSame('http.middleware.app', $exception->path());
            self::assertStringNotContainsString('ControllerMiddleware', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: ConfigDirective, 1: mixed}>
     */
    public static function invalidListDirectivePayloadProvider(): iterable
    {
        yield 'append-map' => [
            ConfigDirective::Append,
            [
                'name' => 'ControllerMiddleware',
            ],
        ];

        yield 'prepend-map' => [
            ConfigDirective::Prepend,
            [
                'name' => 'ControllerMiddleware',
            ],
        ];

        yield 'remove-map' => [
            ConfigDirective::Remove,
            [
                'name' => 'ControllerMiddleware',
            ],
        ];

        yield 'append-scalar' => [
            ConfigDirective::Append,
            'ControllerMiddleware',
        ];

        yield 'prepend-scalar' => [
            ConfigDirective::Prepend,
            'ControllerMiddleware',
        ];

        yield 'remove-scalar' => [
            ConfigDirective::Remove,
            'ControllerMiddleware',
        ];
    }

    private static function processor(): DirectiveProcessor
    {
        return new DirectiveProcessor(
            namespaceGuard: new ConfigNamespaceGuard([
                'coretsia',
                '_internal',
            ]),
        );
    }

    private static function merger(DirectiveProcessor $processor): ConfigMerger
    {
        return new ConfigMerger(
            directiveProcessor: $processor,
        );
    }
}
