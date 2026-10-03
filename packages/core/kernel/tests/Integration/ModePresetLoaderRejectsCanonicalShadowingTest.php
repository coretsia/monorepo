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

use Coretsia\Kernel\Module\Exception\CanonicalPresetOverrideException;
use Coretsia\Kernel\Module\Preset\PresetNamespace;
use Coretsia\Kernel\Tests\Support\FilesystemLinkTestSupport;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ModePresetLoaderRejectsCanonicalShadowingTest extends TestCase
{
    public function testReservedApplicationPresetIsRejectedEvenWhenSelectingAnotherName(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            \mkdir($package, 0777, true);
            \mkdir($app, 0777, true);
            ModeInfrastructureTestSupport::writePreset($app . '/config/modes/enterprise.php', 'enterprise');
            try {
                ModeInfrastructureTestSupport::factory($package)->createFor(
                    ModeInfrastructureTestSupport::bootstrap($app, 'worker-only'),
                    PresetNamespace::Custom,
                );
                self::fail('A reserved canonical application file must not shadow another selected name.');
            } catch (CanonicalPresetOverrideException $exception) {
                self::assertSame('canonical-preset-override-forbidden', $exception->reason());
                self::assertSame(['preset' => 'enterprise'], $exception->context());
            }
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testReservedDanglingSymlinkAlsoFailsBeforePresetLookup(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            \mkdir($package, 0777, true);
            \mkdir($app . '/config/modes', 0777, true);
            FilesystemLinkTestSupport::symlink(
                $app . '/missing-preset.php',
                $app . '/config/modes/micro.php',
            );
            $this->expectException(CanonicalPresetOverrideException::class);
            ModeInfrastructureTestSupport::factory($package)->createFor(
                ModeInfrastructureTestSupport::bootstrap($app, 'express'),
                PresetNamespace::Canonical,
            );
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }
}
