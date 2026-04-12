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

namespace Coretsia\Tools\Spikes\workspace;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

final class ComposerJsonCanonicalizer
{
    private function __construct()
    {
    }

    /**
     * Single canonical composer.json encoder.
     *
     * Canonical pipeline (single-choice):
     * 1) json_encode with JSON_PRETTY_PRINT (4-space baseline) + UNESCAPED_SLASHES + UNESCAPED_UNICODE + THROW_ON_ERROR
     * 2) Normalize EOL to LF
     * 3) Rewrite indentation at beginning of each line: N leading spaces must be divisible by 4, replace with N/2 spaces
     * 4) Ensure final newline
     *
     * MUST NOT reorder keys globally: uses the insertion order of the provided associative arrays.
     *
     * @throws DeterministicException
     */
    public static function encodeCanonical(array $composerJson): string
    {
        try {
            $encoded = \json_encode(
                $composerJson,
                \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES
                | \JSON_UNESCAPED_UNICODE
                | \JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $e) {
            self::failEncode($e);
        }

        if (!\is_string($encoded)) {
            self::failEncode();
        }

        // 2) Normalize EOL to LF (must not use str_replace/preg_replace for CR/LF normalization due to spikes IO policy gate)
        $encoded = self::normalizeEolToLf($encoded);

        // 3) Rewrite indentation only at beginning of each line (no touching JSON string contents)
        $lines = \explode("\n", $encoded);
        $n = \count($lines);

        for ($i = 0; $i < $n; $i++) {
            $line = (string) $lines[$i];

            // json_encode(JSON_PRETTY_PRINT) emits no empty lines, but keep it safe.
            if ($line === '') {
                continue;
            }

            $len = \strlen($line);
            $leadingSpaces = 0;

            // Count leading spaces; any leading tab/other whitespace is a deterministic invariant violation.
            for ($j = 0; $j < $len; $j++) {
                $ch = $line[$j];

                if ($ch === ' ') {
                    $leadingSpaces++;
                    continue;
                }

                // Only spaces are permitted in indentation; any other leading whitespace is an invariant violation.
                if ($ch === "\t" || $ch === "\v" || $ch === "\f") {
                    self::failEncode();
                }

                break;
            }

            if (($leadingSpaces % 4) !== 0) {
                self::failEncode();
            }

            $newLeadingSpaces = intdiv($leadingSpaces, 2);
            if ($newLeadingSpaces === $leadingSpaces) {
                continue; // covers 0 as well
            }

            $lines[$i] = \str_repeat(' ', $newLeadingSpaces) . \substr($line, $leadingSpaces);
        }

        $out = \implode("\n", $lines);

        // 4) Ensure final newline
        if ($out === '' || !\str_ends_with($out, "\n")) {
            $out .= "\n";
        }

        return $out;
    }

    /**
     * Normalize CRLF and CR newlines to LF without using str_replace/preg_replace.
     *
     * Single-choice semantics:
     * - "\r\n" -> "\n"
     * - "\r"   -> "\n"
     */
    private static function normalizeEolToLf(string $content): string
    {
        $len = \strlen($content);
        if ($len === 0) {
            return $content;
        }

        $out = '';
        $i = 0;

        while ($i < $len) {
            $ch = $content[$i];

            if ($ch !== "\r") {
                $out .= $ch;
                $i++;
                continue;
            }

            // "\r\n" => "\n" (consume both)
            if (($i + 1) < $len && $content[$i + 1] === "\n") {
                $out .= "\n";
                $i += 2;
                continue;
            }

            // "\r" => "\n"
            $out .= "\n";
            $i++;
        }

        return $out;
    }

    /**
     * @throws DeterministicException
     */
    private static function failEncode(?\Throwable $previous = null): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_WORKSPACE_COMPOSER_JSON_ENCODE_FAILED,
            ErrorCodes::CORETSIA_WORKSPACE_COMPOSER_JSON_ENCODE_FAILED,
            $previous,
        );
    }
}
