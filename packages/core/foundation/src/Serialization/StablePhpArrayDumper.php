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

namespace Coretsia\Foundation\Serialization;

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\Exception\PhpArraySerializationException;

/**
 * Canonical deterministic PHP-array byte encoder.
 *
 * Baseline value normalization is delegated to JsonLikeNormalizer. Map keys
 * are sorted recursively by byte-order comparison and list order is preserved.
 * The encoded document is serialized data, not executable PHP source.
 *
 * Output uses canonical scalar spelling, string escaping, four-space
 * indentation, LF-only line endings, and exactly one final LF.
 * Schema validation and semantic value safety belong to callers.
 */
final class StablePhpArrayDumper
{
    private const string INDENT = '    ';

    /**
     * @param array<int|string, mixed> $array
     *
     * @throws PhpArraySerializationException
     */
    public function dump(array $array): string
    {
        try {
            $normalized = JsonLikeNormalizer::normalize($array);
        } catch (JsonLikeNormalizationException $exception) {
            throw PhpArraySerializationException::withReason(
                match ($exception->reason()) {
                    JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN => PhpArraySerializationException::REASON_FLOAT_FORBIDDEN,
                    JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN => PhpArraySerializationException::REASON_RESOURCE_FORBIDDEN,
                    JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN => PhpArraySerializationException::REASON_CLOSURE_FORBIDDEN,
                    JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN => PhpArraySerializationException::REASON_OBJECT_FORBIDDEN,
                    JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING => PhpArraySerializationException::REASON_MAP_KEY_MUST_BE_STRING,
                    JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN => PhpArraySerializationException::REASON_TYPE_FORBIDDEN,
                    JsonLikeNormalizationException::REASON_MAX_DEPTH_EXCEEDED => PhpArraySerializationException::REASON_MAX_DEPTH_EXCEEDED,
                    JsonLikeNormalizationException::REASON_MAX_NODES_EXCEEDED => PhpArraySerializationException::REASON_MAX_NODES_EXCEEDED,
                    JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED => PhpArraySerializationException::REASON_STRING_BYTES_EXCEEDED,
                    JsonLikeNormalizationException::REASON_TOTAL_STRING_BYTES_EXCEEDED => PhpArraySerializationException::REASON_TOTAL_STRING_BYTES_EXCEEDED,
                    default => PhpArraySerializationException::REASON_SERIALIZATION_INVALID,
                },
            );
        }

        if (!\is_array($normalized)) {
            throw PhpArraySerializationException::withReason(
                PhpArraySerializationException::REASON_TYPE_FORBIDDEN,
            );
        }

        return $this->dumpNormalized($normalized);
    }

    /**
     * Emits an already normalized array without repeating normalization.
     *
     * The caller must supply canonical json-like values with recursively
     * sorted map keys. Non-canonical map order and unsupported values fail
     * closed rather than producing a non-canonical serialization.
     *
     *
     * @param array<int|string, mixed> $array
     *
     * @throws PhpArraySerializationException
     */
    public function dumpNormalized(array $array): string
    {
        $bytes = "<?php\n\nreturn " . self::dumpValue($array, 0) . ";\n";

        return self::ensureLfOnlyWithSingleFinalNewline($bytes);
    }

    /**
     * @param array<int|string, mixed> $array
     *
     * @throws PhpArraySerializationException
     */
    public static function dumpStable(array $array): string
    {
        return new self()->dump($array);
    }

    /**
     * @throws PhpArraySerializationException
     */
    private static function dumpValue(mixed $value, int $depth): string
    {
        if ($value === null) {
            return 'null';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_int($value)) {
            return (string) $value;
        }

        if (\is_string($value)) {
            return self::dumpString($value);
        }

        if (\is_array($value)) {
            return self::dumpArrayExpression($value, $depth);
        }

        throw PhpArraySerializationException::withReason(
            PhpArraySerializationException::REASON_TYPE_FORBIDDEN,
        );
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private static function dumpArrayExpression(array $array, int $depth): string
    {
        if ($array === []) {
            return '[]';
        }

        if (\array_is_list($array)) {
            return self::dumpListExpression($array, $depth);
        }

        return self::dumpMapExpression($array, $depth);
    }

    /**
     * @param list<mixed> $list
     */
    private static function dumpListExpression(array $list, int $depth): string
    {
        $nextDepth = $depth + 1;
        $currentIndent = self::indent($depth);
        $nextIndent = self::indent($nextDepth);

        $lines = ['['];

        foreach ($list as $item) {
            $lines[] = $nextIndent . self::dumpValue($item, $nextDepth) . ',';
        }

        $lines[] = $currentIndent . ']';

        return \implode("\n", $lines);
    }

    /**
     * @param array<int|string, mixed> $map
     */
    private static function dumpMapExpression(array $map, int $depth): string
    {
        $nextDepth = $depth + 1;
        $currentIndent = self::indent($depth);
        $nextIndent = self::indent($nextDepth);

        $lines = ['['];

        $previousKey = null;

        foreach ($map as $key => $value) {
            if (!\is_string($key)) {
                throw PhpArraySerializationException::withReason(
                    PhpArraySerializationException::REASON_MAP_KEY_MUST_BE_STRING,
                );
            }

            if ($previousKey !== null && \strcmp($previousKey, $key) >= 0) {
                throw PhpArraySerializationException::withReason(
                    PhpArraySerializationException::REASON_SERIALIZATION_INVALID,
                );
            }

            $previousKey = $key;
            $lines[] = $nextIndent
                . self::dumpString($key)
                . ' => '
                . self::dumpValue($value, $nextDepth)
                . ',';
        }

        $lines[] = $currentIndent . ']';

        return \implode("\n", $lines);
    }

    /**
     * Emits a deterministic PHP double-quoted string literal.
     *
     * The output never contains raw CR/LF/TAB/control bytes. That keeps the
     * serialized document LF-only even when the represented string value contains
     * line breaks or control characters.
     */
    private static function dumpString(string $value): string
    {
        $result = '"';
        $length = \strlen($value);

        for ($index = 0; $index < $length; ++$index) {
            $byte = \ord($value[$index]);

            $result .= match ($value[$index]) {
                "\\" => "\\\\",
                "\"" => "\\\"",
                '$' => "\\$",
                "\n" => "\\n",
                "\r" => "\\r",
                "\t" => "\\t",
                default => self::dumpStringByte($byte, $value[$index]),
            };
        }

        return $result . '"';
    }

    private static function dumpStringByte(int $byte, string $character): string
    {
        if ($byte < 0x20 || $byte === 0x7F) {
            return '\\x' . \str_pad(
                \strtoupper(\dechex($byte)),
                2,
                '0',
                \STR_PAD_LEFT,
            );
        }

        return $character;
    }

    private static function indent(int $depth): string
    {
        if ($depth <= 0) {
            return '';
        }

        return \str_repeat(self::INDENT, $depth);
    }

    private static function ensureLfOnlyWithSingleFinalNewline(string $bytes): string
    {
        $normalized = \str_replace(["\r\n", "\r"], "\n", $bytes);

        return \rtrim($normalized, "\n") . "\n";
    }
}
