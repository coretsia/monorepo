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

use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveMixedLevelException;
use Coretsia\Kernel\Config\Exception\ConfigReservedNamespaceException;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\TestCase;

final class ReservedNamespaceWriteGuardTest extends TestCase
{
    public function testRejectsForbiddenTopLevelRootsInGlobalConfig(): void
    {
        $guard = self::guard();

        foreach (['coretsia', '_internal'] as $root) {
            try {
                $guard->guardGlobalConfig([
                    $root => [
                        'enabled' => true,
                    ],
                ]);

                self::fail('Expected ConfigReservedNamespaceException was not thrown.');
            } catch (ConfigReservedNamespaceException $exception) {
                self::assertSame(ConfigReservedNamespaceException::ERROR_CODE, $exception->errorCode());
                self::assertSame(
                    ConfigReservedNamespaceException::REASON_RESERVED_TOP_LEVEL_ROOT,
                    $exception->reason()
                );
                self::assertSame($root, $exception->path());
                self::assertSame('top-level-root', $exception->reservedNamespace());
                self::assertStringNotContainsString('enabled', $exception->getMessage());
            }
        }
    }

    public function testAllowsFrameworkAndUserOwnedTopLevelRootsWhenTheyAreNotGloballyForbidden(): void
    {
        $guard = self::guard();

        $guard->guardGlobalConfig([
            'foundation' => [
                'container' => [
                    'strict' => true,
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
            'project' => [
                'feature' => [
                    'enabled' => true,
                ],
            ],
        ]);

        self::assertSame(
            [
                '_internal',
                'coretsia',
            ],
            $guard->forbiddenTopLevelRoots(),
        );
    }

    public function testRejectsForbiddenRootSpecificConfigFileRootName(): void
    {
        $guard = self::guard();

        try {
            $guard->guardRootSubtree(
                root: 'coretsia',
                subtree: [
                    'enabled' => true,
                ],
            );

            self::fail('Expected ConfigReservedNamespaceException was not thrown.');
        } catch (ConfigReservedNamespaceException $exception) {
            self::assertSame(ConfigReservedNamespaceException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ConfigReservedNamespaceException::REASON_RESERVED_TOP_LEVEL_ROOT, $exception->reason());
            self::assertSame('coretsia', $exception->path());
            self::assertStringNotContainsString('enabled', $exception->getMessage());
        }
    }

    public function testRejectsUnknownDirectiveNamespaceBeforeMixedLevelViolation(): void
    {
        $processor = self::processor();

        try {
            $processor->processRootSubtree(
                root: 'kernel',
                subtree: [
                    'boot' => [
                        '@push' => [
                            'secret-value',
                        ],
                        'default_env' => 'prod',
                    ],
                ],
            );

            self::fail('Expected ConfigReservedNamespaceException was not thrown.');
        } catch (ConfigReservedNamespaceException $exception) {
            self::assertSame(ConfigReservedNamespaceException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ConfigReservedNamespaceException::REASON_RESERVED_DIRECTIVE_KEY, $exception->reason());
            self::assertSame('kernel.boot', $exception->path());
            self::assertSame('@*', $exception->reservedNamespace());
            self::assertStringNotContainsString('@push', $exception->getMessage());
            self::assertStringNotContainsString('secret-value', $exception->getMessage());
            self::assertStringNotContainsString('default_env', $exception->getMessage());
        }
    }

    public function testRejectsAllowedDirectiveMixedWithNormalConfigKeys(): void
    {
        $processor = self::processor();

        try {
            $processor->processRootSubtree(
                root: 'kernel',
                subtree: [
                    'boot' => [
                        '@replace' => [
                            'default_env' => 'prod',
                        ],
                        'default_app' => 'web',
                    ],
                ],
            );

            self::fail('Expected ConfigDirectiveMixedLevelException was not thrown.');
        } catch (ConfigDirectiveMixedLevelException $exception) {
            self::assertSame(ConfigDirectiveMixedLevelException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                ConfigDirectiveMixedLevelException::REASON_DIRECTIVE_MIXED_WITH_CONFIG_KEYS,
                $exception->reason()
            );
            self::assertSame('kernel.boot', $exception->path());
            self::assertStringNotContainsString('prod', $exception->getMessage());
            self::assertStringNotContainsString('web', $exception->getMessage());
        }
    }

    private static function guard(): ConfigNamespaceGuard
    {
        return new ConfigNamespaceGuard([
            'coretsia',
            '_internal',
        ]);
    }

    private static function processor(): DirectiveProcessor
    {
        return new DirectiveProcessor(
            namespaceGuard: self::guard(),
        );
    }
}
