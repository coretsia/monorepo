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

namespace Coretsia\Devtools\InternalToolkit;

final class Slug
{
    private function __construct()
    {
    }

    /**
     * Converts a slug-like identifier (kebab/snake/dot/space separated) to StudlyCase.
     *
     * Examples:
     *  - "cli-spikes" => "CliSpikes"
     *  - "internal_toolkit" => "InternalToolkit"
     *  - "foo.bar-baz" => "FooBarBaz"
     */
    public static function toStudly(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        $parts = preg_split('/[^A-Za-z0-9]+/', $slug, -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        $out = '';
        foreach ($parts as $part) {
            $part = self::asciiLower($part);
            $out .= self::asciiUpperFirst($part);
        }

        return $out;
    }

    /**
     * Converts an identifier (StudlyCase / kebab / snake / dot separated) to snake_case (lowercase, underscores).
     *
     * Examples:
     *  - "CliSpikes" => "cli_spikes"
     *  - "JSONEncoder" => "json_encoder"
     *  - "cli-spikes" => "cli_spikes"
     */
    public static function toSnake(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        // Normalize common separators to underscores.
        $s = preg_replace('/[.\-\/\s\\\\]+/', '_', $slug) ?? $slug;

        // Insert underscores at word boundaries for StudlyCase / camelCase.
        // Handle acronym boundaries: "JSONEncoder" => "JSON_Encoder"
        $s = preg_replace('/([A-Z]+)([A-Z][a-z0-9])/', '$1_$2', $s) ?? $s;
        // Handle lower-to-upper: "cliSpikes" => "cli_Spikes"
        $s = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $s) ?? $s;

        $s = self::asciiLower($s);
        $s = preg_replace('/_+/', '_', $s) ?? $s;
        $s = trim($s, '_');

        return $s;
    }

    private static function asciiLower(string $s): string
    {
        // Locale-independent ASCII lowering.
        return preg_replace_callback(
            '/[A-Z]/',
            static fn(array $m): string => chr(ord($m[0]) + 32),
            $s
        ) ?? $s;
    }

    private static function asciiUpperFirst(string $s): string
    {
        if ($s === '') {
            return '';
        }

        $first = $s[0];
        if ($first >= 'a' && $first <= 'z') {
            $first = chr(ord($first) - 32);
        }

        return $first . substr($s, 1);
    }
}
