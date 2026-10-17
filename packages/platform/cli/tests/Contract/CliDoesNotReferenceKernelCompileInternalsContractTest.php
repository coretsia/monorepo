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

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use PHPUnit\Framework\TestCase;

final class CliDoesNotReferenceKernelCompileInternalsContractTest extends TestCase
{
    private const array FORBIDDEN_INTERNALS = [
        'ModulePlanResolver',
        'ModuleResolution',
        'ContainerProviderPlan',
        'ContainerProviderPlanResolver',
        'ManifestReaderInterface',
        'ComposerManifestReader',
        'ArtifactCompiler',
        'FingerprintCalculator',
        'CacheVerifier',
        'ArtifactGenerationLocator',
        'ArtifactGenerationPublisher',
        'ArtifactGenerationValidator',
        'ArtifactPathResolver',
        'PhpArtifactReader',
        'ArtifactRuntimeBooter',
        'ArtifactRuntimeInput',
    ];

    public function testCliSourceCannotImportOrReferenceKernelCompileInternals(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $checked = 0;
        $sawKernelOpsPort = false;
        $sawHostInput = false;
        $sawHostBooter = false;

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$checked;
            $path = $file->getPathname();
            $relative = \str_replace('\\', '/', \substr($path, \strlen($root) + 1));
            $code = self::codeWithoutComments($path);
            foreach (self::FORBIDDEN_INTERNALS as $name) {
                self::assertSame(
                    0,
                    \preg_match('~(?<![A-Za-z0-9_])' . \preg_quote($name, '~') . '(?![A-Za-z0-9_])~', $code),
                    $relative . ': ' . $name,
                );
            }
            self::assertStringNotContainsString('Coretsia\\Kernel\\Ops\\KernelOpsFacade', $code, $relative);
            if (\str_contains($code, KernelOpsInterface::class)) {
                $sawKernelOpsPort = true;
            }

            $allowedHost = $relative === 'Bootstrap/CliHostBootstrap.php';
            foreach (['KernelOpsHostInput', 'KernelOpsHostBooter'] as $name) {
                if (\str_contains($code, $name)) {
                    self::assertTrue($allowedHost, $relative . ': ' . $name);
                    if ($name === 'KernelOpsHostInput') {
                        $sawHostInput = true;
                    } else {
                        $sawHostBooter = true;
                    }
                }
            }
            if (!$allowedHost) {
                self::assertSame(
                    0,
                    \preg_match('~Coretsia\\\\Kernel\\\\Ops(?:\\\\|\b)~', $code),
                    $relative . ': concrete Kernel Ops dependency',
                );
            } else {
                self::assertSame(
                    0,
                    \preg_match('~Coretsia\\\\Kernel\\\\Ops(?!\\\\KernelOpsHost(?:Input|Booter)\b(?!\\\\))~', $code),
                    $relative . ': unexpected Kernel Ops type',
                );
            }
            // CLI may present safe Kernel-owned OpsResult generation metadata,
            // but must never open Kernel artifact/manifest/config files itself.
            foreach (
                [
                    'file_get_contents',
                    'readfile',
                    'parse_ini_file',
                    'scandir',
                    'glob',
                    'fopen',
                    'SplFileObject',
                    'DirectoryIterator',
                    'opendir',
                    'RecursiveDirectoryIterator',
                    'ArtifactPathResolver',
                ] as $forbiddenIo
            ) {
                self::assertSame(
                    0,
                    \preg_match('~(?<![A-Za-z0-9_])' . \preg_quote($forbiddenIo, '~') . '(?![A-Za-z0-9_])~', $code),
                    $relative . ': ' . $forbiddenIo,
                );
            }
            self::assertSame(
                0,
                \preg_match(
                    '~[\x27\x22](?:[^\x27\x22]*[/\\\\])?(?:current|config\.php|container\.php|manifest\.php)[\x27\x22]~',
                    $code,
                ),
                $relative,
            );
        }
        self::assertGreaterThan(0, $checked);
        self::assertTrue($sawKernelOpsPort);
        self::assertTrue($sawHostInput);
        self::assertTrue($sawHostBooter);
    }

    private static function codeWithoutComments(string $path): string
    {
        $source = \file_get_contents($path);
        self::assertIsString($source);
        $code = '';
        foreach (\token_get_all($source) as $token) {
            if (\is_string($token)) {
                $code .= $token;
            } elseif (!\in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= $token[1];
            }
        }
        return $code;
    }
}
