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

use Coretsia\Foundation\Filesystem\ScopedFileLock;
use PHPUnit\Framework\TestCase;

final class ArtifactLocalFileOpenModeContractTest extends TestCase
{
    public function testGenerationLockUsesPlatformAwareCloseOnExecMode(): void
    {
        $generationLockSource = self::source('src/Artifacts/Generation/ArtifactGenerationLock.php');

        self::assertStringContainsString(
            'private ScopedFileLock $fileLock',
            $generationLockSource,
        );
        self::assertStringContainsString(
            '$this->fileLock->exclusive(',
            $generationLockSource,
        );
        self::assertStringContainsString(
            '$this->fileLock->shared(',
            $generationLockSource,
        );

        $reflection = new \ReflectionClass(ScopedFileLock::class);
        $path = $reflection->getFileName();

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);
        self::assertStringContainsString('self::openMode()', $source);
        self::assertStringContainsString("? 'c+b'", $source);
        self::assertStringContainsString(": 'c+be'", $source);
        self::assertStringNotContainsString("fopen(\$lockPath, 'c+b')", $source);
    }

    public function testArtifactWriterUsesPlatformAwareCloseOnExecMode(): void
    {
        $source = self::source('src/Artifacts/ArtifactWriter.php');

        self::assertStringContainsString('self::exclusiveCreateMode()', $source);
        self::assertStringContainsString("? 'xb'", $source);
        self::assertStringContainsString(": 'xbe'", $source);
        self::assertStringNotContainsString("fopen(\$targetPath, 'xb')", $source);
    }

    private static function source(string $relativePath): string
    {
        $bytes = \file_get_contents(
            \dirname(__DIR__, 2) . '/' . \ltrim($relativePath, '/'),
        );

        self::assertIsString($bytes);

        return $bytes;
    }
}
