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

namespace Coretsia\Platform\Cli\Output;

use Coretsia\Contracts\Cli\Output\OutputInterface;

/**
 * Phase 0 deterministic + prod-safe CLI output implementation.
 *
 * Guarantees:
 * - Always ends output with a single "\n"
 * - Redacts secret-like keys in text and JSON when enabled
 * - Scrubs absolute-path-like fragments (Windows drive/UNC, /home/, /Users/)
 *
 * @internal
 */
final class CliOutput implements OutputInterface
{
    public const string REDACTED_PLACEHOLDER = '<redacted>';
    public const string PATH_PLACEHOLDER = '<redacted-path>';

    /** @var list<string> */
    private const array SECRET_LIKE_SUBSTRINGS = [
        'TOKEN',
        'PASSWORD',
        'PASS',
        'SECRET',
        'AUTH',
        'COOKIE',
        'SESSION',
        'KEY',
        'PRIVATE',
    ];

    /** @var \Closure(string): void */
    private readonly \Closure $stdoutWriter;

    /** @var \Closure(string): void */
    private readonly \Closure $stderrWriter;

    private readonly bool $redactionEnabled;

    /**
     * @param \Closure(string): void|null $stdoutWriter
     * @param \Closure(string): void|null $stderrWriter
     */
    public function __construct(
        bool      $redactionEnabled = true,
        ?\Closure $stdoutWriter = null,
        ?\Closure $stderrWriter = null,
    )
    {
        $this->redactionEnabled = $redactionEnabled;

        $this->stdoutWriter = $stdoutWriter ?? static function (string $chunk): void {
            // STDOUT is always available in CLI SAPIs (Phase 0 assumption).
            \fwrite(\STDOUT, $chunk);
        };

        $this->stderrWriter = $stderrWriter ?? static function (string $chunk): void {
            \fwrite(\STDERR, $chunk);
        };
    }

    public function text(string $text): void
    {
        ($this->stdoutWriter)($this->finalizeText($this->sanitizeText($text)));
    }

    public function json(array $payload): void
    {
        $value = $payload;

        if ($this->redactionEnabled) {
            $value = $this->redactJson($value);
        }

        $value = $this->normalizeJson($value);
        $value = $this->scrubAbsolutePathsInJson($value);

        $json = \json_encode(
            $value,
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );

        ($this->stdoutWriter)($this->finalizeText($json));
    }

    public function error(string $code, string $message): void
    {
        // Normalized error record: 2 lines, deterministic, safe.
        ($this->stderrWriter)($this->finalizeText($this->sanitizeText($code)));
        ($this->stderrWriter)($this->finalizeText($this->sanitizeText($message)));
    }

    private function finalizeText(string $text): string
    {
        // Ensure exactly one trailing "\n" regardless of input.
        $normalized = \rtrim($text, "\r\n");

        return $normalized . "\n";
    }

    private function sanitizeText(string $text): string
    {
        $out = $text;

        if ($this->redactionEnabled) {
            $out = $this->redactText($out);
        }

        // Safety net: never leak absolute paths (including in errors).
        $out = $this->scrubAbsolutePaths($out);

        return $out;
    }

    private function redactText(string $text): string
    {
        $out = $text;

        // Authorization header redaction (case-insensitive).
        $out = (string)\preg_replace(
            '~Authorization\s*:\s*[^\r\n]*~i',
            'Authorization: ' . self::REDACTED_PLACEHOLDER,
            $out,
        );

        // KEY=VALUE redaction where KEY is secret-like (case-insensitive).
        $out = (string)\preg_replace_callback(
            '~([A-Za-z0-9_.-]*?(?:TOKEN|PASSWORD|PASS|SECRET|AUTH|COOKIE|SESSION|KEY|PRIVATE)[A-Za-z0-9_.-]*)\s*=\s*([^\s\r\n]+)~i',
            static function (array $m): string {
                // Preserve the key as-seen; replace value deterministically.
                return $m[1] . '=' . self::REDACTED_PLACEHOLDER;
            },
            $out,
        );

        return $out;
    }

    private function scrubAbsolutePaths(string $text): string
    {
        $out = $text;

        // Windows drive paths: C:\..., D:/...
        $out = (string)\preg_replace(
            '~[A-Za-z]:[\\\\/][^\s\r\n]*~',
            self::PATH_PLACEHOLDER,
            $out,
        );

        // Windows UNC paths: \\server\share\...
        $out = (string)\preg_replace(
            '~\\\\\\\\[^\s\r\n]+~',
            self::PATH_PLACEHOLDER,
            $out,
        );

        // Unix home paths:
        $out = (string)\preg_replace(
            '~/home/[^\s\r\n]*~',
            self::PATH_PLACEHOLDER,
            $out,
        );

        // macOS user paths:
        $out = (string)\preg_replace(
            '~/Users/[^\s\r\n]*~',
            self::PATH_PLACEHOLDER,
            $out,
        );

        return $out;
    }

    /**
     * JSON redaction (key-based, recursive).
     *
     * @param array<string, mixed>|list<mixed> $payload
     * @return array<string, mixed>|list<mixed>
     */
    private function redactJson(array $payload): array
    {
        if (\array_is_list($payload)) {
            $out = [];

            foreach ($payload as $v) {
                $out[] = \is_array($v) ? $this->redactJson($v) : $v;
            }

            return $out;
        }

        $out = [];

        foreach ($payload as $k => $v) {
            $key = \is_string($k) ? $k : (string)$k;

            if ($this->isSecretLikeKey($key)) {
                $out[$k] = self::REDACTED_PLACEHOLDER;
                continue;
            }

            $out[$k] = \is_array($v) ? $this->redactJson($v) : $v;
        }

        return $out;
    }

    private function isSecretLikeKey(string $key): bool
    {
        foreach (self::SECRET_LIKE_SUBSTRINGS as $needle) {
            if (\stripos($key, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize JSON deterministically:
     * - maps: sort keys recursively by strcmp() byte-order
     * - lists: preserve order
     *
     * @param array<string, mixed>|list<mixed> $payload
     * @return array<string, mixed>|list<mixed>
     */
    private function normalizeJson(array $payload): array
    {
        if (\array_is_list($payload)) {
            $out = [];

            foreach ($payload as $v) {
                $out[] = \is_array($v) ? $this->normalizeJson($v) : $v;
            }

            return $out;
        }

        $out = $payload;

        \uksort(
            $out,
            static fn($a, $b): int => \strcmp((string)$a, (string)$b),
        );

        foreach ($out as $k => $v) {
            if (\is_array($v)) {
                $out[$k] = $this->normalizeJson($v);
            }
        }

        return $out;
    }

    /**
     * Optional safety net: scrub absolute paths in JSON string values, recursively.
     *
     * @param array<string, mixed>|list<mixed> $payload
     * @return array<string, mixed>|list<mixed>
     */
    private function scrubAbsolutePathsInJson(array $payload): array
    {
        if (\array_is_list($payload)) {
            $out = [];

            foreach ($payload as $v) {
                if (\is_array($v)) {
                    $out[] = $this->scrubAbsolutePathsInJson($v);
                    continue;
                }

                $out[] = \is_string($v) ? $this->scrubAbsolutePaths($v) : $v;
            }

            return $out;
        }

        $out = [];

        foreach ($payload as $k => $v) {
            if (\is_array($v)) {
                $out[$k] = $this->scrubAbsolutePathsInJson($v);
                continue;
            }

            $out[$k] = \is_string($v) ? $this->scrubAbsolutePaths($v) : $v;
        }

        return $out;
    }
}
