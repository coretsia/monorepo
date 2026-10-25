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

namespace Coretsia\Platform\Errors\Tests\Integration;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class MapperLazyResolutionTest extends TestCase
{
    public function testRegisteredServicesResolveOnlyDuringDispatchAndStopAtFirstAcceptedMapper(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.null', 30);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.accepted', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.unreachable', 10);

        $nullMapper = $this->createMock(ExceptionMapperInterface::class);
        $nullMapper->expects(self::once())->method('map')->willReturn(null);
        $accepted = new ErrorDescriptor('OWNER_APPROVED_ERROR', 'Operation failed.');
        $acceptedMapper = $this->createMock(ExceptionMapperInterface::class);
        $acceptedMapper->expects(self::once())->method('map')->willReturn($accepted);
        $resolved = [];
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');
        $container->expects(self::exactly(2))->method('get')->willReturnCallback(
            static function (string $id) use (&$resolved, $nullMapper, $acceptedMapper): ExceptionMapperInterface {
                $resolved[] = $id;

                return match ($id) {
                    'mapper.null' => $nullMapper,
                    'mapper.accepted' => $acceptedMapper,
                    default => throw new \LogicException('Unreachable mapper must not resolve.'),
                };
            },
        );

        $registry = new ExceptionMapperRegistry($tags, $container, new NullLogger(), false, false);
        self::assertSame([], $resolved);
        self::assertSame($accepted, $registry->map(new \RuntimeException('SYNTHETIC_PRIVATE')));
        self::assertSame(['mapper.null', 'mapper.accepted'], $resolved);
    }

    public function testEachDispatchReenumeratesRegistrationsAndResolvesServicesWithoutCaching(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.original', 10);
        $resolved = [];
        $mapped = [];
        $original = new ErrorDescriptor('ORIGINAL_ERROR', 'Original public reason.');
        $new = new ErrorDescriptor('NEW_ERROR', 'New public reason.');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(3))->method('get')->willReturnCallback(
            function (string $id) use (&$resolved, &$mapped, $original, $new): ExceptionMapperInterface {
                $resolved[] = $id;
                $mapper = $this->createMock(ExceptionMapperInterface::class);
                $mapper->expects(self::once())->method('map')->willReturnCallback(
                    static function () use (&$mapped, $id, $original, $new): ErrorDescriptor {
                        $mapped[] = $id;

                        return $id === 'mapper.new' ? $new : $original;
                    },
                );

                return $mapper;
            },
        );

        $registry = new ExceptionMapperRegistry($tags, $container, new NullLogger(), false, false);
        self::assertSame([], $resolved);
        self::assertSame($original, $registry->map(new \RuntimeException('FIRST_PRIVATE')));
        self::assertSame($original, $registry->map(new \RuntimeException('SECOND_PRIVATE')));

        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.new', 20);
        self::assertSame($new, $registry->map(new \RuntimeException('THIRD_PRIVATE')));
        self::assertSame(['mapper.original', 'mapper.original', 'mapper.new'], $resolved);
        self::assertSame($resolved, $mapped);
    }
}
