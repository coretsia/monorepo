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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Foundation\Container\Container;
use PHPUnit\Framework\TestCase;

final class ContainerDefinitionsAreSharedByDefaultTest extends TestCase
{
    public function testExplicitDefinitionsAreSharedByDefault(): void
    {
        $container = new Container(
            definitions: [
                'service' => static fn (Container $_container): object => new \stdClass(),
            ],
            config: self::foundationConfig(),
        );

        $first = $container->get('service');
        $second = $container->get('service');

        self::assertSame($first, $second);
    }

    public function testExplicitDefinitionsCanBeMarkedNonShared(): void
    {
        $container = new Container(
            definitions: [
                'service' => static fn (Container $_container): object => new \stdClass(),
            ],
            config: self::foundationConfig(),
            definitionShared: [
                'service' => false,
            ],
        );

        $first = $container->get('service');
        $second = $container->get('service');

        self::assertNotSame($first, $second);
    }

    /**
     * @return array<string, mixed>
     */
    private static function foundationConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
            ],
        ];
    }
}
