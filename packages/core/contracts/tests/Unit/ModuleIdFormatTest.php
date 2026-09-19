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

namespace Coretsia\Contracts\Tests\Unit;

use Coretsia\Contracts\Module\ModuleId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleIdFormatTest extends TestCase
{
    public function testAcceptsCanonicalModuleId(): void
    {
        $id = ModuleId::fromString('platform.cli');

        self::assertSame('platform.cli', $id->value());
        self::assertSame('platform', $id->layer());
        self::assertSame('cli', $id->slug());
        self::assertSame('platform.cli', (string)$id);
    }

    public function testNormalizesAsciiCaseWithoutLocale(): void
    {
        $id = ModuleId::fromString('Platform.Http-Server');

        self::assertSame('platform.http-server', $id->value());
        self::assertSame('platform', $id->layer());
        self::assertSame('http-server', $id->slug());
    }

    public function testBuildsFromLayerAndSlug(): void
    {
        $id = ModuleId::fromLayerAndSlug('Presets', 'Enterprise-Api');

        self::assertSame('presets.enterprise-api', $id->value());
        self::assertSame('presets', $id->layer());
        self::assertSame('enterprise-api', $id->slug());
    }

    public function testComparesByCanonicalValue(): void
    {
        $left = ModuleId::fromString('CORE.KERNEL');
        $right = ModuleId::fromLayerAndSlug('core', 'kernel');
        $other = ModuleId::fromString('platform.cli');

        self::assertTrue($left->equals($right));
        self::assertFalse($left->equals($other));
    }

    public function testRejectsNonAsciiLocaleSensitiveLetters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModuleId::fromString('İntegrations.redis');
    }

    public function testRejectsDotInsideLayerOrSlugParts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModuleId::fromLayerAndSlug('core.kernel', 'http');
    }

    #[DataProvider('invalidModuleIds')]
    public function testRejectsInvalidModuleIds(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModuleId::fromString($value);
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function invalidModuleIds(): iterable
    {
        yield 'empty' => [''];
        yield 'missing-dot' => ['core'];
        yield 'leading-dot' => ['.kernel'];
        yield 'trailing-dot' => ['core.'];
        yield 'too-many-parts' => ['core.kernel.extra'];
        yield 'hyphenated-layer' => ['core-tools.kernel'];
        yield 'underscore' => ['core.kernel_api'];
        yield 'slash' => ['core/kernel'];
        yield 'backslash' => ['core\\kernel'];
        yield 'leading-whitespace' => [' core.kernel'];
        yield 'trailing-whitespace' => ['core.kernel '];
        yield 'outer-whitespace' => [' core.kernel '];
        yield 'whitespace-inside' => ['core.kernel api'];
        yield 'slug-leading-hyphen' => ['core.-kernel'];
        yield 'slug-trailing-hyphen' => ['core.kernel-'];
        yield 'slug-double-hyphen' => ['core.kernel--api'];
        yield 'non-ascii-slug' => ['core.kérnel'];
    }
}
