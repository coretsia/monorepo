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

namespace Coretsia\Devtools\InternalToolkit\Tests\Unit;

use Coretsia\Devtools\InternalToolkit\Path;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathNormalizeRelativeGoldenVectorsTest extends TestCase
{
    #[DataProvider('provideGoldenVectors')]
    public function testNormalizeRelativeGoldenVectors(string $absOrRelPath, string $repoRoot, string $expected): void
    {
        self::assertSame($expected, Path::normalizeRelative($absOrRelPath, $repoRoot));
    }

    public function testNormalizeRelativeRejectsOutsideRepoRoot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CORETSIA_INTERNAL_TOOLKIT_PATH_OUTSIDE_REPO_ROOT');

        Path::normalizeRelative('../secret', '/repo');
    }

    /**
     * @return array<string, array{0:string, 1:string, 2:string}>
     */
    public static function provideGoldenVectors(): array
    {
        $vectors = [
            'windows-separators a\\b\\c' => ['a\\b\\c', '/repo', 'a/b/c'],
            'mixed-separators a/b\\c' => ['a/b\\c', '/repo', 'a/b/c'],
            'redundant ./a/b' => ['./a/b', '/repo', 'a/b'],
            'redundant a//b' => ['a//b', '/repo', 'a/b'],
            'drive-letter C:\\repo\\framework\\tools\\spikes' => ['C:\\repo\\framework\\tools\\spikes', 'C:\\repo', 'framework/tools/spikes'],
        ];

        // Windows-only: MSYS/MinGW absolute forms + case-insensitive containment.
        if (\PHP_OS_FAMILY === 'Windows') {
            $vectors['msys-drive /c/repo/... under win repoRoot'] = ['/c/repo/framework/tools/spikes', 'C:\\repo', 'framework/tools/spikes'];
            $vectors['msys-repoRoot /c/repo with win abs path'] = ['C:\\repo\\framework\\tools\\spikes', '/c/repo', 'framework/tools/spikes'];

            // Case-insensitive containment (Windows FS semantics).
            $vectors['windows-case-insensitive root vs abs'] = ['c:\\REPO\\Framework\\Tools', 'C:\\repo', 'Framework/Tools'];
        }

        return $vectors;
    }
}
