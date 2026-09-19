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

namespace Coretsia\Tools\Support;

final class RepoTextNormalizationScanner
{
    private function __construct()
    {
    }

    /**
     * Scan repository policy files and EOL invariants under $scanRoot.
     *
     * Contract:
     * - returns list<string> diagnostics (unique, strcmp-sorted)
     * - diagnostics are deterministic and should be safe to pass through ConsoleOutput
     *
     * @return list<string>
     */
    public static function scan(RepositoryContext $repository, string $scanRoot): array
    {
        /** @var array<string,bool> $diagnostics */
        $diagnostics = [];

        self::withWarningsAsExceptions(static function () use ($repository, $scanRoot, &$diagnostics): void {
            $resolvedScanRoot = $repository->resolveExistingDirectory($scanRoot);

            self::validatePolicyFiles($repository, $diagnostics);
            self::validateEolInvariants($repository, $resolvedScanRoot, $diagnostics);
        });

        $out = array_keys($diagnostics);
        usort($out, static fn (string $a, string $b): int => strcmp($a, $b));

        /** @var list<string> $out */
        return $out;
    }

    /**
     * @param array<string,bool> $diagnostics set
     */
    private static function validatePolicyFiles(RepositoryContext $repository, array &$diagnostics): void
    {
        $files = [
            '.editorconfig' => $repository->resolve('.editorconfig'),
            '.gitattributes' => $repository->resolve('.gitattributes'),
            '.gitignore' => $repository->resolve('.gitignore'),
        ];

        $contents = [];

        foreach ($files as $logical => $abs) {
            if (!is_file($abs)) {
                $diagnostics[$logical . ': missing_file'] = true;
                continue;
            }
            if (!is_readable($abs)) {
                $diagnostics[$logical . ': unreadable_file'] = true;
                continue;
            }

            $bytes = DeterministicFile::readBytesExact($abs);
            $contents[$logical] = self::normalizeTextEol($bytes);

            // Enforce policy files themselves are LF + final newline (deterministic bytes hygiene).
            self::enforceLfOnlyBytes($logical, $bytes, $diagnostics);
        }

        if (isset($contents['.editorconfig'])) {
            self::validateEditorConfig($contents['.editorconfig'], $diagnostics);
        }
        if (isset($contents['.gitattributes'])) {
            self::validateGitAttributes($contents['.gitattributes'], $diagnostics);
        }
        if (isset($contents['.gitignore'])) {
            self::validateGitIgnore($contents['.gitignore'], $diagnostics);
        }
    }

    /**
     * @param array<string,bool> $diagnostics set
     */
    private static function validateEditorConfig(string $contents, array &$diagnostics): void
    {
        $mustContain = [
            'root = true',
            '[*]',
            'end_of_line = lf',
            '[*.{bat,cmd}]',
            'end_of_line = crlf',

            // Cemented by canonical policy:
            '[*.{json,yml,yaml,neon}]',
            'max_line_length = off',
            '[*.md]',
            'trim_trailing_whitespace = false',
        ];

        foreach ($mustContain as $needle) {
            if (!self::containsExactActiveLine($contents, $needle)) {
                self::addMissing($diagnostics, '.editorconfig', $needle);
            }
        }
    }

