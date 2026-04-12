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
 * Stable console writer for Phase 0 tooling rails (spikes + gates).
 *
 * Policy (cemented by roadmap):
 * - This is the ONLY allowlisted stdout/stderr writer for Phase 0 tooling rails.
 * - Callers MUST NOT print secrets/PII; only deterministic codes + normalized repo-relative paths.
 * - Output MUST be stable across OS (LF-only, no CRLF, no absolute paths, no env values).
 *
 * Fail-safe behavior:
 * - If a provided line looks unsafe, it is replaced with fixed token "unsafe-output".
 */
final class ConsoleOutput
{
    private const string UNSAFE_OUTPUT_TOKEN = 'unsafe-output';

    /** @var resource|null */
    private static $stdout = null;

    /** @var resource|null */
    private static $stderr = null;

    private function __construct()
    {
    }

    /**
     * Emit a single sanitized line (LF-only, exactly one trailing "\n").
     *
     * @param bool $toStderr true => STDERR, false => STDOUT
     */
    public static function line(string $line, bool $toStderr = true): void
    {
        $stream = $toStderr ? self::stderrStream() : self::stdoutStream();

        $sanitized = self::sanitizeLine($line);

        self::write($stream, $sanitized . "\n");
    }

    /**
     * Emit multiple sanitized lines (each LF-only, each ends with "\n").
     *
     * @param list<string> $lines
     * @param bool $toStderr true => STDERR, false => STDOUT
     */
    public static function lines(array $lines, bool $toStderr = true): void
    {
        $stream = $toStderr ? self::stderrStream() : self::stdoutStream();

        foreach ($lines as $line) {
            if (!\is_string($line)) {
                self::write($stream, self::UNSAFE_OUTPUT_TOKEN . "\n");
                continue;
            }

            $sanitized = self::sanitizeLine($line);
            self::write($stream, $sanitized . "\n");
        }
    }

    /**
     * Convenience: emit CODE on line 1, then optional diagnostics lines.
     *
     * @param list<string> $diagnostics
     * @param bool $toStderr true => STDERR, false => STDOUT
     */
    public static function codeWithDiagnostics(string $code, array $diagnostics = [], bool $toStderr = true): void
    {
        self::line($code, $toStderr);

        if ($diagnostics === []) {
            return;
        }

        self::lines($diagnostics, $toStderr);
    }

    /**
     * @return resource
     */
    private static function stdoutStream()
    {
        if (self::$stdout !== null) {
            return self::$stdout;
        }

        if (\defined('STDOUT') && \is_resource(\STDOUT)) {
            self::$stdout = \STDOUT;
            return self::$stdout;
        }

        // CLI-only: best-effort fallback.
        self::$stdout = self::openStream('php://stdout');

        return self::$stdout;
    }

    /**
     * @return resource
     */
    private static function stderrStream()
    {
        if (self::$stderr !== null) {
            return self::$stderr;
        }

        if (\defined('STDERR') && \is_resource(\STDERR)) {
            self::$stderr = \STDERR;
            return self::$stderr;
        }

        // CLI-only: best-effort fallback.
        self::$stderr = self::openStream('php://stderr');

        return self::$stderr;
    }

    /**
     * @return resource
     */
    private static function openStream(string $uri)
    {
        // Suppress warnings deterministically (warnings themselves would violate output policy).
        return self::withSuppressedErrors(static function () use ($uri) {
            $h = \fopen($uri, 'wb');
            if (\is_resource($h)) {
                return $h;
            }

            // As a last resort: open php://output (still better than leaking warnings).
            $fallback = \fopen('php://output', 'wb');
            if (\is_resource($fallback)) {
                return $fallback;
            }

            // Absolutely nothing we can do; create a memory stream to keep code paths deterministic.
            $mem = \fopen('php://memory', 'wb');
            if (\is_resource($mem)) {
                return $mem;
            }

            // Unreachable in practice; but keep it deterministic.
            throw new \RuntimeException('console-stream-unavailable');
        });
    }

    /**
     * Write bytes to a stream without producing PHP warnings/notices.
     *
     * @param resource $stream
     */
    private static function write($stream, string $bytes): void
    {
        self::withSuppressedErrors(static function () use ($stream, $bytes): void {
            if (!\is_resource($stream)) {
                return;
            }

            // fwrite() may write partial data; loop to ensure full write (deterministic).
            $len = \strlen($bytes);
            $off = 0;

            while ($off < $len) {
                $written = \fwrite($stream, \substr($bytes, $off));
                if ($written === false || $written === 0) {
                    break;
                }
                $off += $written;
            }
        });
    }

    /**
     * Sanitize a single output line to avoid multiline injection and obvious secret/path leaks.
     */
    private static function sanitizeLine(string $line): string
    {
        // Normalize EOL first.
        $line = \str_replace(["\r\n", "\r"], "\n", $line);

        // Enforce single-line: take the first line segment only.
        $pos = \strpos($line, "\n");
        if ($pos !== false) {
            $line = \substr($line, 0, $pos);
        }

        // Trim spaces deterministically.
        $line = \trim($line);

        if ($line === '') {
            return self::UNSAFE_OUTPUT_TOKEN;
        }

        // Reject non-printable or non-ASCII (stability across OS/terminals).
        if (\preg_match('/^[\x20-\x7E]+$/', $line) !== 1) {
            return self::UNSAFE_OUTPUT_TOKEN;
        }

        // Hard bans to reduce accidental leakage.
        // - "=" blocks dotenv-like dumps (KEY=VALUE)
        // - "://" blocks URL-like accidental output (incl php://*)
        if (
            \str_contains($line, "\0")
            || \str_contains($line, "\x1B") // ANSI escapes
            || \str_contains($line, '=')
            || \str_contains($line, '://')
        ) {
            return self::UNSAFE_OUTPUT_TOKEN;
        }

        // Reject parent traversal markers (both slash styles).
        if (
            $line === '..'
            || \str_starts_with($line, '../')
            || \str_contains($line, '/../')
            || \str_ends_with($line, '/..')
            || \str_starts_with($line, '..\\')
            || \str_contains($line, '\\..\\')
            || \str_ends_with($line, '\\..')
        ) {
            return self::UNSAFE_OUTPUT_TOKEN;
        }

        // Absolute-path hints (minimum cemented set).
        if (
            \str_starts_with($line, '/')
            || \str_contains($line, '/home/')
            || \str_contains($line, '/Users/')
            || \preg_match('/(?i)\b[A-Z]:[\/\\\\]/', $line) === 1
            || \preg_match('/\\\\\\\\[^\s\\\\\/]+/', $line) === 1 // UNC-like
        ) {
            return self::UNSAFE_OUTPUT_TOKEN;
        }

        // Backslash policy:
        // Allow "\" ONLY for the cemented namespace-diagnostic token "forbidden-import:<Namespace\Root>",
        // optionally prefixed by "<relpath>: ".
        if (\str_contains($line, '\\')) {
            $ok = \preg_match(
                    '/^([A-Za-z0-9._\/-]+:\s)?forbidden-import:[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/',
                    $line
                ) === 1;

            if (!$ok) {
                return self::UNSAFE_OUTPUT_TOKEN;
            }
        }

        return $line;
    }

    /**
     * Execute callable with warnings/notices suppressed (no output pollution).
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    private static function withSuppressedErrors(callable $fn)
    {
        \set_error_handler(static function (): bool {
            return true;
        });

        try {
            return $fn();
        } finally {
            \restore_error_handler();
        }
    }
}
