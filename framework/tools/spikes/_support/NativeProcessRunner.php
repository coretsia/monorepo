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
 * @internal
 */
final class NativeProcessRunner implements ProcessRunnerInterface
{
    private static ?string $gitExe = null;

    /**
     * Windows hardening:
     * Resolve Git-for-Windows bash.exe to avoid accidentally hitting WSL launcher (System32\bash.exe).
     */
    private static ?string $bashExe = null;

    public function run(
        string $command,
        string $cwd,
        ?array $env,
        bool   $captureStdout,
        bool   $captureStderr
    ): ProcessResult {
        $null = self::nullDevice();

        $spec = [
            0 => ['pipe', 'r'],
            1 => $captureStdout ? ['pipe', 'w'] : ['file', $null, 'w'],
            2 => $captureStderr ? ['pipe', 'w'] : ['file', $null, 'w'],
        ];

        foreach (self::commandCandidates($command) as $cmd) {
            $pipes = [];

            $proc = @proc_open($cmd, $spec, $pipes, $cwd, $env, self::procOptions($cmd));
            if (!is_resource($proc)) {
                continue;
            }

            if (isset($pipes[0]) && is_resource($pipes[0])) {
                fclose($pipes[0]);
            }

            $stdout = '';
            $stderr = '';

            if ($captureStdout && isset($pipes[1]) && is_resource($pipes[1])) {
                $stdout = (string)stream_get_contents($pipes[1]);
                fclose($pipes[1]);
            }

            if ($captureStderr && isset($pipes[2]) && is_resource($pipes[2])) {
                $stderr = (string)stream_get_contents($pipes[2]);
                fclose($pipes[2]);
            }

            $exitCode = (int)proc_close($proc);

            // Sentinel fallback codes (Windows shells).
            if (self::shouldFallback($cmd, $exitCode)) {
                continue;
            }

            // Extra Windows hardening: sometimes cmd/bash return exit=1 with "command not found" messages
            // instead of sentinel codes. We must treat those as "try next candidate".
            if (self::shouldFallbackByOutput($cmd, $exitCode, $stdout, $stderr)) {
                continue;
            }

            // Extra Windows hardening: Git-Bash/MSYS boundary sometimes yields exit!=0 with empty output.
            // Treat as a shell-level failure and try the next candidate.
            if (self::shouldFallbackByEmptyOutput($cmd, $exitCode, $stdout, $stderr)) {
                continue;
            }

            $r = new ProcessResult($exitCode, $stdout, $stderr);
            $r->runnerTag = self::describeCandidate($cmd);

            return $r;
        }

        $r = new ProcessResult(1, '', '');
        $r->runnerTag = null;

        return $r;
    }

    public function runSilenced(string $command, string $cwd, array $env): ?int
    {
        $null = self::nullDevice();

        $spec = [
            0 => ['pipe', 'r'],
            1 => ['file', $null, 'w'],
            2 => ['file', $null, 'w'],
        ];

        foreach (self::commandCandidates($command) as $cmd) {
            $pipes = [];

            $proc = @proc_open($cmd, $spec, $pipes, $cwd, $env, self::procOptions($cmd));
            if (!is_resource($proc)) {
                continue;
            }

            if (isset($pipes[0]) && is_resource($pipes[0])) {
                fclose($pipes[0]);
            }

            $exitCode = (int)proc_close($proc);

            if (self::shouldFallback($cmd, $exitCode)) {
                continue;
            }

            // NOTE: silenced mode has no output capture => cannot apply output-based fallbacks here.
            return $exitCode;
        }

        return null;
    }

    /**
     * Windows: prefer resolved git.exe path for "git *" commands to avoid PATH/MSYS issues in CI.
     *
     * For "composer *" commands on Windows:
     * - if running under MSYS/Git-Bash environment: prefer bash first (avoid cmd boundary)
     * - otherwise: prefer cmd.exe first (stable .bat/.cmd resolution)
     * - then raw
     *
     * @return list<string|array<int,string>>
     */
    private static function commandCandidates(string $command): array
    {
        if (\PHP_OS_FAMILY !== 'Windows') {
            return [$command];
        }

        $trim = ltrim($command);
        $first = self::firstTokenLower($trim);

        // 1) Hardening for git: resolve absolute git.exe path first (most stable on GitHub Actions).
        if ($first === 'git') {
            $gitExe = self::resolveGitExe();
            if ($gitExe !== null) {
                $argv = self::splitBySpaces($trim);
                if ($argv !== [] && strtolower($argv[0]) === 'git') {
                    array_shift($argv);

                    $direct = array_merge([$gitExe], $argv);

                    return [$direct, self::bashCandidate($command), $command];
                }
            }

            return [self::bashCandidate($command), $command];
        }

        // 2) Hardening for composer: choose order based on current shell environment.
        if ($first === 'composer') {
            if (self::preferBashOnWindows()) {
                return [self::bashCandidate($command), self::cmdCandidate($command), $command];
            }

            return [self::cmdCandidate($command), self::bashCandidate($command), $command];
        }

        // Default Windows behavior: bash first (matches CI shell), then raw.
        return [self::bashCandidate($command), $command];
    }