    /**
     * @param array<string,bool> $diagnostics set
     */
    private static function validateGitAttributes(string $contents, array &$diagnostics): void
    {
        // MUST have default "* text=auto" and MUST NOT enforce global eol here.
        if (preg_match('/^\*[\t ]+text=auto[\t ]*$/m', $contents) !== 1) {
            $diagnostics['.gitattributes: missing_default_text_auto'] = true;
        }

        if (preg_match('/^\*[\t ]+[^\r\n]*\beol=/m', $contents) === 1) {
            $diagnostics['.gitattributes: forbidden_global_eol'] = true;
        }

        // MUST have explicit CRLF only for Windows scripts.
        $mustContain = [
            '*.bat   text eol=crlf',
            '*.cmd   text eol=crlf',
        ];

        foreach ($mustContain as $needle) {
            if (!self::containsExactActiveLine($contents, $needle)) {
                self::addMissing($diagnostics, '.gitattributes', $needle);
            }
        }

        // MUST have explicit "-text" for binaries in the canonical set.
        $binaryMust = [
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
        ];

        foreach ($binaryMust as $needle) {
            if (!self::containsExactActiveLine($contents, $needle)) {
                self::addMissing($diagnostics, '.gitattributes', $needle);
            }
        }

        // MUST pin LF for core textual types from canonical policy.
        $lfMust = [
            '.editorconfig  text eol=lf',
            '.gitattributes text eol=lf',
            '.gitignore     text eol=lf',

            'LICENSE text eol=lf',
            'NOTICE text eol=lf',
            'packages/**/LICENSE text eol=lf',
            'packages/**/NOTICE text eol=lf',

            'coretsia text eol=lf',
            'tools/bin/coretsia text eol=lf',
            'packages/**/bin/* text eol=lf',

            '*.php   text eol=lf diff=php',
            '*.phpt  text eol=lf',
            '*.phtml text eol=lf',

            '*.js    text eol=lf',
            '*.ts    text eol=lf',
            '*.tsx   text eol=lf',
            '*.css   text eol=lf',
            '*.scss  text eol=lf',

            '*.md    text eol=lf',
            '*.txt   text eol=lf',

            '*.html  text eol=lf',
            '*.htm   text eol=lf',

            '*.json  text eol=lf',
            '*.yml   text eol=lf',
            '*.yaml  text eol=lf',
            '*.toml  text eol=lf',
            '*.xml   text eol=lf',
            '*.neon  text eol=lf',
            '*.dist  text eol=lf',
            '*.env   text eol=lf',
            '*.lock  text eol=lf',

            '*.sh    text eol=lf',
            '*.bash  text eol=lf',
            '*.zsh   text eol=lf',
            '*.ps1   text eol=lf',

            '*.svg   text eol=lf',

            '*.dot   text eol=lf',
        ];

        foreach ($lfMust as $needle) {
            if (!self::containsExactActiveLine($contents, $needle)) {
                self::addMissing($diagnostics, '.gitattributes', $needle);
            }
        }

        // Env variants that are NOT matched by "*.env".
        // Use regex to be whitespace-robust but semantically strict.
        self::requireGitattributesRule(
            contents: $contents,
            diagnostics: $diagnostics,
            canonicalNeedle: '.env* text eol=lf',
            pattern: '.env*',
            ruleRegexTail: 'text[\t ]+eol=lf\b',
        );

        self::requireGitattributesRule(
            contents: $contents,
            diagnostics: $diagnostics,
            canonicalNeedle: '*.env.* text eol=lf',
            pattern: '*.env.*',
            ruleRegexTail: 'text[\t ]+eol=lf\b',
        );

        // Fixtures MUST be byte-exact (no Git EOL normalization).
        self::requireGitattributesRule(
            contents: $contents,
            diagnostics: $diagnostics,
            canonicalNeedle: 'tools/**/fixtures/** -text',
            pattern: 'tools/**/fixtures/**',
            ruleRegexTail: '-text\b',
        );

        self::requireGitattributesRule(
            contents: $contents,
            diagnostics: $diagnostics,
            canonicalNeedle: 'tools/tests/Fixtures/** -text',
            pattern: 'tools/tests/Fixtures/**',
            ruleRegexTail: '-text\b',
        );

        self::requireGitattributesRule(
            contents: $contents,
            diagnostics: $diagnostics,
            canonicalNeedle: '**/tests/fixtures/** -text',
            pattern: '**/tests/fixtures/**',
            ruleRegexTail: '-text\b',
        );
    }

