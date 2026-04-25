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

namespace Coretsia\Tools\Spikes\_support;

/**
 * DeterminismRunner (Phase 0 rails):
 * - Runs `composer spike:test` twice (rerun-no-diff policy).
 * - Enforces git worktree cleanliness before/after/between runs.
 * - Provides deterministic failure codes and stable reason tokens.
 *
 * Output policy (cemented):
 * - success: prints nothing, exit 0
 * - failure: prints via ConsoleOutput only:
 *   line1: CODE
 *   line2+: stable reason tokens (no absolute paths, no captured output)
 *   exit 1
 *
 * NOTE:
 * - No stdout/stderr dump persistence is performed. Failure analysis relies only on safe reason tokens.
 */
final class DeterminismRunner
{
    private const string ENV_SPIKES_TMP = 'CORETSIA_SPIKES_TMP';

    private const string REASON_GIT_REQUIRED = 'git-required';
    private const string REASON_WORKTREE_DIRTY = 'worktree-dirty';
    private const string REASON_RUN1_NONZERO = 'run1-nonzero';
    private const string REASON_RUN2_NONZERO = 'run2-nonzero';
    private const string REASON_RUNNER_ERROR = 'runner-error';

    // Optional extra reason tokens (stable allowlist).
    private const string REASON_COMPOSER_NOT_FOUND = 'composer-not-found';
    private const string REASON_PHPUNIT_FAILED = 'phpunit-failed';
    private const string REASON_PHP_FATAL = 'php-fatal';

    // Extra safe classifiers (no output echo).
    private const string REASON_PAYLOAD_EMPTY = 'payload-empty';
    private const string REASON_AUTOLOAD_MISSING = 'autoload-missing';
    private const string REASON_PHPUNIT_MISSING = 'phpunit-missing';
    private const string REASON_PHP_EXT_MISSING = 'php-ext-missing';
    private const string REASON_PERMISSION_DENIED = 'permission-denied';
    private const string REASON_PATH_TOO_LONG = 'path-too-long';
    private const string REASON_MEMORY_LIMIT = 'memory-limit';
    private const string REASON_PHP_INPUT_MISSING = 'php-input-missing';

    // Extra PHP-level classifiers (stable allowlist).
    private const string REASON_PHP_WARNING = 'php-warning';
    private const string REASON_PHP_NOTICE = 'php-notice';
    private const string REASON_PHP_DEPRECATED = 'php-deprecated';
    private const string REASON_PHP_PARSE_ERROR = 'php-parse-error';
    private const string REASON_PHP_UNCAUGHT = 'php-uncaught';

    // Composer plugin policy / allow-plugins.
    private const string REASON_COMPOSER_PLUGIN_BLOCKED = 'composer-plugin-blocked';

    private static ?string $repoRoot = null;
    private static ?string $frameworkRoot = null;

    /**
     * @internal tests only
     */
    private static ?ProcessRunnerInterface $processRunner = null;

    private function __construct()
    {
    }

    /**
     * @internal
     *
     * Test hook: inject a process runner to avoid git/composer side effects.
     */
    public static function setProcessRunner(?ProcessRunnerInterface $runner): void
    {
        self::$processRunner = $runner;
    }

    public static function main(): int
    {
        // Local tools-only includes (CWD-independent).
        require_once __DIR__ . '/ConsoleOutput.php';
        require_once __DIR__ . '/ErrorCodes.php';
        require_once __DIR__ . '/RunnerFailure.php';
        require_once __DIR__ . '/ProcessResult.php';
        require_once __DIR__ . '/ProcessRunnerInterface.php';
        require_once __DIR__ . '/NativeProcessRunner.php';

        try {
            self::run();

            return 0;
        } catch (RunnerFailure $failure) {
            self::emitFailure($failure->code(), $failure->reasons());

            return 1;
        } catch (\Throwable) {
            self::emitFailure(
                ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                [self::REASON_RUNNER_ERROR],
            );

            return 1;
        }
    }

    private static function runner(): ProcessRunnerInterface
    {
        if (self::$processRunner !== null) {
            return self::$processRunner;
        }

        return new NativeProcessRunner();
    }

