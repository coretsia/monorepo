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

use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\Preset\PresetNamespace;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ModePresetLoaderUsesCanonicalSourceOnlyTest extends TestCase
{
    public function testCanonicalNameLoadsOnlyKernelSourceAndNeverApplicationCustomSource(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            \mkdir($package, 0777, true);
            \mkdir($app, 0777, true);
            ModeInfrastructureTestSupport::writePreset(
                $package . '/resources/modes/micro.php',
                'micro',
                ['core.foundation'],
            );
            ModeInfrastructureTestSupport::writePreset(
                $app . '/config/modes/worker-only.php',
                'worker-only',
                ['platform.worker'],
            );
            $loader = ModeInfrastructureTestSupport::factory($package)->createFor(
                ModeInfrastructureTestSupport::bootstrap($app, 'micro'),
                PresetNamespace::Canonical,
            );
            self::assertSame(['micro'], $loader->listNames());
            self::assertTrue($loader->has('micro'));
            self::assertFalse($loader->has('worker-only'));
            self::assertSame(['core.foundation'], $loader->load('micro')->toArray()['required']);
            self::assertNull($loader->tryLoad('worker-only'));
            try {
                $loader->load('worker-only');
                self::fail('Expected namespace isolation.');
            } catch (ModePresetNotFoundException) {
            }
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testCanonicalDirectorySymlinkCannotEscapeKernelPackageRoot(): void
    {
        $root = ModeInfrastructureTestSupport::root();

        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            $outside = $root . '/outside';

            \mkdir($package . '/resources', 0777, true);
            \mkdir($app, 0777, true);
            \mkdir($outside, 0777, true);

            $link = $package . '/resources/modes';

            if (!@\symlink($outside, $link)) {
                if (\PHP_OS_FAMILY !== 'Windows') {
                    self::fail('Creating a directory symbolic link failed.');
                }

                $output = [];
                $exitCode = -1;

                \exec(
                    'mklink /J '
                    . \escapeshellarg($link)
                    . ' '
                    . \escapeshellarg($outside)
                    . ' 2>&1',
                    $output,
                    $exitCode,
                );

                self::assertSame(
                    0,
                    $exitCode,
                    'Creating the Windows directory junction failed: ' . \implode("\n", $output),
                );
            }

            self::assertSame(\realpath($outside), \realpath($link));

            $this->expectException(ModePresetInvalidException::class);

            ModeInfrastructureTestSupport::factory($package)->createFor(
                ModeInfrastructureTestSupport::bootstrap($app, 'micro'),
                PresetNamespace::Canonical,
            );
        } finally {
            if (\PHP_OS_FAMILY === 'Windows' && (\is_dir($link ?? '') || \is_link($link ?? ''))) {
                self::assertTrue(
                    @\rmdir($link),
                    'The Windows directory link must be removed before fixture cleanup.',
                );
            }

            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testCanonicalSourceRespectsConfiguredNonDefaultLocation(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            \mkdir($package, 0777, true);
            \mkdir($app, 0777, true);
            ModeInfrastructureTestSupport::writePreset($package . '/custom-resources/micro.php', 'micro');
            $factory = ModeInfrastructureTestSupport::factory($package, [
                'schema_version' => 1,
                'defaults_path' => 'custom-resources',
                'overrides_path' => 'custom-app-modes',
            ]);
            $bootstrap = ModeInfrastructureTestSupport::bootstrap($app, 'micro');
            self::assertTrue($factory->createFor($bootstrap, PresetNamespace::Canonical)->has('micro'));
            self::assertSame(
                'custom-resources/micro.php',
                $factory->sourceCandidateFor($bootstrap, PresetNamespace::Canonical)['path'],
            );
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }
}
