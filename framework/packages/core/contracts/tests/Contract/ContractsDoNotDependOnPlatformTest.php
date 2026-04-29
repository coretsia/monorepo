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

use PHPUnit\Framework\TestCase;

final class ContractsDoNotDependOnPlatformTest extends TestCase
{
    public function test_contracts_source_has_no_forbidden_compile_time_dependencies(): void
    {
        $packageRoot = dirname(__DIR__, 2);
        $srcRoot = $packageRoot . '/src';

        self::assertDirectoryExists($srcRoot);

        $violations = [];

        foreach (self::phpFiles($srcRoot) as $file) {
            $contents = file_get_contents($file);

            self::assertIsString($contents);

            $code = self::phpCodeWithoutCommentsAndStrings($contents);
            $relativePath = self::relativePath($srcRoot, $file);

            foreach (self::forbiddenPatterns() as $reason => $pattern) {
                if (preg_match($pattern, $code) === 1) {
                    $violations[] = $relativePath . ': ' . $reason;
                }
            }
        }

        sort($violations, \SORT_STRING);

        self::assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $srcRoot): array
    {
        $files = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($it as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $real = realpath($path);

            if ($real === false) {
                continue;
            }

            $files[] = $real;
        }

        usort($files, static fn (string $a, string $b): int => strcmp($a, $b));

        return $files;
    }

    /**
     * @return array<string,string>
     */
    private static function forbiddenPatterns(): array
    {
        return [
            'forbidden-platform-namespace' => '~\bCoretsia\\\\Platform\\\\~',
            'forbidden-integrations-namespace' => '~\bCoretsia\\\\Integrations\\\\~',
            'forbidden-psr-http-message-namespace' => '~\bPsr\\\\Http\\\\Message\\\\~',
            'forbidden-tooling-namespace' => '~\bCoretsia\\\\Tools\\\\~',
            'forbidden-pdo-concrete' => '~(?<![A-Za-z0-9_\\\\])PDO(?![A-Za-z0-9_\\\\])~',
            'forbidden-redis-concrete' => '~(?<![A-Za-z0-9_\\\\])Redis(?![A-Za-z0-9_\\\\])~',
            'forbidden-s3-concrete' => '~\bAws\\\\S3\\\\~',
            'forbidden-prometheus-concrete' => '~\bPrometheus\\\\~',
        ];
    }

    private static function phpCodeWithoutCommentsAndStrings(string $contents): string
    {
        $tokens = token_get_all($contents);
        $out = '';

        foreach ($tokens as $token) {
            if (is_string($token)) {
                $out .= $token;

                continue;
            }

            [$type, $text] = $token;

            if (
                $type === \T_COMMENT
                || $type === \T_DOC_COMMENT
                || $type === \T_CONSTANT_ENCAPSED_STRING
                || $type === \T_ENCAPSED_AND_WHITESPACE
            ) {
                $out .= ' ';

                continue;
            }

            $out .= $text;
        }

        return $out;
    }

    private static function relativePath(string $root, string $file): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $file = str_replace('\\', '/', $file);

        if (str_starts_with($file, $root . '/')) {
            return substr($file, strlen($root) + 1);
        }

        return basename($file);
    }
}