    /**
     * @param array<string,bool> $diagnostics set
     */
    private static function validateGitIgnore(string $contents, array &$diagnostics): void
    {
        $mustContain = [
            '/var/*',

            '!tools/tests/Fixtures/**/.env',
            '!tools/tests/Fixtures/**/.env.*',

            '**/vendor/',
            '/packages/applications/skeleton/var/**/*',
            '!/packages/applications/skeleton/var/**/.gitkeep',
        ];

        foreach ($mustContain as $needle) {
            if (!self::containsExactActiveLine($contents, $needle)) {
                self::addMissing($diagnostics, '.gitignore', $needle);
            }
        }
    }

    /**
     * @param array<string,bool> $diagnostics set
     */
    private static function validateEolInvariants(
        RepositoryContext $repository,
        string $scanRoot,
        array &$diagnostics,
    ): void {
        // Enforce EOL only for types pinned in .gitattributes (plus .env* variants).
        $lfExtensions = [
            'php',
            'phpt',
            'phtml',
            'js',
            'ts',
            'tsx',
            'css',
            'scss',
            'md',
            'txt',
            'html',
            'htm',
            'json',
            'yml',
            'yaml',
            'toml',
            'xml',
            'neon',
            'dist',
            'env',
            'lock',
            'sh',
            'bash',
            'zsh',
            'ps1',
            'svg',
            'dot',
        ];

        $crlfExtensions = ['bat', 'cmd'];

        $skipDirNames = [
            '.git',
            '.idea',
            '.vscode',
            '.fleet',
            '.osp',
            'vendor',
            'node_modules',
            'var',
            'tmp',
            'coverage',
            '.phpunit.cache',
            '.phpstan.cache',
            '.phpstan-cache',
            '.rector.cache',
            '.psalm',
            '.infection',
        ];

        foreach (self::iterateFiles($scanRoot, $skipDirNames) as $absPath) {
            $rel = $repository->relativeToRepo($absPath);
            $base = basename($rel);

            // Fixtures are byte-exact by policy; never EOL-normalize-check them.
            if (self::isFixturePath($rel)) {
                continue;
            }

            // Always check policy/legal files if they are under scanRoot.
            if (
                $base === '.editorconfig'
                || $base === '.gitattributes'
                || $base === '.gitignore'
                || $base === 'LICENSE'
                || $base === 'NOTICE'
            ) {
                $bytes = DeterministicFile::readBytesExact($absPath);
                self::enforceLfOnlyBytes($rel, $bytes, $diagnostics);
                continue;
            }

            // Extensionless `coretsia` launchers pinned explicitly in .gitattributes.
            if ($base === 'coretsia') {
                $bytes = DeterministicFile::readBytesExact($absPath);
                self::enforceLfOnlyBytes($rel, $bytes, $diagnostics);
                continue;
            }

            $ext = pathinfo($base, PATHINFO_EXTENSION);

            if (in_array($ext, $crlfExtensions, true)) {
                $bytes = DeterministicFile::readBytesExact($absPath);
                self::enforceCrlfOnlyBytes($rel, $bytes, $diagnostics);
                continue;
            }

            // ".env*" should be LF-only in working tree by policy (even if not matched by "*.env").
            // ALSO: "*.env.*" is LF-only by policy (e.g. "foo.env.example").
            if (str_starts_with($base, '.env') || str_contains($base, '.env.')) {
                $bytes = DeterministicFile::readBytesExact($absPath);
                self::enforceLfOnlyBytes($rel, $bytes, $diagnostics);
                continue;
            }

            if (self::isPackageBinPath($rel)) {
                $bytes = DeterministicFile::readBytesExact($absPath);
                self::enforceLfOnlyBytes($rel, $bytes, $diagnostics);
                continue;
            }

            if ($ext === '') {
                continue;
            }

            if (in_array($ext, $lfExtensions, true)) {
                $bytes = DeterministicFile::readBytesExact($absPath);
                self::enforceLfOnlyBytes($rel, $bytes, $diagnostics);
            }
        }
    }

