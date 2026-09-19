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

namespace Coretsia\Dto\Attribute\Tests\Contract;

use Coretsia\Dto\Attribute\Dto;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testDtoAttributeIsBehaviorFreeMarkerOnly(): void
    {
        $reflection = new ReflectionClass(Dto::class);

        self::assertTrue($reflection->isFinal(), 'DTO marker attribute must remain final.');
        self::assertFalse($reflection->isAbstract(), 'DTO marker attribute must not be abstract.');
        self::assertFalse($reflection->isInterface(), 'DTO marker attribute must not be an interface.');
        self::assertFalse($reflection->isTrait(), 'DTO marker attribute must not be a trait.');

        self::assertFalse($reflection->getParentClass(), 'DTO marker attribute must not extend another class.');
        self::assertSame([], $reflection->getInterfaceNames(), 'DTO marker attribute must not implement interfaces.');
        self::assertSame([], $reflection->getTraitNames(), 'DTO marker attribute must not use traits.');

        self::assertNull($reflection->getConstructor(), 'DTO marker attribute must not define a constructor.');
        self::assertSame([], $reflection->getProperties(), 'DTO marker attribute must not define state.');
        self::assertSame([], $reflection->getMethods(), 'DTO marker attribute must not define behavior.');
    }

    public function testPackageComposerMetadataStaysPhpOnlyLibraryPackage(): void
    {
        $composer = self::packageComposer();

        self::assertSame('coretsia/core-dto-attribute', $composer['name'] ?? null);
        self::assertSame('library', $composer['type'] ?? null);

        self::assertSame(
            [
                'php' => '^8.4',
            ],
            $composer['require'] ?? null,
            'core/dto-attribute must depend on PHP only.',
        );

        self::assertSame(
            'library',
            $composer['extra']['coretsia']['kind'] ?? null,
            'core/dto-attribute must remain a library package.',
        );
    }

    public function testPackageAutoloadIsScopedToDtoAttributeNamespaceOnly(): void
    {
        $composer = self::packageComposer();

        self::assertSame(
            [
                'Coretsia\\Dto\\Attribute\\' => 'src/Attribute/',
            ],
            $composer['autoload']['psr-4'] ?? null,
        );

        self::assertSame(
            [
                'Coretsia\\Dto\\Attribute\\Tests\\' => 'tests/',
            ],
            $composer['autoload-dev']['psr-4'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function packageComposer(): array
    {
        $composerPath = \dirname(__DIR__, 2) . '/composer.json';

        self::assertFileExists($composerPath);

        $json = \file_get_contents($composerPath);

        self::assertIsString($json);

        $decoded = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            self::fail('Package composer.json must decode to an object-like array.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
