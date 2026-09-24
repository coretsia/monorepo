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

use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\Preset\PresetNamespace;
use Coretsia\Kernel\Module\Preset\PresetNamespaceResolver;
use PHPUnit\Framework\TestCase;

final class PresetNamespaceResolverTest extends TestCase
{
    public function testReservedNamesResolveCanonicallyAndCustomNamesDoNotProbeFilesystem(): void
    {
        $resolver = new PresetNamespaceResolver();
        foreach (['micro', 'express', 'hybrid', 'enterprise'] as $name) {
            self::assertSame(PresetNamespace::Canonical, $resolver->resolve($name));
        }
        self::assertSame(PresetNamespace::Custom, $resolver->resolve('worker-only'));
    }

    public function testInvalidNameFailsBeforeFilesystemAccess(): void
    {
        $this->expectException(ModePresetNotFoundException::class);
        new PresetNamespaceResolver()->resolve('../micro');
    }
}
