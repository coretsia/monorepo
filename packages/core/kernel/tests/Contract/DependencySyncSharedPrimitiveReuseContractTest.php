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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLock;
use Coretsia\Kernel\DependencySync\Composer\ComposerManifestStore;
use Coretsia\Kernel\DependencySync\Composer\ComposerSyncCoordinator;
use Coretsia\Kernel\DependencySync\Verification\DependencySyncVerificationCodec;
use PHPUnit\Framework\TestCase;

final class DependencySyncSharedPrimitiveReuseContractTest extends TestCase
{
    public function testCoordinatorAndArtifactLockReuseFoundationScopedLockWithoutDuplicateMechanics(): void
    {
        foreach (
            [ComposerSyncCoordinator::class, ArtifactGenerationLock::class] as $class
        ) {
            $source = self::source($class);

            self::assertStringContainsString(
                'use Coretsia\\Foundation\\Filesystem\\ScopedFileLock;',
                $source,
            );

            foreach (
                ['fopen(', 'flock(', 'mkdir(', 'chmod(', 'openMode('] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }

        $kernelRoot = \dirname(__DIR__, 2);
        $files = [
            $kernelRoot . '/bin/dependency-sync-verify.php',
        ];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $kernelRoot . '/src/DependencySync',
                \FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        \sort($files, \SORT_STRING);

        foreach ($files as $file) {
            $source = \file_get_contents($file);

            self::assertIsString($source);
            self::assertStringNotContainsString(
                'Coretsia\\Kernel\\Artifacts\\Generation\\ArtifactGenerationLock',
                $source,
                $file,
            );
        }
    }

    public function testVerificationCodecReusesStableDecoderWhileManifestStoreKeepsStrictComposerJsonDecoder(): void
    {
        $codec = self::source(DependencySyncVerificationCodec::class);

        self::assertStringContainsString('StableJsonDecoder', $codec);
        self::assertStringNotContainsString('json_decode(', $codec);
        self::assertStringNotContainsString('StableJsonEncoder', $codec);

        $manifestStore = self::source(ComposerManifestStore::class);

        self::assertStringContainsString('json_decode(', $manifestStore);
        self::assertStringNotContainsString(
            'StableJsonDecoder',
            $manifestStore,
        );
    }

    private static function source(string $class): string
    {
        $path = new \ReflectionClass($class)->getFileName();

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }
}