    /**
     * @param array<string,bool> $diagnostics set
     */
    private static function enforceLfOnlyBytes(string $relOrLogical, string $bytes, array &$diagnostics): void
    {
        $path = self::normalizeRelativePath($relOrLogical);

        if (str_contains($bytes, "\r")) {
            $diagnostics[$path . ': eol_lf_required_found_cr'] = true;
            return;
        }

        if ($bytes !== '' && !str_ends_with($bytes, "\n")) {
            $diagnostics[$path . ': final_newline_missing'] = true;
        }
    }

    /**
     * @param array<string,bool> $diagnostics set
     */
    private static function enforceCrlfOnlyBytes(string $rel, string $bytes, array &$diagnostics): void
    {
        $path = self::normalizeRelativePath($rel);

        // Reject any "\n" not preceded by "\r".
        if (preg_match('/(?<!\r)\n/', $bytes) === 1) {
            $diagnostics[$path . ': eol_crlf_required_found_lf'] = true;
            return;
        }

        // Reject any "\r" not followed by "\n".
        if (preg_match("/\r(?!\n)/", $bytes) === 1) {
            $diagnostics[$path . ': eol_crlf_required_found_cr'] = true;
            return;
        }

        if ($bytes !== '' && !str_ends_with($bytes, "\n")) {
            $diagnostics[$path . ': final_newline_missing'] = true;
        }
    }

    /**
     * Add a deterministic "missing" diagnostic that is safe for ConsoleOutput.
     *
     * Contract:
     * - MUST NOT contain "=" or "\" or "://"
     * - MUST be single-line ASCII
     *
     * @param array<string,bool> $diagnostics set
     */
    private static function addMissing(array &$diagnostics, string $logicalFile, string $needle): void
    {
        $diagnostics[self::missingKey($logicalFile, $needle)] = true;
    }

    private static function missingKey(string $logicalFile, string $needle): string
    {
        return $logicalFile . ': missing:' . self::normalizeNeedleForDiagnostic($needle);
    }

    /**
     * Normalize a policy "needle" into a safe diagnostic token:
     * - collapse whitespace
     * - replace "=" with ":" (and normalize ":" spacing)
     * - replace "\" with "/" (defensive)
     * - replace "://" with ":/ /" (defensive)
     * - if non-ASCII printable -> fall back to sha1 hex token
     */
    private static function normalizeNeedleForDiagnostic(string $needle): string
    {
        // Single-line normalization first (defensive).
        $needle = str_replace(["\r\n", "\r", "\n"], ' ', $needle);
        $needle = trim($needle);

        if ($needle === '') {
            return 'empty';
        }

        if (str_starts_with($needle, '!/')) {
            $needle = '!repo-root/' . substr($needle, 2);
        } elseif (str_starts_with($needle, '/')) {
            $needle = 'repo-root/' . substr($needle, 1);
        }

        // Collapse whitespace (canonical form).
        $needle = (string) preg_replace('/\s+/', ' ', $needle);

        // Hard bans in ConsoleOutput: "=", "\" and "://"
        $needle = str_replace('://', ':/ /', $needle);
        $needle = str_replace('\\', '/', $needle);
        $needle = str_replace('=', ':', $needle);

        // Normalize ":" spacing after replacement: "a : b" => "a:b"
        $needle = (string) preg_replace('/\s*:\s*/', ':', $needle);
        $needle = trim($needle);

        // Ensure printable ASCII (ConsoleOutput policy).
        if (preg_match('/^[\x20-\x7E]+$/', $needle) !== 1) {
            return 'sha1:' . sha1($needle);
        }

        // Final guarantee: never emit "=".
        $token = $needle;
        if (str_contains($token, '=')) {
            return 'sha1:' . sha1($token);
        }

        return $token;
    }

