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

namespace Coretsia\Foundation\Tests\Unit;

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\Exception\ContainerException;
use PHPUnit\Framework\TestCase;

final class ContainerCanAutowireIsStrictOnMissingConfigTest extends TestCase
{
    public function testCanAutowireFailsDeterministicallyWhenFoundationConfigIsMissing(): void
    {
        self::assertCanAutowireFailsWith(
            config: [],
            expectedMessage: 'container-config-foundation-missing',
        );
    }

    public function testCanAutowireFailsDeterministicallyWhenFoundationConfigIsNotAMap(): void
    {
        self::assertCanAutowireFailsWith(
            config: [
                'foundation' => true,
            ],
            expectedMessage: 'container-config-foundation-missing',
        );
    }

    public function testCanAutowireFailsDeterministicallyWhenFoundationContainerConfigIsMissing(): void
    {
        self::assertCanAutowireFailsWith(
            config: [
                'foundation' => [],
            ],
            expectedMessage: 'container-config-foundation-container-missing',
        );
    }

    public function testCanAutowireFailsDeterministicallyWhenFoundationContainerConfigIsNotAMap(): void
    {
        self::assertCanAutowireFailsWith(
            config: [
                'foundation' => [
                    'container' => true,
                ],
            ],
            expectedMessage: 'container-config-foundation-container-missing',
        );
    }

    public function testCanAutowireFailsDeterministicallyWhenFoundationContainerConfigShapeIsInvalid(): void
    {
        self::assertCanAutowireFailsWith(
            config: [
                'foundation' => [
                    'container' => [],
                ],
            ],
            expectedMessage: 'container-config-foundation-container-invalid',
        );
    }

    public function testHasFailsDeterministicallyForConcreteClassesWhenAutowireConfigIsMissing(): void
    {
        $container = new Container(config: []);

        try {
            $container->has(ContainerStrictConfigConcreteFixture::class);
        } catch (ContainerException $exception) {
            self::assertSame('container-config-foundation-missing', $exception->getMessage());
            self::assertSame(ContainerException::ERROR_CODE, $exception->errorCode());

            return;
        }

        self::fail('Expected has() to fail deterministically when autowire config is missing.');
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function assertCanAutowireFailsWith(array $config, string $expectedMessage): void
    {
        $container = new Container(config: $config);

        try {
            $container->canAutowire(ContainerStrictConfigConcreteFixture::class);
        } catch (ContainerException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
            self::assertSame(ContainerException::ERROR_CODE, $exception->errorCode());

            return;
        }

        self::fail('Expected canAutowire() to fail with message: ' . $expectedMessage);
    }
}

final class ContainerStrictConfigConcreteFixture
{
}
