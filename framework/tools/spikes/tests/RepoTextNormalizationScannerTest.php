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

namespace Coretsia\Tools\Spikes\tests;

use Coretsia\Tools\Spikes\_support\RepoTextNormalizationScanner;
use PHPUnit\Framework\TestCase;

final class RepoTextNormalizationScannerTest extends TestCase
{
    public function test_passes_on_minimal_synthetic_repo_with_canonical_policy_and_eol(): void
    {
        $root = self::createTempRoot();
        try {
            self::writeCanonicalPolicyFiles($root);

            self::writeFile($root . '/src/Ok.php', "<?php\ndeclare(strict_types=1);\n\nreturn 1;\n");
            self::writeFile($root . '/scripts/ok.bat', "echo ok\r\n");
            self::writeFile($root . '/.env', "X=1\n");

            $diagnostics = RepoTextNormalizationScanner::scan($root, $root);

            self::assertSame([], $diagnostics, implode("\n", $diagnostics));
        } finally {
            self::removeDirRecursive($root);
        }
    }

    public function test_detects_missing_final_newline_for_lf_pinned_file(): void
    {
        $root = self::createTempRoot();
        try {
            self::writeCanonicalPolicyFiles($root);

            self::writeFile($root . '/src/missing_newline.php', "<?php\necho 1;"); // no trailing "\n"

            $diagnostics = RepoTextNormalizationScanner::scan($root, $root);

            self::assertContains('src/missing_newline.php: final_newline_missing', $diagnostics);
        } finally {
            self::removeDirRecursive($root);
        }
    }

    public function test_detects_cr_in_lf_required_file(): void
    {
        $root = self::createTempRoot();
        try {
            self::writeCanonicalPolicyFiles($root);

            self::writeFile($root . '/src/bad_crlf.php', "<?php\r\necho 1;\r\n");

            $diagnostics = RepoTextNormalizationScanner::scan($root, $root);

            self::assertContains('src/bad_crlf.php: eol_lf_required_found_cr', $diagnostics);
        } finally {
            self::removeDirRecursive($root);
        }
    }

    public function test_detects_lf_in_crlf_required_file(): void
    {
        $root = self::createTempRoot();
        try {
            self::writeCanonicalPolicyFiles($root);

            self::writeFile($root . '/scripts/bad_lf.bat', "echo bad\n");

            $diagnostics = RepoTextNormalizationScanner::scan($root, $root);

            self::assertContains('scripts/bad_lf.bat: eol_crlf_required_found_lf', $diagnostics);
        } finally {
            self::removeDirRecursive($root);
        }
    }

    public function test_detects_missing_policy_files(): void
    {
        $root = self::createTempRoot();
        try {
            // Intentionally do not create policy files.

            self::writeFile($root . '/src/Ok.php', "<?php\nreturn 1;\n");

            $diagnostics = RepoTextNormalizationScanner::scan($root, $root);

            self::assertContains('.editorconfig: missing_file', $diagnostics);
            self::assertContains('.gitattributes: missing_file', $diagnostics);
            self::assertContains('.gitignore: missing_file', $diagnostics);
        } finally {
            self::removeDirRecursive($root);
        }
    }

    private static function createTempRoot(): string
    {
        $base = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');
        $dir = $base . '/coretsia_repo_text_scan_' . bin2hex(random_bytes(10));

        self::mkdirp($dir);

        return $dir;
    }

    private static function mkdirp(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (@mkdir($dir, 0777, true) !== true && !is_dir($dir)) {
            throw new \RuntimeException('mkdir-failed');
        }
    }

    private static function writeFile(string $absPath, string $contents): void
    {
        self::mkdirp(dirname($absPath));

        $bytes = @file_put_contents($absPath, $contents);
        if ($bytes === false) {
            throw new \RuntimeException('write-failed');
        }
    }

    private static function writeCanonicalPolicyFiles(string $root): void
    {
        self::writeFile($root . '/.editorconfig', self::editorconfigContents());
        self::writeFile($root . '/.gitattributes', self::gitattributesContents());
        self::writeFile($root . '/.gitignore', self::gitignoreContents());
    }

    private static function editorconfigContents(): string
    {
        // Must include all "mustContain" needles from the gate policy.
        return implode("\n", [
                'root = true',
                '',
                '[*]',
                'end_of_line = lf',
                '',
                '[*.{bat,cmd}]',
                'end_of_line = crlf',
                '',
                '[*.{json,yml,yaml,neon}]',
                'max_line_length = off',
                '',
                '[*.md]',
                'trim_trailing_whitespace = false',
                '',
            ]) . "\n";
    }

    private static function gitattributesContents(): string
    {
        return implode("\n", [
                '* text=auto',
                '',
                '.editorconfig  text eol=lf',
                '.gitattributes text eol=lf',
                '.gitignore     text eol=lf',
                '',
                '*.php   text eol=lf',
                '*.phpt  text eol=lf',
                '*.phtml text eol=lf',
                '*.js    text eol=lf',
                '*.ts    text eol=lf',
                '*.tsx   text eol=lf',
                '*.css   text eol=lf',
                '*.scss  text eol=lf',
                '*.md    text eol=lf',
                '*.txt   text eol=lf',
                '*.json  text eol=lf',
                '*.yml   text eol=lf',
                '*.yaml  text eol=lf',
                '*.xml   text eol=lf',
                '*.neon  text eol=lf',
                '*.dist  text eol=lf',
                '*.env   text eol=lf',
                '.env*   text eol=lf',
                '*.env.* text eol=lf',
                '*.lock  text eol=lf',
                '*.sh    text eol=lf',
                '*.bash  text eol=lf',
                '*.zsh   text eol=lf',
                '*.ps1   text eol=lf',
                '*.svg   text eol=lf',
                '',
                '*.bat   text eol=crlf',
                '*.cmd   text eol=crlf',
                '',
                '*.png   -text',
                '*.jpg   -text',
                '*.jpeg  -text',
                '*.gif   -text',
                '*.webp  -text',
                '*.ico   -text',
                '*.pdf   -text',
                '*.zip   -text',
                '*.gz    -text',
                '*.tgz   -text',
                '*.tar   -text',
                '*.7z    -text',
                '*.phar  -text',
                '*.woff  -text',
                '*.woff2 -text',
                '*.ttf   -text',
                '*.eot   -text',
                '*.mp4   -text',
                '*.mov   -text',
                '',
                'framework/tools/**/fixtures/** -text',
                '**/tests/fixtures/** -text',
                '',
            ]) . "\n";
    }

    private static function gitignoreContents(): string
    {
        return implode("\n", [
                'framework/var/',
                '',
                '!framework/tools/spikes/fixtures/**/.env',
                '!framework/tools/spikes/fixtures/**/.env.*',
                '!framework/tools/tests/Fixtures/**/.env',
                '!framework/tools/tests/Fixtures/**/.env.*',
                '',
                '**/vendor/',
                '/skeleton/var/**/*',
                '!/skeleton/var/**/.gitkeep',
                '',
            ]) . "\n";
    }

    private static function removeDirRecursive(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($it as $item) {
            $path = $item->getPathname();

            if ($item->isDir()) {
                @rmdir($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
