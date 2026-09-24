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
use Coretsia\Kernel\Module\Preset\CustomPresetSource;
use Coretsia\Kernel\Module\Preset\PresetNamespace;
use Coretsia\Kernel\Tests\Support\FilesystemLinkTestSupport;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ModePresetLoaderUsesCustomSourceOnlyTest extends TestCase
{
    public function testMissingCustomPresetNeverFallsBackToKernelResource(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            \mkdir($package, 0777, true);
            \mkdir($app, 0777, true);
            ModeInfrastructureTestSupport::writePreset($package . '/resources/modes/worker-only.php', 'worker-only');
            $bootstrap = ModeInfrastructureTestSupport::bootstrap($app, 'worker-only');
            $factory = ModeInfrastructureTestSupport::factory($package);
            $candidate = $factory->sourceCandidateFor($bootstrap, PresetNamespace::Custom);
            self::assertSame('config/modes/worker-only.php', $candidate['path']);
            self::assertSame('application:config/modes/worker-only.php', $candidate['sourceId']);
            self::assertSame(20, $candidate['precedence']);
            $loader = $factory->createFor($bootstrap, PresetNamespace::Custom);
            self::assertSame([], $loader->listNames());
            self::assertFalse($loader->has('worker-only'));
            self::assertNull($loader->tryLoad('worker-only'));
            $this->expectException(ModePresetNotFoundException::class);
            $loader->load('worker-only');
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testCustomSourceLoadsOnlyApplicationAndDoesNotExecuteCandidateDuringInspection(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            \mkdir($package, 0777, true);
            \mkdir($app, 0777, true);
            $flag = $root . '/executed.flag';
            ModeInfrastructureTestSupport::write(
                $app . '/config/modes/worker-only.php',
                '<?php file_put_contents(' . \var_export($flag, true) . ', "yes"); return ' . \var_export(
                    ModeInfrastructureTestSupport::payload('worker-only'),
                    true,
                ) . ';',
            );
            $bootstrap = ModeInfrastructureTestSupport::bootstrap($app, 'worker-only');
            $factory = ModeInfrastructureTestSupport::factory($package);
            $candidate = $factory->sourceCandidateFor($bootstrap, PresetNamespace::Custom);
            self::assertFileDoesNotExist($flag);
            $loader = $factory->createFor($bootstrap, PresetNamespace::Custom);
            self::assertSame(['worker-only'], $loader->listNames());
            self::assertTrue($loader->has('worker-only'));
            self::assertFileDoesNotExist($flag);
            self::assertSame('worker-only', $loader->load('worker-only')->name());
            self::assertFileExists($flag);
            self::assertSame('config/modes/worker-only.php', $candidate['path']);
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testCustomDirectorySymlinkCannotEscapeApplicationRoot(): void
    {
        $root = ModeInfrastructureTestSupport::root();

        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            $outside = $root . '/outside';

            \mkdir($package, 0777, true);
            \mkdir($app . '/config', 0777, true);
            \mkdir($outside, 0777, true);

            $link = $app . '/config/modes';

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

            $probe = 'coretsia-directory-link-probe';

            self::assertSame(
                \strlen($probe),
                \file_put_contents($outside . '/boundary-probe.txt', $probe),
            );

            self::assertSame(
                $probe,
                \file_get_contents($link . '/boundary-probe.txt'),
            );

            $this->expectException(ModePresetInvalidException::class);

            ModeInfrastructureTestSupport::factory($package)->createFor(
                ModeInfrastructureTestSupport::bootstrap($app, 'worker-only'),
                PresetNamespace::Custom,
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

    public function testCustomSourceRespectsConfiguredNonDefaultLocation(): void
    {
        $root = ModeInfrastructureTestSupport::root();

        try {
            $package = $root . '/kernel';
            $app = $root . '/application';

            \mkdir($package, 0777, true);
            \mkdir($app, 0777, true);

            ModeInfrastructureTestSupport::writePreset(
                $app . '/custom-app-modes/worker-only.php',
                'worker-only',
            );

            $factory = ModeInfrastructureTestSupport::factory(
                $package,
                [
                    'schema_version' => 1,
                    'defaults_path' => 'custom-kernel-modes',
                    'overrides_path' => 'custom-app-modes',
                ],
            );

            $bootstrap = ModeInfrastructureTestSupport::bootstrap($app, 'worker-only');
            $candidate = $factory->sourceCandidateFor($bootstrap, PresetNamespace::Custom);

            self::assertSame('custom-app-modes/worker-only.php', $candidate['path']);
            self::assertSame('application:custom-app-modes/worker-only.php', $candidate['sourceId']);
            self::assertSame(20, $candidate['precedence']);

            $loader = $factory->createFor($bootstrap, PresetNamespace::Custom);

            self::assertSame(['worker-only'], $loader->listNames());
            self::assertSame('worker-only', $loader->load('worker-only')->name());
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testExistingUnreadableCustomPresetIsInvalidRatherThanMissing(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        $file = $root . '/application/config/modes/worker-only.php';
        $windowsReadDenied = false;

        try {
            $package = $root . '/kernel';
            $app = $root . '/application';

            \mkdir($package, 0777, true);

            ModeInfrastructureTestSupport::writePreset($file, 'worker-only');

            if (\PHP_OS_FAMILY === 'Windows') {
                $output = [];
                $exitCode = -1;

                \exec(
                    'icacls '
                    . \escapeshellarg($file)
                    . ' /deny '
                    . \escapeshellarg('*S-1-1-0:(RD)')
                    . ' 2>&1',
                    $output,
                    $exitCode,
                );

                self::assertSame(
                    0,
                    $exitCode,
                    'Applying the Windows read-deny ACL failed: ' . \implode("\n", $output),
                );

                $windowsReadDenied = true;
            } else {
                self::assertTrue(
                    @\chmod($file, 0000),
                    'Making the preset file unreadable failed.',
                );
            }

            \clearstatcache(true, $file);

            self::assertFileExists($file);
            self::assertFalse(
                \is_readable($file),
                'The fixture must be unreadable before preset loading is tested.',
            );

            $loader = ModeInfrastructureTestSupport::factory($package)->createFor(
                ModeInfrastructureTestSupport::bootstrap($app, 'worker-only'),
                PresetNamespace::Custom,
            );

            try {
                $loader->load('worker-only');

                self::fail('An existing unreadable preset must not be loaded.');
            } catch (ModePresetInvalidException $exception) {
                self::assertSame(
                    ModePresetInvalidException::REASON_PRESET_INVALID,
                    $exception->reason(),
                );
                self::assertSame(['preset' => 'worker-only'], $exception->context());
            }
        } finally {
            if ($windowsReadDenied) {
                $output = [];
                $exitCode = -1;

                \exec(
                    'icacls '
                    . \escapeshellarg($file)
                    . ' /remove:d '
                    . \escapeshellarg('*S-1-1-0')
                    . ' 2>&1',
                    $output,
                    $exitCode,
                );

                if ($exitCode !== 0) {
                    throw new \RuntimeException(
                        'Restoring the Windows preset-file ACL failed: ' . \implode("\n", $output),
                    );
                }
            }

            @\chmod($file, 0600);
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testInvalidPresetPhpExecutionPreservesSafeFailureTaxonomy(): void
    {
        $root = ModeInfrastructureTestSupport::root();

        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            $sensitivePath = $root . '/sensitive-preset-path';

            \mkdir($package, 0777, true);
            \mkdir($app, 0777, true);

            ModeInfrastructureTestSupport::write(
                $app . '/config/modes/worker-only.php',
                '<?php throw new \RuntimeException('
                . \var_export($sensitivePath, true)
                . ');',
            );

            $loader = ModeInfrastructureTestSupport::factory($package)->createFor(
                ModeInfrastructureTestSupport::bootstrap($app, 'worker-only'),
                PresetNamespace::Custom,
            );

            try {
                $loader->load('worker-only');

                self::fail('Invalid preset PHP execution must fail safely.');
            } catch (ModePresetInvalidException $exception) {
                self::assertSame(
                    ModePresetInvalidException::REASON_PRESET_INVALID,
                    $exception->reason(),
                );
                self::assertSame(['preset' => 'worker-only'], $exception->context());
                self::assertStringNotContainsString($sensitivePath, $exception->getMessage());
                self::assertStringNotContainsString($root, $exception->getMessage());
            }
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testDanglingCustomSymlinkIsInvalidRatherThanMissing(): void
    {
        $root = ModeInfrastructureTestSupport::root();

        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            $directory = $app . '/config/modes';

            \mkdir($package, 0777, true);
            \mkdir($directory, 0777, true);

            $directory = \realpath($directory);
            self::assertIsString($directory);

            FilesystemLinkTestSupport::symlink(
                $root . '/missing-preset.php',
                $directory . '/worker-only.php',
            );

            $source = new CustomPresetSource(
                $directory,
                'config/modes',
                \realpath($app),
            );

            self::assertNull($source->resolveFile('actually-absent'));
            self::assertSame([], $source->listNames());
            self::assertFalse($source->has('worker-only'));

            try {
                $source->resolveFile('worker-only');

                self::fail('An existing dangling link must not be treated as absent.');
            } catch (ModePresetInvalidException $exception) {
                self::assertSame(['preset' => 'worker-only'], $exception->context());
            }

            $loader = ModeInfrastructureTestSupport::factory($package)->createFor(
                ModeInfrastructureTestSupport::bootstrap($app, 'worker-only'),
                PresetNamespace::Custom,
            );

            $this->expectException(ModePresetInvalidException::class);

            $loader->load('worker-only');
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testNamespaceEscapingPresetSymlinkIsInvalidRatherThanMissing(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        try {
            $package = $root . '/kernel';
            $app = $root . '/application';
            \mkdir($package, 0777, true);
            \mkdir($app . '/config/modes', 0777, true);
            ModeInfrastructureTestSupport::writePreset($root . '/outside.php', 'worker-only');
            FilesystemLinkTestSupport::symlink(
                $root . '/outside.php',
                $app . '/config/modes/worker-only.php',
            );
            $loader = ModeInfrastructureTestSupport::factory($package)->createFor(
                ModeInfrastructureTestSupport::bootstrap($app, 'worker-only'),
                PresetNamespace::Custom,
            );
            self::assertFalse($loader->has('worker-only'));
            try {
                $loader->load('worker-only');
                self::fail('Existing escaping link must not appear missing.');
            } catch (ModePresetInvalidException $exception) {
                self::assertSame(['preset' => 'worker-only'], $exception->context());
                self::assertNull($loader->tryLoad('micro'));
            }
        } finally {
            ModeInfrastructureTestSupport::remove($root);
        }
    }
}