    private static function run(): void
    {
        $repoRoot = self::repoRoot();

        // 1) Require git + clean worktree before run #1.
        self::assertGitAvailable($repoRoot);
        self::assertWorktreeClean($repoRoot);

        // 2) Run #1 with fresh temp root, then cleanup, then re-check worktree clean.
        $tmp1 = self::createFreshTmpRootOutsideRepo($repoRoot);
        $exit1 = null;
        $extra1 = [];

        try {
            $r1 = self::runComposerSpikeTest($repoRoot, $tmp1);
            $exit1 = $r1['exit'];
            $extra1 = $r1['reasons'];
        } finally {
            if ($exit1 === null) {
                self::tryRemoveDirRecursive($tmp1);
            } else {
                self::removeDirRecursive($tmp1);
            }
        }

        if ($exit1 !== 0) {
            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                array_values(array_merge([self::REASON_RUN1_NONZERO], $extra1)),
            );
        }

        self::assertWorktreeClean($repoRoot);

        // 3) Run #2 with fresh temp root, then cleanup, then re-check worktree clean.
        $tmp2 = self::createFreshTmpRootOutsideRepo($repoRoot);
        $exit2 = null;
        $extra2 = [];

        try {
            $r2 = self::runComposerSpikeTest($repoRoot, $tmp2);
            $exit2 = $r2['exit'];
            $extra2 = $r2['reasons'];
        } finally {
            if ($exit2 === null) {
                self::tryRemoveDirRecursive($tmp2);
            } else {
                self::removeDirRecursive($tmp2);
            }
        }

