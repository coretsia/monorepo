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

namespace Coretsia\Contracts\Tests\Contract;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CoreContractsDoNotTrimInputContractTest extends TestCase
{
    public function testCoreContractsSourceDoesNotTrimBoundaryInput(): void
    {
        $packageRoot = dirname(__DIR__, 2);
        $violations = [];

        foreach (self::phpFiles($packageRoot . '/src') as $file) {
            foreach (self::trimCallLines($file) as $line) {
                $violations[] = self::relativePath($file, $packageRoot) . ':' . $line . ' uses trim()';
            }
        }

        sort($violations, SORT_STRING);

        self::assertSame(
            [],
            $violations,
            "core/contracts src must not trim contracts boundary input.\n"
            . "Normalize in runtime/adapters before constructing contracts values, or document field-specific trimming explicitly in SSoT.\n"
            . "Violations:\n"
            . implode("\n", $violations)
        );
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $root): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return list<int>
     */
    private static function trimCallLines(string $file): array
    {
        $source = (string)file_get_contents($file);
        $tokens = token_get_all($source);
        $lines = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] !== T_STRING) {
                continue;
            }

            if (strtolower($token[1]) !== 'trim') {
                continue;
            }

            if (!self::isFunctionCall($tokens, $index)) {
                continue;
            }

            $lines[] = $token[2];
        }

        $lines = array_values(array_unique($lines));
        sort($lines, SORT_NUMERIC);

        return $lines;
    }

    /**
     * @param array<int,array{0:int,1:string,2:int}|string> $tokens
     */
    private static function isFunctionCall(array $tokens, int $index): bool
    {
        $next = self::nextSignificantToken($tokens, $index);

        if ($next !== '(') {
            return false;
        }

        $previous = self::previousSignificantToken($tokens, $index);

        return $previous !== T_FUNCTION
            && $previous !== T_OBJECT_OPERATOR
            && $previous !== T_NULLSAFE_OBJECT_OPERATOR
            && $previous !== T_DOUBLE_COLON;
    }

    /**
     * @param array<int,array{0:int,1:string,2:int}|string> $tokens
     * @param int $index
     * @return int|string|null
     */
    private static function nextSignificantToken(array $tokens, int $index): int|string|null
    {
        for ($i = $index + 1, $count = count($tokens); $i < $count; ++$i) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    /**
     * @param array<int,array{0:int,1:string,2:int}|string> $tokens
     * @param int $index
     * @return int|string|null
     */
    private static function previousSignificantToken(array $tokens, int $index): int|string|null
    {
        for ($i = $index - 1; $i >= 0; --$i) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    private static function relativePath(string $path, string $root): string
    {
        $path = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', $root);

        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        return basename($path);
    }
}