    private static function containsExactActiveLine(string $contents, string $needle): bool
    {
        return preg_match(
            '~^[\t ]*' . preg_quote($needle, '~') . '[\t ]*$~m',
            $contents,
        ) === 1;
    }

    private static function normalizeTextEol(string $bytes): string
    {
        return str_replace(["\r\n", "\r"], "\n", $bytes);
    }

    /**
     * @return iterable<string> absPath list
     */
    private static function iterateFiles(string $scanRoot, array $skipDirNames): iterable
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator(
                    $scanRoot,
                    \FilesystemIterator::SKIP_DOTS,
                ),
                static function (\SplFileInfo $current) use ($skipDirNames): bool {
                    // Skip symlinks entirely (avoid scanning outside repo and platform-specific link semantics).
                    if ($current->isLink()) {
                        return false;
                    }

                    if ($current->isDir()) {
                        $base = $current->getFilename();
                        if (in_array($base, $skipDirNames, true)) {
                            return false;
                        }
                        return true;
                    }

                    // Skip obvious binary types (we don't EOL-check those).
                    $ext = pathinfo($current->getFilename(), PATHINFO_EXTENSION);
                    if (in_array($ext, [
                        'png',
                        'jpg',
                        'jpeg',
                        'gif',
                        'webp',
                        'ico',
                        'pdf',
                        'zip',
                        'gz',
                        'tgz',
                        'tar',
                        '7z',
                        'phar',
                        'woff',
                        'woff2',
                        'ttf',
                        'eot',
                        'mp4',
                        'mov',
                    ], true)) {
                        return false;
                    }

                    return true;
                },
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($it as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            if ($file->isLink()) {
                continue;
            }

            $abs = $file->getPathname();
            $absReal = realpath($abs);
            if ($absReal === false) {
                // Deterministic fail: treat as scan error, but do not leak paths here.
                throw new \RuntimeException('scan_failed');
            }

            yield $absReal;
        }
    }

    private static function isFixturePath(string $rel): bool
    {
        $normalized = trim(str_replace('\\', '/', $rel), '/');
        $withBoundaries = '/' . $normalized . '/';

        if (
            str_starts_with($normalized, 'tools/')
            && str_contains($withBoundaries, '/fixtures/')
        ) {
            return true;
        }

        if (str_starts_with($normalized, 'tools/tests/Fixtures/')) {
            return true;
        }

        return str_contains($withBoundaries, '/tests/fixtures/');
    }

    private static function isPackageBinPath(string $rel): bool
    {
        $normalized = trim(str_replace('\\', '/', $rel), '/');

        return preg_match(
            '~\Apackages/(?:[^/]+/)*bin/[^/]+\z~',
            $normalized,
        ) === 1;
    }

    private static function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);

        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                if ($out === []) {
                    throw new \RuntimeException('scan_failed');
                }

                array_pop($out);
                continue;
            }
            $out[] = $p;
        }

        return implode('/', $out);
    }

    private static function withWarningsAsExceptions(callable $fn): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \RuntimeException('php_warning');
        });

        try {
            $fn();
        } finally {
            // CRITICAL: always pop our handler.
            restore_error_handler();
        }
    }

    /**
     * Require a .gitattributes rule line by semantic match:
     * - pattern at line start
     * - whitespace-robust
     * - rule tail regex (e.g. "text[\t ]+eol=lf\b" or "-text\b")
     *
     * @param array<string,bool> $diagnostics set
     */
    private static function requireGitattributesRule(
        string $contents,
        array &$diagnostics,
        string $canonicalNeedle,
        string $pattern,
        string $ruleRegexTail,
    ): void {
        $re = '~^[\t ]*' . preg_quote($pattern, '~') . '[\t ]+' . $ruleRegexTail . '[\t ]*$~m';

        if (preg_match($re, $contents) !== 1) {
            self::addMissing($diagnostics, '.gitattributes', $canonicalNeedle);
        }
    }
}
