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

use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveMixedLevelException;
use Coretsia\Kernel\Config\Exception\ConfigReservedNamespaceException;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\TestCase;

final class DirectivesExclusiveLevelTest extends TestCase
{
    public function testUnknownReservedDirectiveKeyFailsBeforeMixedLevelViolation(): void
    {
        $processor = self::processor();

        try {
            $processor->processRootSubtree(
                root: 'http',
                subtree: [
                    'middleware' => [
                        'app' => [
                            '@push' => [
                                'ControllerMiddleware',
                            ],
                            'other' => true,
                        ],
                    ],
                ],
            );

            self::fail('Expected ConfigReservedNamespaceException was not thrown.');
        } catch (ConfigReservedNamespaceException $exception) {
            self::assertSame(
                ConfigReservedNamespaceException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ConfigReservedNamespaceException::REASON_RESERVED_DIRECTIVE_KEY,
                $exception->reason(),
            );
            self::assertSame('http.middleware.app', $exception->path());
            self::assertSame('@*', $exception->reservedNamespace());
            self::assertContains('@append', $exception->allowedDirectiveKeys());
            self::assertContains('@prepend', $exception->allowedDirectiveKeys());
            self::assertContains('@remove', $exception->allowedDirectiveKeys());
            self::assertContains('@merge', $exception->allowedDirectiveKeys());
            self::assertContains('@replace', $exception->allowedDirectiveKeys());
            self::assertStringNotContainsString('@push', $exception->getMessage());
            self::assertStringNotContainsString('ControllerMiddleware', $exception->getMessage());
            self::assertStringNotContainsString('other', $exception->getMessage());
        }
    }

    public function testUnknownReservedDirectiveKeyFailsBeforeMultipleDirectiveViolation(): void
    {
        $processor = self::processor();

        try {
            $processor->processRootSubtree(
                root: 'http',
                subtree: [
                    'middleware' => [
                        'app' => [
                            '@append' => [
                                'ControllerMiddleware',
                            ],
                            '@push' => [
                                'AuditMiddleware',
                            ],
                        ],
                    ],
                ],
            );

            self::fail('Expected ConfigReservedNamespaceException was not thrown.');
        } catch (ConfigReservedNamespaceException $exception) {
            self::assertSame(
                ConfigReservedNamespaceException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ConfigReservedNamespaceException::REASON_RESERVED_DIRECTIVE_KEY,
                $exception->reason(),
            );
            self::assertSame('http.middleware.app', $exception->path());
            self::assertStringNotContainsString('@push', $exception->getMessage());
            self::assertStringNotContainsString('AuditMiddleware', $exception->getMessage());
        }
    }

    public function testDirectiveLevelMustNotMixDirectiveKeyWithNormalConfigKeys(): void
    {
        $processor = self::processor();

        try {
            $processor->processRootSubtree(
                root: 'http',
                subtree: [
                    'middleware' => [
                        'app' => [
                            '@append' => [
                                'ControllerMiddleware',
                            ],
                            'enabled' => true,
                        ],
                    ],
                ],
            );

            self::fail('Expected ConfigDirectiveMixedLevelException was not thrown.');
        } catch (ConfigDirectiveMixedLevelException $exception) {
            self::assertSame(
                ConfigDirectiveMixedLevelException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ConfigDirectiveMixedLevelException::REASON_DIRECTIVE_MIXED_WITH_CONFIG_KEYS,
                $exception->reason(),
            );
            self::assertSame('http.middleware.app', $exception->path());
            self::assertContains('@append', $exception->allowedDirectiveKeys());
            self::assertStringNotContainsString('ControllerMiddleware', $exception->getMessage());
            self::assertStringNotContainsString('enabled', $exception->getMessage());
        }
    }

    public function testDirectiveLevelMustContainExactlyOneDirectiveKey(): void
    {
        $processor = self::processor();

        try {
            $processor->processRootSubtree(
                root: 'http',
                subtree: [
                    'middleware' => [
                        'app' => [
                            '@append' => [
                                'ControllerMiddleware',
                            ],
                            '@remove' => [
                                'DebugToolbarMiddleware',
                            ],
                        ],
                    ],
                ],
            );

            self::fail('Expected ConfigDirectiveMixedLevelException was not thrown.');
        } catch (ConfigDirectiveMixedLevelException $exception) {
            self::assertSame(
                ConfigDirectiveMixedLevelException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ConfigDirectiveMixedLevelException::REASON_MULTIPLE_DIRECTIVES,
                $exception->reason(),
            );
            self::assertSame('http.middleware.app', $exception->path());
            self::assertContains('@append', $exception->allowedDirectiveKeys());
            self::assertContains('@remove', $exception->allowedDirectiveKeys());
            self::assertStringNotContainsString('ControllerMiddleware', $exception->getMessage());
            self::assertStringNotContainsString('DebugToolbarMiddleware', $exception->getMessage());
        }
    }

    public function testSingleDirectiveLevelIsAcceptedWhenItHasNoNormalSiblingKeys(): void
    {
        $processor = self::processor();

        self::assertSame(
            [
                'middleware' => [
                    'app' => [
                        '@append' => [
                            'ControllerMiddleware',
                        ],
                    ],
                ],
            ],
            $processor->processRootSubtree(
                root: 'http',
                subtree: [
                    'middleware' => [
                        'app' => [
                            '@append' => [
                                'ControllerMiddleware',
                            ],
                        ],
                    ],
                ],
            ),
        );
    }

    public function testExclusiveLevelViolationInsideListItemReportsDeterministicPath(): void
    {
        $processor = self::processor();

        try {
            $processor->processRootSubtree(
                root: 'http',
                subtree: [
                    'middleware' => [
                        'groups' => [
                            [
                                '@append' => [
                                    'ControllerMiddleware',
                                ],
                                'enabled' => true,
                            ],
                        ],
                    ],
                ],
            );

            self::fail('Expected ConfigDirectiveMixedLevelException was not thrown.');
        } catch (ConfigDirectiveMixedLevelException $exception) {
            self::assertSame(
                ConfigDirectiveMixedLevelException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ConfigDirectiveMixedLevelException::REASON_DIRECTIVE_MIXED_WITH_CONFIG_KEYS,
                $exception->reason(),
            );
            self::assertSame('http.middleware.groups[0]', $exception->path());
        }
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
}
