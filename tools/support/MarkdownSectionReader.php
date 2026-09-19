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

/**
 * Minimal deterministic Markdown section extraction for repository tooling.
 *
 * This class intentionally owns heading-boundary mechanics only. Table, list,
 * policy, and domain-specific parsing remain with the callers.
 */
final class MarkdownSectionReader
{
    private function __construct()
    {
    }

    public static function section(string $markdown, string $heading): ?string
    {
        $heading = trim($heading);

        if (preg_match('/\A(#{1,6})[ \t]+\S.*\z/', $heading, $match) !== 1) {
            throw new \RuntimeException('markdown-section-heading-invalid');
        }

        $headingLevel = strlen($match[1]);
        $normalized = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $normalized);
        $bodyStart = null;
        $fenceChar = null;
        $fenceLength = 0;

        foreach ($lines as $index => $line) {
            $fence = self::fenceMarker($line);

            if ($fence !== null) {
                if ($fenceChar === null) {
                    $fenceChar = $fence['char'];
                    $fenceLength = $fence['length'];
                } elseif (
                    $fence['char'] === $fenceChar
                    && $fence['length'] >= $fenceLength
                    && $fence['tail'] === ''
                ) {
                    $fenceChar = null;
                    $fenceLength = 0;
                }

                continue;
            }

            if ($fenceChar !== null) {
                continue;
            }

            if (
                preg_match('/\A {0,3}(#{1,6})[ \t]+\S.*\z/', $line, $lineMatch) !== 1
                || trim($line) !== $heading
            ) {
                continue;
            }

            $bodyStart = $index + 1;
            break;
        }

        if ($bodyStart === null) {
            return null;
        }

        $body = [];
        $count = count($lines);
        $fenceChar = null;
        $fenceLength = 0;

        for ($i = $bodyStart; $i < $count; $i++) {
            $line = $lines[$i];
            $fence = self::fenceMarker($line);

            if ($fence !== null) {
                if ($fenceChar === null) {
                    $fenceChar = $fence['char'];
                    $fenceLength = $fence['length'];
                } elseif (
                    $fence['char'] === $fenceChar
                    && $fence['length'] >= $fenceLength
                    && $fence['tail'] === ''
                ) {
                    $fenceChar = null;
                    $fenceLength = 0;
                }

                $body[] = $line;
                continue;
            }

            if (
                $fenceChar === null
                && preg_match('/\A {0,3}(#{1,6})[ \t]+\S/', $line, $lineMatch) === 1
                && strlen($lineMatch[1]) <= $headingLevel
            ) {
                break;
            }

            $body[] = $line;
        }

        return implode("\n", $body);
    }

    /**
     * @return array{char:string,length:int,tail:string}|null
     */
    private static function fenceMarker(string $line): ?array
    {
        if (preg_match('/\A {0,3}(`{3,}|~{3,})(.*)\z/', $line, $match) !== 1) {
            return null;
        }

        return [
            'char' => $match[1][0],
            'length' => strlen($match[1]),
            'tail' => trim($match[2]),
        ];
    }
}
