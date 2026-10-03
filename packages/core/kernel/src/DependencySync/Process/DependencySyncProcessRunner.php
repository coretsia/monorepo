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

namespace Coretsia\Kernel\DependencySync\Process;

use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;

/** @internal */
final readonly class DependencySyncProcessRunner
{
    private const int MAX_CAPTURED_STDOUT_BYTES = 65_536;
    private const int MAX_CAPTURED_STDERR_BYTES = 65_536;

    public function __construct(
        private string $composerExecutable = 'composer',
    ) {
        if (!self::isValidExecutableToken($this->composerExecutable)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
        }
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{exitCode: int, timedOut: bool, stdout: string, stderr: string}
     */
    public function runComposer(
        string $projectRoot,
        array $arguments,
        int $timeoutSeconds,
        string $stdin = '',
    ): array {
        $argv = $this->composerArgv();

        foreach (
            self::validateArguments(
                $arguments,
                DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
            ) as $argument
        ) {
            $argv[] = $argument;
        }

        return self::runExact(
            $projectRoot,
            $argv,
            $timeoutSeconds,
            $stdin,
            DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
        );
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{exitCode: int, timedOut: bool, stdout: string, stderr: string}
     */
    public function runPhpScript(
        string $projectRoot,
        string $scriptPath,
        array $arguments,
        int $timeoutSeconds,
        string $stdin = '',
    ): array {
        $resolved = @\realpath($scriptPath);

        if (
            !\is_string($resolved)
            || !\is_file($resolved)
            || \is_link($scriptPath)
            || !\is_file(\PHP_BINARY)
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::VERIFICATION_EXECUTION_FAILED);
        }

        $argv = [\PHP_BINARY, $resolved];

        foreach (
            self::validateArguments(
                $arguments,
                DependencySyncErrorCodes::VERIFICATION_EXECUTION_FAILED,
            ) as $argument
        ) {
            $argv[] = $argument;
        }

        return self::runExact(
            $projectRoot,
            $argv,
            $timeoutSeconds,
            $stdin,
            DependencySyncErrorCodes::VERIFICATION_EXECUTION_FAILED,
        );
    }

    /** @return non-empty-list<string> */
    private function composerArgv(): array
    {
        $executable = $this->composerExecutable;
        $isExplicit = \str_contains($executable, '/') || \str_contains($executable, '\\');

        if (!$isExplicit) {
            if (\PHP_OS_FAMILY !== 'Windows') {
                return [$executable];
            }

            $resolved = self::resolveWindowsExecutableToken($executable);

            if ($resolved === null) {
                return [$executable];
            }

            return self::resolvedComposerArgv($resolved);
        }

        $resolved = @\realpath($executable);

        if (!\is_string($resolved) || !\is_file($resolved)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
        }

        return self::resolvedComposerArgv($resolved);
    }

    /** @return non-empty-list<string> */
    private static function resolvedComposerArgv(
        string $resolved,
    ): array {
        $extension = \strtolower(
            (string) \pathinfo(
                $resolved,
                \PATHINFO_EXTENSION,
            ),
        );

        if ($extension === 'php' || $extension === 'phar') {
            if (!\is_file(\PHP_BINARY)) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
            }

            return [\PHP_BINARY, $resolved];
        }

        if (
            \PHP_OS_FAMILY === 'Windows'
            && ($extension === 'bat' || $extension === 'cmd')
        ) {
            if (!\is_file(\PHP_BINARY)) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
            }

            $directory = \dirname($resolved);
            $filename = (string) \pathinfo(
                $resolved,
                \PATHINFO_FILENAME,
            );

            foreach (['phar', 'php'] as $scriptExtension) {
                $candidate = @\realpath(
                    $directory
                    . \DIRECTORY_SEPARATOR
                    . $filename
                    . '.'
                    . $scriptExtension,
                );

                if (
                    \is_string($candidate)
                    && \is_file($candidate)
                ) {
                    return [\PHP_BINARY, $candidate];
                }
            }

            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
        }

        if (
            \DIRECTORY_SEPARATOR !== '\\'
            && !\is_executable($resolved)
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
        }

        return [$resolved];
    }

    private static function resolveWindowsExecutableToken(
        string $executable,
    ): ?string {
        $composerBinary = \getenv('COMPOSER_BINARY');

        if (
            $executable === 'composer'
            && \is_string($composerBinary)
            && $composerBinary !== ''
        ) {
            $resolved = @\realpath($composerBinary);

            if (
                \is_string($resolved)
                && \is_file($resolved)
                && \in_array(
                    \strtolower(
                        (string) \pathinfo(
                            $resolved,
                            \PATHINFO_EXTENSION,
                        ),
                    ),
                    [
                        'com',
                        'exe',
                        'bat',
                        'cmd',
                        'php',
                        'phar',
                    ],
                    true,
                )
            ) {
                return $resolved;
            }
        }

        $path = \getenv('PATH');

        if (!\is_string($path) || $path === '') {
            return null;
        }

        $hasExtension = (string) \pathinfo($executable, \PATHINFO_EXTENSION) !== '';

        $extensions = $hasExtension ? [''] : [];
        $pathExtensions = \getenv('PATHEXT');

        if (!$hasExtension) {
            if (
                \is_string($pathExtensions)
                && $pathExtensions !== ''
            ) {
                foreach (
                    \explode(';', $pathExtensions) as $extension
                ) {
                    $extension = \trim($extension);

                    if ($extension === '') {
                        continue;
                    }

                    if ($extension[0] !== '.') {
                        $extension = '.' . $extension;
                    }

                    $extensions[] = $extension;
                }
            } else {
                $extensions = [
                    '.COM',
                    '.EXE',
                    '.BAT',
                    '.CMD',
                ];
            }
        }

        foreach (
            \explode(\PATH_SEPARATOR, $path) as $directory
        ) {
            $directory = \trim($directory, " \t\n\r\0\x0B\"");

            if ($directory === '') {
                continue;
            }

            foreach ($extensions as $extension) {
                $resolved = @\realpath(
                    $directory
                    . \DIRECTORY_SEPARATOR
                    . $executable
                    . $extension,
                );

                if (
                    \is_string($resolved)
                    && \is_file($resolved)
                ) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     */
    private static function validateArguments(array $arguments, string $errorCode): array
    {
        if (!\array_is_list($arguments)) {
            throw DependencySyncException::forCode($errorCode);
        }

        foreach ($arguments as $argument) {
            if (!\is_string($argument) || \preg_match('/[\x00-\x1F\x7F]/', $argument) === 1) {
                throw DependencySyncException::forCode($errorCode);
            }
        }

        return $arguments;
    }

    private static function isValidExecutableToken(string $value): bool
    {
        if (
            $value === ''
            || \trim($value) !== $value
            || \preg_match('/[\x00-\x20\x7F]/', $value) === 1
            || \preg_match('~\A[A-Za-z][A-Za-z0-9+.-]*://~', $value) === 1
            || \preg_match('/[;&|<>`]/', $value) === 1
        ) {
            return false;
        }

        if (!\str_contains($value, '/') && !\str_contains($value, '\\')) {
            return \preg_match('/\A[A-Za-z0-9_.-]+\z/D', $value) === 1;
        }

        return true;
    }

    /**
     * @param non-empty-list<string> $argv
     *
     * @return array{exitCode: int, timedOut: bool, stdout: string, stderr: string}
     */
    private static function runExact(
        string $projectRoot,
        array $argv,
        int $timeoutSeconds,
        string $stdin,
        string $startFailureCode,
    ): array {
        $cwd = @\realpath($projectRoot);

        if (!\is_string($cwd) || !\is_dir($cwd) || $timeoutSeconds <= 0) {
            throw DependencySyncException::forCode($startFailureCode);
        }

        if (\PHP_OS_FAMILY === 'Windows') {
            return self::runExactWindows(
                $cwd,
                $argv,
                $timeoutSeconds,
                $stdin,
                $startFailureCode,
            );
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];

        try {
            $process = @\proc_open(
                $argv,
                $descriptors,
                $pipes,
                $cwd,
                null,
                [
                    'bypass_shell' => true,
                    'suppress_errors' => true,
                ],
            );
        } catch (\Throwable) {
            $process = false;
        }

        if (!\is_resource($process) || \count($pipes) !== 3) {
            throw DependencySyncException::forCode($startFailureCode);
        }

        @\stream_set_blocking($pipes[0], false);
        @\stream_set_blocking($pipes[1], false);
        @\stream_set_blocking($pipes[2], false);

        $stdinLength = \strlen($stdin);
        $stdinOffset = 0;
        $stdinClosed = false;
        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $startedAt = \microtime(true);
        $knownExitCode = null;

        while (true) {
            if (!$stdinClosed) {
                if ($stdinOffset < $stdinLength) {
                    $written = @\fwrite(
                        $pipes[0],
                        \substr($stdin, $stdinOffset, 8192),
                    );

                    if (\is_int($written) && $written > 0) {
                        $stdinOffset += $written;
                    } elseif ($written === false) {
                        @\fclose($pipes[0]);
                        $stdinClosed = true;
                    }
                }

                if (!$stdinClosed && $stdinOffset >= $stdinLength) {
                    @\fclose($pipes[0]);
                    $stdinClosed = true;
                }
            }

            self::drain(
                $pipes[1],
                $stdout,
                self::MAX_CAPTURED_STDOUT_BYTES,
            );
            self::drain(
                $pipes[2],
                $stderr,
                self::MAX_CAPTURED_STDERR_BYTES,
            );

            $status = @\proc_get_status($process);

            if (!$status['running']) {
                if ($status['exitcode'] >= 0) {
                    $knownExitCode = $status['exitcode'];
                }

                break;
            }

            if ((\microtime(true) - $startedAt) >= $timeoutSeconds) {
                $timedOut = true;
                @\proc_terminate($process);
                \usleep(100_000);

                $status = @\proc_get_status($process);

                if ($status['running']) {
                    @\proc_terminate($process, 9);
                }

                break;
            }

            \usleep(10_000);
        }

        if (!$stdinClosed) {
            @\fclose($pipes[0]);
        }

        self::drain(
            $pipes[1],
            $stdout,
            self::MAX_CAPTURED_STDOUT_BYTES,
        );
        self::drain(
            $pipes[2],
            $stderr,
            self::MAX_CAPTURED_STDERR_BYTES,
        );

        @\fclose($pipes[1]);
        @\fclose($pipes[2]);
        $closedExitCode = @\proc_close($process);
        $exitCode = $knownExitCode ?? $closedExitCode;

        if ($timedOut && $exitCode === 0) {
            $exitCode = 1;
        }

        return [
            'exitCode' => $exitCode,
            'timedOut' => $timedOut,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * @param non-empty-list<string> $argv
     *
     * @return array{
     *     exitCode: int,
     *     timedOut: bool,
     *     stdout: string,
     *     stderr: string
     * }
     */
    private static function runExactWindows(
        string $cwd,
        array $argv,
        int $timeoutSeconds,
        string $stdin,
        string $startFailureCode,
    ): array {
        $stdinFile = @\tmpfile();
        $stdoutFile = @\tmpfile();
        $stderrFile = @\tmpfile();

        if (
            !\is_resource($stdinFile)
            || !\is_resource($stdoutFile)
            || !\is_resource($stderrFile)
        ) {
            foreach (
                [
                    $stdinFile,
                    $stdoutFile,
                    $stderrFile,
                ] as $file
            ) {
                if (\is_resource($file)) {
                    @\fclose($file);
                }
            }

            throw DependencySyncException::forCode($startFailureCode);
        }

        $process = false;

        try {
            $stdinLength = \strlen($stdin);
            $stdinOffset = 0;

            while ($stdinOffset < $stdinLength) {
                $written = @\fwrite(
                    $stdinFile,
                    \substr(
                        $stdin,
                        $stdinOffset,
                        8192,
                    ),
                );

                if (
                    !\is_int($written)
                    || $written <= 0
                ) {
                    throw DependencySyncException::forCode($startFailureCode);
                }

                $stdinOffset += $written;
            }

            if (
                !@\fflush($stdinFile)
                || !@\rewind($stdinFile)
            ) {
                throw DependencySyncException::forCode($startFailureCode);
            }

            $pipes = [];

            try {
                $process = @\proc_open(
                    $argv,
                    [
                        0 => $stdinFile,
                        1 => $stdoutFile,
                        2 => $stderrFile,
                    ],
                    $pipes,
                    $cwd,
                    null,
                    [
                        'bypass_shell' => true,
                        'suppress_errors' => true,
                    ],
                );
            } catch (\Throwable) {
                $process = false;
            }

            if (!\is_resource($process)) {
                throw DependencySyncException::forCode($startFailureCode);
            }

            $timedOut = false;
            $startedAt = \microtime(true);
            $knownExitCode = null;

            while (true) {
                $status = @\proc_get_status($process);

                if (!$status['running']) {
                    if ($status['exitcode'] >= 0) {
                        $knownExitCode = $status['exitcode'];
                    }

                    break;
                }

                if (
                    (\microtime(true) - $startedAt)
                    >= $timeoutSeconds
                ) {
                    $timedOut = true;

                    @\proc_terminate($process);
                    \usleep(100_000);

                    $status = @\proc_get_status($process);

                    if ($status['running']) {
                        @\proc_terminate($process, 9);
                    }

                    break;
                }

                \usleep(10_000);
            }

            $closedExitCode = @\proc_close($process);
            $process = false;

            $exitCode = $knownExitCode ?? $closedExitCode;

            if ($timedOut && $exitCode === 0) {
                $exitCode = 1;
            }

            if (
                !@\rewind($stdoutFile)
                || !@\rewind($stderrFile)
            ) {
                throw DependencySyncException::forCode($startFailureCode);
            }

            $stdout = @\fread(
                $stdoutFile,
                self::MAX_CAPTURED_STDOUT_BYTES,
            );
            $stderr = @\fread(
                $stderrFile,
                self::MAX_CAPTURED_STDERR_BYTES,
            );

            return [
                'exitCode' => $exitCode,
                'timedOut' => $timedOut,
                'stdout' => \is_string($stdout) ? $stdout : '',
                'stderr' => \is_string($stderr) ? $stderr : '',
            ];
        } finally {
            if (\is_resource($process)) {
                @\proc_terminate($process, 9);
                @\proc_close($process);
            }

            @\fclose($stdinFile);
            @\fclose($stdoutFile);
            @\fclose($stderrFile);
        }
    }

    /** @param resource $pipe */
    private static function drain($pipe, string &$capture, int $limit): void
    {
        while (true) {
            $chunk = @\fread($pipe, 8192);

            if (!\is_string($chunk) || $chunk === '') {
                return;
            }

            self::appendBounded($capture, $chunk, $limit);
        }
    }

    private static function appendBounded(string &$capture, string $chunk, int $limit): void
    {
        $remaining = $limit - \strlen($capture);

        if ($remaining > 0) {
            $capture .= \substr($chunk, 0, $remaining);
        }
    }
}
