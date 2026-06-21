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

use Coretsia\Foundation\Tag\ReservedTags;
use PHPUnit\Framework\TestCase;

final class ReservedTagsRegistryTest extends TestCase
{
    /**
     * @return list<non-empty-string>
     */
    private static function expectedTags(): array
    {
        return [
            'cli.command',
            'error.mapper',
            'health.check',
            'http.middleware.app',
            'http.middleware.app_post',
            'http.middleware.app_pre',
            'http.middleware.route',
            'http.middleware.route_post',
            'http.middleware.route_pre',
            'http.middleware.system',
            'http.middleware.system_post',
            'http.middleware.system_pre',
            'kernel.hook.after_uow',
            'kernel.hook.before_uow',
            'kernel.reset',
            'kernel.stateful',
        ];
    }

    public function testCanonicalReservedTagListIsStableAndOrdered(): void
    {
        self::assertSame(self::expectedTags(), ReservedTags::all());
    }

    public function testCanonicalConstantsMatchStableTagValues(): void
    {
        self::assertSame('cli.command', ReservedTags::CLI_COMMAND);

        self::assertSame('error.mapper', ReservedTags::ERROR_MAPPER);

        self::assertSame('health.check', ReservedTags::HEALTH_CHECK);

        self::assertSame('http.middleware.app', ReservedTags::HTTP_MIDDLEWARE_APP);
        self::assertSame('http.middleware.app_post', ReservedTags::HTTP_MIDDLEWARE_APP_POST);
        self::assertSame('http.middleware.app_pre', ReservedTags::HTTP_MIDDLEWARE_APP_PRE);
        self::assertSame('http.middleware.route', ReservedTags::HTTP_MIDDLEWARE_ROUTE);
        self::assertSame('http.middleware.route_post', ReservedTags::HTTP_MIDDLEWARE_ROUTE_POST);
        self::assertSame('http.middleware.route_pre', ReservedTags::HTTP_MIDDLEWARE_ROUTE_PRE);
        self::assertSame('http.middleware.system', ReservedTags::HTTP_MIDDLEWARE_SYSTEM);
        self::assertSame('http.middleware.system_post', ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST);
        self::assertSame('http.middleware.system_pre', ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE);

        self::assertSame('kernel.hook.after_uow', ReservedTags::KERNEL_HOOK_AFTER_UOW);
        self::assertSame('kernel.hook.before_uow', ReservedTags::KERNEL_HOOK_BEFORE_UOW);
        self::assertSame('kernel.reset', ReservedTags::KERNEL_RESET);
        self::assertSame('kernel.stateful', ReservedTags::KERNEL_STATEFUL);
    }

    public function testCanonicalReservedTagListContainsNoDuplicates(): void
    {
        $tags = ReservedTags::all();

        self::assertSame(
            $tags,
            \array_values(\array_unique($tags)),
        );
    }

    public function testKnownReservedTagsAreKnown(): void
    {
        foreach (self::expectedTags() as $tag) {
            self::assertTrue(
                ReservedTags::isKnown($tag),
                \sprintf('Expected reserved tag "%s" to be known.', $tag),
            );
        }
    }

    public function testUnknownTagsAreNotKnown(): void
    {
        self::assertFalse(ReservedTags::isKnown(''));
        self::assertFalse(ReservedTags::isKnown('unknown.tag'));
        self::assertFalse(ReservedTags::isKnown('cli.unknown'));
        self::assertFalse(ReservedTags::isKnown('@cli.command'));
    }

    public function testCanonicalReservedTagsUseStableLowercaseDotSeparatedAsciiShape(): void
    {
        foreach (ReservedTags::all() as $tag) {
            self::assertMatchesRegularExpression(
                '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+\z/',
                $tag,
                \sprintf('Reserved tag "%s" must use stable lowercase dot-separated ASCII.', $tag),
            );
        }
    }
}