        if ($exit2 !== 0) {
            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                array_values(array_merge([self::REASON_RUN2_NONZERO], $extra2)),
            );
        }

        self::assertWorktreeClean($repoRoot);
    }

    /**
     * @param list<string> $reasons
     */
    private static function emitFailure(string $code, array $reasons): void
    {
        ConsoleOutput::line($code);

        foreach ($reasons as $reason) {
            ConsoleOutput::line($reason);
        }
    }

    private static function frameworkRoot(): string
    {
        if (self::$frameworkRoot !== null) {
            return self::$frameworkRoot;
        }

        $frameworkRoot = realpath(__DIR__ . '/../../..');
        if ($frameworkRoot === false) {
            throw new \RuntimeException('framework-root-missing');
        }

        self::$frameworkRoot = $frameworkRoot;

        return self::$frameworkRoot;
    }

    private static function repoRoot(): string
    {
        if (self::$repoRoot !== null) {
            return self::$repoRoot;
        }

        $frameworkRoot = self::frameworkRoot();

        $repoRoot = realpath($frameworkRoot . '/..');
        if ($repoRoot === false) {
            throw new \RuntimeException('repo-root-missing');
        }

        self::$repoRoot = $repoRoot;

        return $repoRoot;
    }

    private static function assertGitAvailable(string $repoRoot): void
    {
        $r = self::runCommand(
            command: 'git status --porcelain',
            cwd: $repoRoot,
            env: null,
            captureStdout: true,
            captureStderr: true,
        );

        if ($r->exitCode !== 0) {
            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_GIT_REQUIRED,
                [self::REASON_GIT_REQUIRED],
            );
        }
    }

    private static function assertWorktreeClean(string $repoRoot): void
    {
        $r = self::runCommand(
            command: 'git status --porcelain',
            cwd: $repoRoot,
            env: null,
            captureStdout: true,
            captureStderr: false,
        );

        if ($r->exitCode !== 0) {
            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_GIT_REQUIRED,
                [self::REASON_GIT_REQUIRED],
            );
        }

        if (trim($r->stdout) !== '') {
            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_WORKTREE_DIRTY,
                [self::REASON_WORKTREE_DIRTY],
            );
        }
    }

    /**
     * @return array{exit:int,reasons:list<string>}
     */
    private static function runComposerSpikeTest(string $repoRoot, string $tmpRoot): array
    {
        $baseEnv = getenv();
        if (!is_array($baseEnv)) {
            $baseEnv = [];
        }

        // IMPORTANT (Windows):
        // - tmpRoot is canonicalized to "/" separators for internal comparisons.
        // - Composer + Windows tooling are more stable with native "\" separators.
        $tmpRootNative = $tmpRoot;
        if (\PHP_OS_FAMILY === 'Windows') {
            $tmpRootNative = str_replace('/', '\\', $tmpRootNative);
        }

        $env = $baseEnv;
        $env[self::ENV_SPIKES_TMP] = $tmpRootNative;

        // Hardening: forbid interactive prompts from Composer scripts.
        $env['COMPOSER_NO_INTERACTION'] = '1';

        // Isolation: avoid any machine/user-specific Composer state.
        $env['COMPOSER_NO_AUDIT'] = '1';

        $sep = DIRECTORY_SEPARATOR;

        $env['COMPOSER_HOME'] = rtrim($tmpRootNative, "\\/") . $sep . 'composer_home';
        $env['COMPOSER_CACHE_DIR'] = rtrim($tmpRootNative, "\\/") . $sep . 'composer_cache';

        // Prepare dirs deterministically; if cannot, fail early (do not delegate to Composer).
        if (!is_dir((string)$env['COMPOSER_HOME'])) {
            if (@mkdir((string)$env['COMPOSER_HOME'], 0777, true) !== true) {
                throw RunnerFailure::with(
                    ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                    [self::REASON_RUNNER_ERROR],
                );
            }
        }

        if (!is_dir((string)$env['COMPOSER_CACHE_DIR'])) {
            if (@mkdir((string)$env['COMPOSER_CACHE_DIR'], 0777, true) !== true) {
                throw RunnerFailure::with(
                    ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                    [self::REASON_RUNNER_ERROR],
                );
            }
        }

        $runner = self::runner();

        // Tests inject their own runner; keep the hook semantics stable.
        if (self::$processRunner !== null) {
            $exit = $runner->runSilenced('composer --no-interaction spike:test', $repoRoot, $env);
            if ($exit === null) {
                throw RunnerFailure::with(
                    ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                    [self::REASON_RUNNER_ERROR],
                );
            }

            return ['exit' => (int)$exit, 'reasons' => []];
        }

        // Execute from framework root (no nested --working-dir boundary).
        $frameworkRoot = self::frameworkRoot();

        $r = $runner->run(
            command: 'composer --no-interaction spike:test',
            cwd: $frameworkRoot,
            env: $env,
            captureStdout: true,
            captureStderr: true,
        );

        $extra = [];
        if ($r->exitCode !== 0) {
            // Capture classifier reasons (safe tokens).
            $extra = self::classifyComposerFailure($r);
        }

        return ['exit' => (int)$r->exitCode, 'reasons' => self::dedupPreserveOrder($extra)];
    }

    /**
     * @return list<string> stable + safe reason tokens only
     */
    private static function classifyComposerFailure(ProcessResult $r): array
    {
        $payload = $r->stdout . "\n" . $r->stderr;

        $reasons = [];

        // Always add minimal safe meta for debugging.
        $reasons[] = 'exit-' . (string)$r->exitCode;
        if (is_string($r->runnerTag) && $r->runnerTag !== '') {
            $reasons[] = 'shell-' . $r->runnerTag;
        }

        if (trim($payload) === '') {
            $reasons[] = self::REASON_PAYLOAD_EMPTY;
        } else {
            $reasons[] = 'payload-nonempty';
        }

        if (trim($r->stdout) !== '') {
            $reasons[] = 'payload-has-stdout';
        }

        if (trim($r->stderr) !== '') {
            $reasons[] = 'payload-has-stderr';
        }

        // Heuristic (stable): command-not-found sentinel codes on Windows shells.
        if (\PHP_OS_FAMILY === 'Windows') {
            if ($r->exitCode === 127 || $r->exitCode === 9009) {
                $reasons[] = self::REASON_COMPOSER_NOT_FOUND;
            }
        }

        // Extract any CORETSIA_* tokens (safe by shape).
        $codes = self::extractCoretsiaCodes($payload);
        foreach ($codes as $code) {
            $reasons[] = 'inner-' . $code;
        }

        // Safe classifiers (no raw output):
        $pl = strtolower($payload);

        if (
            str_contains($pl, 'a required privilege is not held by the client')
            || str_contains($pl, 'secreatesymboliclinkprivilege')
            || str_contains($pl, 'symbolic link')
            || str_contains($pl, 'symlink')
            || str_contains($pl, 'operation not permitted')
        ) {
            $reasons[] = 'symlink-privilege';
        }

        if (
            str_contains($pl, 'being used by another process')
            || str_contains($pl, 'the process cannot access the file')
            || str_contains($pl, 'process cannot access the file')
        ) {
            $reasons[] = 'file-locked';
        }

        if (str_contains($pl, 'could not open input file')) {
            $reasons[] = self::REASON_PHP_INPUT_MISSING;

            if (str_contains($pl, 'vendor/bin/phpunit')) {
                $reasons[] = self::REASON_PHPUNIT_MISSING;
            }
        }

        if (
            str_contains($pl, 'vendor/autoload.php')
            && (str_contains($pl, 'failed to open stream') || str_contains($pl, 'no such file or directory'))
        ) {
            $reasons[] = self::REASON_AUTOLOAD_MISSING;
        }

        if (
            (str_contains($pl, 'phpunit') && str_contains($pl, 'not found'))
            || (str_contains($pl, 'class') && str_contains($pl, 'phpunit') && str_contains($pl, 'not found'))
        ) {
            $reasons[] = self::REASON_PHPUNIT_MISSING;
        }

        if (preg_match('~requires\s+ext-[a-z0-9_]+~i', $payload) === 1) {
            $reasons[] = self::REASON_PHP_EXT_MISSING;
        }

        if (str_contains($pl, 'permission denied') || str_contains($pl, 'access is denied')) {
            $reasons[] = self::REASON_PERMISSION_DENIED;
        }

        if (
            str_contains($pl, 'the filename or extension is too long')
            || str_contains($pl, 'file name too long')
            || str_contains($pl, 'filename too long')
        ) {
            $reasons[] = self::REASON_PATH_TOO_LONG;
        }

        if (str_contains($pl, 'allowed memory size') && str_contains($pl, 'exhausted')) {
            $reasons[] = self::REASON_MEMORY_LIMIT;
        }

        if (stripos($payload, 'PHP Warning:') !== false) {
            $reasons[] = self::REASON_PHP_WARNING;
        }

        if (stripos($payload, 'PHP Notice:') !== false) {
            $reasons[] = self::REASON_PHP_NOTICE;
        }

        if (stripos($payload, 'PHP Deprecated:') !== false || stripos($payload, 'Deprecated:') !== false) {
            $reasons[] = self::REASON_PHP_DEPRECATED;
        }

        if (stripos($payload, 'Parse error:') !== false) {
            $reasons[] = self::REASON_PHP_PARSE_ERROR;
        }

        if (
            stripos($payload, 'Uncaught') !== false
            && (stripos($payload, 'Exception') !== false || stripos($payload, 'Error') !== false)
        ) {
            $reasons[] = self::REASON_PHP_UNCAUGHT;
        }

        if (
            str_contains($pl, 'allow-plugins')
            || str_contains($pl, 'you must enable the plugin')
            || (str_contains($pl, 'plugin') && str_contains($pl, 'blocked'))
            || (str_contains($pl, 'plugin') && str_contains($pl, 'is disabled'))
        ) {
            $reasons[] = self::REASON_COMPOSER_PLUGIN_BLOCKED;
        }

        if (stripos($payload, 'PHP Fatal error') !== false) {
            $reasons[] = self::REASON_PHP_FATAL;
        }

        if (
            stripos($payload, 'FAILURES!') !== false
            || stripos($payload, 'ERRORS!') !== false
            || (stripos($payload, 'PHPUnit') !== false && stripos($payload, 'failed') !== false)
        ) {
            $reasons[] = self::REASON_PHPUNIT_FAILED;
        }

        return self::dedupPreserveOrder($reasons);
    }

    /**
     * @return list<string> sorted strcmp
     */
    private static function extractCoretsiaCodes(string $payload): array
    {
        if ($payload === '') {
            return [];
        }

        $matches = [];
        preg_match_all('/\bCORETSIA_[A-Z0-9_]+\b/', $payload, $matches);

        if (!isset($matches[0]) || !is_array($matches[0])) {
            return [];
        }

        $uniq = [];
        foreach ($matches[0] as $m) {
            if (!is_string($m) || $m === '') {
                continue;
            }
            if (strlen($m) > 160) {
                continue;
            }
            $uniq[$m] = true;
        }

        $out = array_keys($uniq);
        usort($out, static fn (string $a, string $b): int => strcmp($a, $b));

        /** @var list<string> $out */
        return $out;
    }

    /**
     * Create a fresh tmp root outside repo root.
     * Windows: prefer RUNNER_TEMP when available (shorter paths on GitHub Actions).
     */
    private static function createFreshTmpRootOutsideRepo(string $repoRoot): string
    {
        $repoRootCanonical = self::canonicalize($repoRoot);

        $baseNative = self::chooseTempBaseNative();
        $baseCanonical = self::canonicalize($baseNative);
        if ($baseCanonical === '') {
            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                [self::REASON_RUNNER_ERROR],
            );
        }

        if (self::isInside($baseCanonical, $repoRootCanonical)) {
            $parent = realpath($repoRootCanonical . DIRECTORY_SEPARATOR . '..');
            if ($parent === false) {
                throw RunnerFailure::with(
                    ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                    [self::REASON_RUNNER_ERROR],
                );
            }

            $baseNative = $parent;
            $baseCanonical = self::canonicalize($baseNative);
        }

        // Keep directory name SHORT to reduce Windows path-length risk.
        $dirNative = rtrim($baseNative, "\\/") . DIRECTORY_SEPARATOR . 'cstmp_' . bin2hex(random_bytes(4));

        if (@mkdir($dirNative, 0777, true) !== true) {
            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                [self::REASON_RUNNER_ERROR],
            );
        }

        $dirCanonical = self::canonicalize($dirNative);

        if ($dirCanonical === '' || self::isInside($dirCanonical, $repoRootCanonical)) {
            self::tryRemoveDirRecursive($dirCanonical !== '' ? $dirCanonical : $dirNative);

            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                [self::REASON_RUNNER_ERROR],
            );
        }

        return $dirCanonical;
    }

    /**
     * Pick a temp base directory (native), with Windows CI hardening.
     */
    private static function chooseTempBaseNative(): string
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            $rt = getenv('RUNNER_TEMP');
            if (is_string($rt) && $rt !== '' && is_dir($rt)) {
                return $rt;
            }
        }

        return sys_get_temp_dir();
    }

    private static function removeDirRecursive(string $dir): void
    {
        if ($dir === '') {
            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                [self::REASON_RUNNER_ERROR],
            );
        }

        if (!is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($it as $item) {
            $path = $item->getPathname();

            if ($item->isDir()) {
                if (@rmdir($path) !== true) {
                    throw RunnerFailure::with(
                        ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                        [self::REASON_RUNNER_ERROR],
                    );
                }

                continue;
            }

            if (@unlink($path) !== true) {
                throw RunnerFailure::with(
                    ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                    [self::REASON_RUNNER_ERROR],
                );
            }
        }

        if (@rmdir($dir) !== true) {
            throw RunnerFailure::with(
                ErrorCodes::CORETSIA_DETERMINISM_RERUN_FAILED,
                [self::REASON_RUNNER_ERROR],
            );
        }
    }

    private static function tryRemoveDirRecursive(string $dir): void
    {
        try {
            self::removeDirRecursive($dir);
        } catch (\Throwable) {
            // Best-effort cleanup only; never emit output here.
        }
    }

    private static function runCommand(
        string $command,
        string $cwd,
        ?array $env,
        bool   $captureStdout,
        bool   $captureStderr
    ): ProcessResult {
        return self::runner()->run($command, $cwd, $env, $captureStdout, $captureStderr);
    }

    private static function canonicalize(string $path): string
    {
        $path = rtrim($path, "\\/");

        if ($path === '') {
            return '';
        }

        $real = realpath($path);
        $canonical = $real !== false ? $real : $path;

        return str_replace('\\', '/', $canonical);
    }

    private static function isInside(string $candidate, string $root): bool
    {
        $candidate = rtrim($candidate, '/');
        $root = rtrim($root, '/');

        if ($candidate === '' || $root === '') {
            return false;
        }

        if ($candidate === $root) {
            return true;
        }

        return str_starts_with($candidate, $root . '/');
    }

    /**
     * @param list<string> $items
     * @return list<string>
     */
    private static function dedupPreserveOrder(array $items): array
    {
        $seen = [];
        $out = [];
        foreach ($items as $it) {
            if (!is_string($it) || $it === '') {
                continue;
            }
            if (isset($seen[$it])) {
                continue;
            }
            $seen[$it] = true;
            $out[] = $it;
        }

        /** @var list<string> $out */
        return $out;
    }
}

// If executed as a script, enforce exit policy deterministically.
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(DeterminismRunner::main());
}
