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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Platform\Cli\Module\CliModule;
use PHPUnit\Framework\TestCase;

final class WorkflowEpicIntroducesNoReplayArtifactTest extends TestCase
{
    public function testCliPackageHasNoReplayClassesArtifactOrConfiguration(): void
    {
        $package = \dirname(__DIR__, 2);
        $sources = [$package . '/src', $package . '/config', $package . '/resources'];
        $visited = 0;
        foreach ($sources as $directory) {
            self::assertDirectoryExists($directory);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                ++$visited;
                self::assertSame(
                    0,
                    \preg_match(
                        '~cli[-_.]?replay|replay[-_.]?artifact|replay[-_.]?storage|replay[-_.]?registry~i',
                        $file->getFilename(),
                    ),
                    $file->getPathname(),
                );
                if ($file->getExtension() !== 'php' && !\in_array(
                    $file->getExtension(),
                    ['json', 'yaml', 'yml'],
                    true,
                )) {
                    continue;
                }
                $source = \file_get_contents($file->getPathname());
                self::assertIsString($source);
                $code = '';
                foreach (\token_get_all($file->getExtension() === 'php' ? $source : '<?php ' . $source) as $token) {
                    if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $code .= \is_array($token) ? $token[1] : $token;
                }
                self::assertSame(
                    0,
                    \preg_match(
                        '~cli[_\-.]?replay|cli-replay@1|cli_replay@1|\bReplay[A-Za-z0-9_]*\b~i',
                        $code,
                    ),
                    $file->getPathname(),
                );
            }
        }
        self::assertGreaterThan(20, $visited);

        $defaults = require $package . '/config/cli.php';
        $rules = require $package . '/config/rules.php';
        self::assertIsArray($defaults);
        self::assertIsArray($rules);
        self::assertSame(['commands', 'workflows', 'output'], \array_keys($defaults));
        self::assertSame(['definitions'], \array_keys($defaults['workflows']));
        self::assertSame(['commands', 'workflows', 'output'], \array_keys($rules['keys']));
        self::assertSame(['definitions'], \array_keys($rules['keys']['workflows']['keys']));
        self::assertSame([], $defaults['workflows']['definitions']);
        self::assertSame('cli', CliModule::CONFIG_ROOT);
        self::assertSame('runtime', CliModule::KIND);
    }

    public function testNoReplayContractOrReplaySsotIsIntroduced(): void
    {
        $root = \dirname(__DIR__, 5);
        $ssot = $root . '/docs/ssot';

        self::assertDirectoryExists($ssot);

        foreach (new \DirectoryIterator($ssot) as $file) {
            if ($file->isFile()) {
                self::assertSame(0, \preg_match('/replay/i', $file->getFilename()), $file->getPathname());
            }
        }

        $artifacts = $root . '/packages/core/kernel/src/Artifacts';
        self::assertDirectoryExists($artifacts);

        $artifactFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($artifacts, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($artifactFiles as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            self::assertSame(0, \preg_match('/replay/i', $file->getFilename()), $file->getPathname());

            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $code = '';

            foreach (\token_get_all($source) as $token) {
                if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }

                $code .= \is_array($token) ? $token[1] : $token;
            }

            self::assertSame(0, \preg_match('/replay/i', $code), $file->getPathname());
        }
        self::assertDirectoryDoesNotExist($root . '/packages/platform/cli/src/Replay');
        self::assertDirectoryDoesNotExist($root . '/packages/platform/cli/src/Artifact/Replay');
        self::assertFileDoesNotExist($root . '/packages/core/contracts/src/Cli/Replay/ReplayInterface.php');

        $contracts = $root . '/packages/core/contracts/src';
        self::assertDirectoryExists($contracts);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contracts, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                self::assertSame(0, \preg_match('/replay/i', $file->getFilename()), $file->getPathname());

                $source = \file_get_contents($file->getPathname());
                self::assertIsString($source);
                $code = '';

                foreach (\token_get_all($source) as $token) {
                    if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                        continue;
                    }

                    $code .= \is_array($token) ? $token[1] : $token;
                }

                self::assertSame(0, \preg_match('/replay/i', $code), $file->getPathname());
            }
        }
    }
}
