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

final class DirectivesMergeMapLikeOnlyTest extends TestCase
{
    public function testMergeAcceptsMapAndMergesAtMergeTime(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $base = [
            'boot' => [
                'default_env' => 'prod',
                'default_app' => 'main',
            ],
            'modules' => [
                'discovery' => [
                    'enabled' => true,
                    'source' => 'composer',
                ],
            ],
        ];

        $patch = $processor->processRootSubtree(
            root: 'kernel',
            subtree: [
                'boot' => [
                    '@merge' => [
                        'default_env' => 'dev',
                    ],
                ],
                'modules' => [
                    'discovery' => [
                        '@merge' => [
                            'source' => 'static',
                        ],
                    ],
                ],
            ],
        );

        self::assertSame(
            [
                'boot' => [
                    'default_app' => 'main',
                    'default_env' => 'dev',
                ],
                'modules' => [
                    'discovery' => [
                        'enabled' => true,
                        'source' => 'static',
                    ],
                ],
            ],
            $merger->merge($base, $patch),
        );
    }

    public function testMergeAcceptsEmptyMapAsNoChangedKeys(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $base = [
            'boot' => [
                'default_app' => 'main',
                'default_env' => 'prod',
            ],
        ];

        $patch = $processor->processRootSubtree(
            root: 'kernel',
            subtree: [
                'boot' => [
                    '@merge' => [],
                ],
            ],
        );

        self::assertSame($base, $merger->merge($base, $patch));
    }

    public function testMergeAgainstMissingBaseCreatesMap(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $patch = $processor->processRootSubtree(
            root: 'kernel',
            subtree: [
                'boot' => [
                    '@merge' => [
                        'default_env' => 'dev',
                    ],
                ],
            ],
        );

        self::assertSame(
            [
                'boot' => [
                    'default_env' => 'dev',
                ],
            ],
            $merger->merge([], $patch),
        );
    }

    #[DataProvider('invalidMergeDirectivePayloadProvider')]
    public function testMergeRejectsNonMapPayloads(mixed $payload): void
    {
        $processor = self::processor();

        try {
            $processor->processRootSubtree(
                root: 'kernel',
                subtree: [
                    'boot' => [
                        '@merge' => $payload,
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
                ConfigDirectiveTypeMismatchException::REASON_MERGE_DIRECTIVE_VALUE_MUST_BE_MAP,
                $exception->reason(),
            );
            self::assertSame(ConfigDirective::Merge->key(), $exception->directiveKey());
            self::assertSame(ConfigDirectiveTypeMismatchException::EXPECTED_MAP, $exception->expectedType());
            self::assertSame('kernel.boot', $exception->path());
            self::assertStringNotContainsString('prod', $exception->getMessage());
            self::assertStringNotContainsString('dev', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidMergeDirectivePayloadProvider(): iterable
    {
        yield 'list' => [
            [
                'prod',
                'dev',
            ],
        ];

        yield 'string' => [
            'dev',
        ];

        yield 'bool' => [
            true,
        ];

        yield 'int' => [
            1,
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
