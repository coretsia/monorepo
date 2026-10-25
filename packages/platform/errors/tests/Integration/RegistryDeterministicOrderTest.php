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

final class RegistryDeterministicOrderTest extends TestCase
{
    public function testDiscoveryPreservesPriorityDescendingServiceIdAscendingAndFirstRegistrationWins(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.zeta', 10);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.beta', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.alpha', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.last', -5);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.beta', 100, ['invalid' => true]);
        $tags->add('cli.command', 'other.tag', 1000);

        $registrations = $tags->all(ReservedTags::ERROR_MAPPER);
        self::assertSame(
            ['mapper.alpha', 'mapper.beta', 'mapper.zeta', 'mapper.last'],
            \array_map(static fn ($registration): string => $registration->id(), $registrations),
        );
        self::assertSame(
            [20, 20, 10, -5],
            \array_map(static fn ($registration): int => $registration->priority(), $registrations),
        );
        self::assertSame([], $registrations[1]->meta());

        $resolutionOrder = [];
        $mapper = $this->createMock(ExceptionMapperInterface::class);
        $mapper->expects(self::exactly(4))->method('map')->willReturn(null);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(4))->method('get')->willReturnCallback(
            static function (string $serviceId) use (&$resolutionOrder, $mapper): ExceptionMapperInterface {
                $resolutionOrder[] = $serviceId;

                return $mapper;
            },
        );

        $registry = new ExceptionMapperRegistry($tags, $container, new NullLogger(), false, false);
        self::assertNull($registry->map(new \RuntimeException('SYNTHETIC_PRIVATE')));
        self::assertSame(['mapper.alpha', 'mapper.beta', 'mapper.zeta', 'mapper.last'], $resolutionOrder);
    }

    public function testFirstNonNullDescriptorWinsWithoutResolvingLowerPriorityCandidates(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.later', 0);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.winner', 10);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.earlier-null', 20);

        $nullMapper = $this->createMock(ExceptionMapperInterface::class);
        $nullMapper->expects(self::once())->method('map')->willReturn(null);
        $expected = new ErrorDescriptor('OWNER_APPROVED_ERROR', 'Operation failed.');
        $winner = $this->createMock(ExceptionMapperInterface::class);
        $winner->expects(self::once())->method('map')->willReturn($expected);
        $resolved = [];
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturnCallback(
            static function (string $serviceId) use (&$resolved, $nullMapper, $winner): ExceptionMapperInterface {
                $resolved[] = $serviceId;

                return match ($serviceId) {
                    'mapper.earlier-null' => $nullMapper,
                    'mapper.winner' => $winner,
                    default => throw new \LogicException('Later candidates must not resolve.'),
                };
            },
        );

        $result = new ExceptionMapperRegistry($tags, $container, new NullLogger(), false, false)
            ->map(new \RuntimeException('SYNTHETIC_PRIVATE'));

        self::assertSame($expected, $result);
        self::assertSame(['mapper.earlier-null', 'mapper.winner'], $resolved);
    }
}