    /**
     * Detect MSYS/Git-Bash-like environment to avoid cmd boundary issues.
     */
    private static function preferBashOnWindows(): bool
    {
        if (\PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $msys = getenv('MSYSTEM');
        if (is_string($msys) && $msys !== '') {
            return true;
        }

        $msys2 = getenv('MSYS');
        if (is_string($msys2) && $msys2 !== '') {
            return true;
        }

        $shell = getenv('SHELL');
        if (is_string($shell) && $shell !== '' && stripos($shell, 'bash') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Match GitHub Actions bash semantics as close as possible:
     * - no profile, no rc
     * - fail-fast: -e
     * - pipefail: -o pipefail
     * - execute command: -c
     *
     * Windows hardening:
     * - prefer Git-for-Windows bash.exe absolute path if available
     *   to avoid WSL launcher (System32\bash.exe).
     *
     * @return array<int,string>
     */
    private static function bashCandidate(string $command): array
    {
        $exe = 'bash';

        if (\PHP_OS_FAMILY === 'Windows') {
            $resolved = self::resolveBashExe();
            if ($resolved !== null) {
                $exe = $resolved;
            }
        }

        return [$exe, '--noprofile', '--norc', '-e', '-o', 'pipefail', '-c', $command];
    }

    /**
     * cmd.exe candidate (bypass_shell): stable invocation for .bat/.cmd resolution.
     *
     * @return array<int,string>
     */
    private static function cmdCandidate(string $command): array
    {
        return ['cmd.exe', '/d', '/s', '/c', $command];
    }

    /**
     * @param string|array<int,string> $cmd
     * @return array<string,mixed>
     */
    private static function procOptions(string|array $cmd): array
    {
        // When using array form, explicitly bypass the shell.
        if (\PHP_OS_FAMILY === 'Windows' && is_array($cmd)) {
            return ['bypass_shell' => true];
        }

        return [];
    }

    private static function nullDevice(): string
    {
        return (\PHP_OS_FAMILY === 'Windows') ? 'NUL' : '/dev/null';
    }

    private static function firstTokenLower(string $command): string
    {
        $command = ltrim($command);
        if ($command === '') {
            return '';
        }

        $parts = self::splitBySpaces($command);
        if ($parts === []) {
            return '';
        }

        return strtolower((string)$parts[0]);
    }

    /**
     * Minimal splitter for our controlled commands (no quotes expected).
     *
     * @return list<string>
     */
    private static function splitBySpaces(string $command): array
    {
        $command = trim($command);
        if ($command === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $command);
        if (!is_array($parts)) {
            return [];
        }

        $out = [];
        foreach ($parts as $p) {
            if (!is_string($p) || $p === '') {
                continue;
            }
            $out[] = $p;
        }

        /** @var list<string> $out */
        return $out;
    }

    /**
     * Minimal exe detector for array-form candidates:
     * - normalizes slashes
     * - returns basename() lowercased (e.g. "bash.exe", "cmd.exe", "git.exe").
     *
     * @param string|array<int,string> $cmd
     */
    private static function exeBase(string|array $cmd): string
    {
        if (!is_array($cmd) || $cmd === []) {
            return '';
        }

        $exe = strtolower((string)($cmd[0] ?? ''));
        if ($exe === '') {
            return '';
        }

        $exe = str_replace('\\', '/', $exe);

        return strtolower(basename($exe));
    }

    /**
     * On Windows we only fallback on "command not found" sentinel exit codes:
     * - bash -c <cmd> : 127
     * - cmd /c <cmd>  : 9009
     *
     * @param string|array<int,string> $cmd
     */
    private static function shouldFallback(string|array $cmd, int $exitCode): bool
    {
        if (\PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        if ($exitCode === 0) {
            return false;
        }

        if (!is_array($cmd) || $cmd === []) {
            return false;
        }

        $base = self::exeBase($cmd);

        if (($base === 'bash' || $base === 'bash.exe') && $exitCode === 127) {
            return true;
        }

        if (($base === 'cmd' || $base === 'cmd.exe') && $exitCode === 9009) {
            return true;
        }

        return false;
    }

    /**
     * Windows hardening:
     * Sometimes shells return exit=1 (not 127/9009) while still clearly being "command not found".
     * We treat those as fallback signals too (safe; does not emit output).
     *
     * Additional hardening:
     * - detect WSL launcher message ("Windows Subsystem for Linux has no installed distributions.")
     *   and fallback to the next candidate (typically cmd.exe) to avoid getting stuck on wrong bash.exe.
     *
     * @param string|array<int,string> $cmd
     */
    private static function shouldFallbackByOutput(string|array $cmd, int $exitCode, string $stdout, string $stderr): bool
    {
        if (\PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        if ($exitCode === 0) {
            return false;
        }

        if (!is_array($cmd) || $cmd === []) {
            return false;
        }

        $base = self::exeBase($cmd);
        if ($base === '') {
            return false;
        }

        $payload = strtolower($stdout . "\n" . $stderr);
        if ($payload === '') {
            return false;
        }

        // cmd.exe typical "not found" phrasing.
        if ($base === 'cmd' || $base === 'cmd.exe') {
            if (
                str_contains($payload, 'is not recognized as an internal or external command')
                || str_contains($payload, 'the system cannot find the path specified')
                || str_contains($payload, 'the system cannot find the file specified')
            ) {
                return true;
            }

            return false;
        }

        // bash typical "not found" phrasing + WSL launcher failure signature.
        if ($base === 'bash' || $base === 'bash.exe') {
            if (
                str_contains($payload, 'command not found')
                || str_contains($payload, 'no such file or directory')
            ) {
                return true;
            }

            // WSL launcher: no installed distributions.
            if (str_contains($payload, 'windows subsystem for linux has no installed distributions')) {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Windows hardening:
     * If Git Bash/MSYS fails across boundary, it may return nonzero with empty output.
     * Try the next candidate in that case (safe).
     *
     * @param string|array<int,string> $cmd
     */
    private static function shouldFallbackByEmptyOutput(string|array $cmd, int $exitCode, string $stdout, string $stderr): bool
    {
        if (\PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        if ($exitCode === 0) {
            return false;
        }

        if (!is_array($cmd) || $cmd === []) {
            return false;
        }

        $base = self::exeBase($cmd);
        if (!($base === 'bash' || $base === 'bash.exe')) {
            return false;
        }

        $payload = trim($stdout . $stderr);
        return $payload === '';
    }

    /**
     * @param string|array<int,string> $cmd
     */
    private static function describeCandidate(string|array $cmd): string
    {
        if (!is_array($cmd) || $cmd === []) {
            return 'raw';
        }

        $base = self::exeBase($cmd);

        if ($base === 'bash' || $base === 'bash.exe') {
            return 'bash';
        }

        if ($base === 'cmd' || $base === 'cmd.exe') {
            return 'cmd';
        }

        $exe = strtolower((string)($cmd[0] ?? ''));

        // Any absolute exe path (git.exe direct, bash.exe direct etc) => "direct" (no path leakage).
        if ($exe !== '' && (str_contains($exe, '\\') || str_contains($exe, '/'))) {
            return 'direct';
        }

        return $exe !== '' ? $exe : 'raw';
    }

    /**
     * Resolve git.exe via well-known Git for Windows install locations.
     * No output; deterministic candidate order.
     */
    private static function resolveGitExe(): ?string
    {
        if (self::$gitExe !== null) {
            return self::$gitExe;
        }

        if (\PHP_OS_FAMILY !== 'Windows') {
            self::$gitExe = null;
            return null;
        }

        $candidates = [];

        $pf = getenv('ProgramFiles');
        if (is_string($pf) && $pf !== '') {
            $candidates[] = $pf . '\\Git\\cmd\\git.exe';
            $candidates[] = $pf . '\\Git\\bin\\git.exe';
        }

        $pfx86 = getenv('ProgramFiles(x86)');
        if (is_string($pfx86) && $pfx86 !== '') {
            $candidates[] = $pfx86 . '\\Git\\cmd\\git.exe';
            $candidates[] = $pfx86 . '\\Git\\bin\\git.exe';
        }

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                self::$gitExe = $path;
                return self::$gitExe;
            }
        }

        self::$gitExe = null;
        return null;
    }

    /**
     * Resolve Git-for-Windows bash.exe via well-known install locations.
     * No output; deterministic candidate order.
     *
     * IMPORTANT: This is to avoid accidentally invoking the WSL launcher
     * (typically C:\Windows\System32\bash.exe) on GitHub Actions runners.
     */
    private static function resolveBashExe(): ?string
    {
        if (self::$bashExe !== null) {
            return self::$bashExe;
        }

        if (\PHP_OS_FAMILY !== 'Windows') {
            self::$bashExe = null;
            return null;
        }

        $candidates = [];

        $pf = getenv('ProgramFiles');
        if (is_string($pf) && $pf !== '') {
            $candidates[] = $pf . '\\Git\\bin\\bash.exe';
            $candidates[] = $pf . '\\Git\\usr\\bin\\bash.exe';
        }

        $pfx86 = getenv('ProgramFiles(x86)');
        if (is_string($pfx86) && $pfx86 !== '') {
            $candidates[] = $pfx86 . '\\Git\\bin\\bash.exe';
            $candidates[] = $pfx86 . '\\Git\\usr\\bin\\bash.exe';
        }

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                self::$bashExe = $path;
                return self::$bashExe;
            }
        }

        self::$bashExe = null;
        return null;
    }
}
