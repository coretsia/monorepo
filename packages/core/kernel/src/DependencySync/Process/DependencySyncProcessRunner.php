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
            return [$executable];
        }

        $resolved = @\realpath($executable);

        if (!\is_string($resolved) || !\is_file($resolved)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
        }

        $extension = \strtolower((string) \pathinfo($resolved, \PATHINFO_EXTENSION));

        if ($extension === 'php' || $extension === 'phar') {
            if (!\is_file(\PHP_BINARY)) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
            }

            return [\PHP_BINARY, $resolved];
        }

        if (\DIRECTORY_SEPARATOR !== '\\' && !\is_executable($resolved)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED);
        }

        return [$resolved];
    }

    /** @param list<string> $arguments @return list<string> */
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
