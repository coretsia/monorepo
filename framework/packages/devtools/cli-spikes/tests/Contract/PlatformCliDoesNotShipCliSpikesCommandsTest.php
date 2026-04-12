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

namespace Coretsia\Devtools\CliSpikes\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class PlatformCliDoesNotShipCliSpikesCommandsTest extends TestCase
{
    public function testPlatformCliComposerJsonDoesNotRequireCliSpikesPackage(): void
    {
        $repoRoot = \dirname(__DIR__, 6);
        $composerJsonPath = $repoRoot . '/framework/packages/platform/cli/composer.json';

        self::assertFileExists($composerJsonPath, 'platform/cli composer.json MUST exist.');

        $bytes = \file_get_contents($composerJsonPath);
        self::assertIsString($bytes, 'platform/cli composer.json read failed');

        $decoded = \json_decode($bytes, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertFalse(\array_is_list($decoded));

        /** @var array<string, mixed> $composer */
        $composer = $decoded;

        $require = $composer['require'] ?? [];
        $requireDev = $composer['require-dev'] ?? [];

        self::assertIsArray($require);
        self::assertIsArray($requireDev);

        self::assertArrayNotHasKey(
            'coretsia/devtools-cli-spikes',
            $require,
            'platform/cli MUST NOT require coretsia/devtools-cli-spikes.'
        );

        self::assertArrayNotHasKey(
            'coretsia/devtools-cli-spikes',
            $requireDev,
            'platform/cli MUST NOT require-dev coretsia/devtools-cli-spikes.'
        );
    }

    public function testPlatformCliPhpSourcesDoNotReferenceCliSpikesNamespaceOrCommandClasses(): void
    {
        $repoRoot = \dirname(__DIR__, 6);
        $platformCliRoot = $repoRoot . '/framework/packages/platform/cli';

        self::assertDirectoryExists($platformCliRoot, 'platform/cli package root MUST exist.');

        $dirs = [
            $platformCliRoot . '/src',
            $platformCliRoot . '/config',
        ];

        $files = [];
        foreach ($dirs as $dir) {
            if (!\is_dir($dir)) {
                continue;
            }

            foreach ($this->collectPhpFiles($dir) as $file) {
                $files[] = $file;
            }
        }

        self::assertNotEmpty($files, 'platform/cli PHP sources/config MUST exist.');

        $forbidden = [
            'Coretsia\\Devtools\\CliSpikes\\',
            'DoctorCommand',
            'SpikeFingerprintCommand',
            'SpikeConfigDebugCommand',
            'DeptracGraphCommand',
            'WorkspaceSyncDryRunCommand',
            'WorkspaceSyncApplyCommand',
        ];

        foreach ($files as $file) {
            $code = \file_get_contents($file);
            self::assertIsString($code, 'Read failed: ' . $this->displayPath($repoRoot, $file));

            foreach ($forbidden as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $code,
                    'platform/cli MUST NOT reference cli-spikes commands directly. File: ' . $this->displayPath($repoRoot, $file)
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function collectPhpFiles(string $dir): array
    {
        $out = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            if (!$item->isFile()) {
                continue;
            }
            if (\strtolower((string)$item->getExtension()) !== 'php') {
                continue;
            }

            $out[] = $item->getPathname();
        }

        \sort($out);

        return $out;
    }

    private function displayPath(string $repoRoot, string $absPath): string
    {
        $root = \rtrim(\str_replace('\\', '/', $repoRoot), '/');
        $path = \str_replace('\\', '/', $absPath);

        if ($root !== '' && \str_starts_with($path, $root . '/')) {
            return \substr($path, \strlen($root) + 1);
        }

        return $path;
    }
}
