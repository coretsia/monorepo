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
 * Deterministic composer.json reader and canonical encoder for repository tools.
 *
 * Object key order is preserved. Nested JSON objects are represented as
 * associative arrays when lossless; objects that cannot be represented
 * losslessly as PHP arrays are preserved as stdClass instances.
 *
 * Callers remain responsible for intentional semantic ordering changes such as
 * rebuilding a require/require-dev block.
 */
final class ComposerJson
{
    private function __construct()
    {
    }

    /**
     * @return array<string,mixed>
     */
    public static function readObject(string $path): array
    {
        return self::decodeObject(DeterministicFile::readBytesExact($path));
    }

    /**
     * @return array<string,mixed>
     */
    public static function decodeObject(string $json): array
    {
        if (str_starts_with($json, "\xEF\xBB\xBF")) {
            $json = substr($json, 3);
        }

        try {
            $data = json_decode(
                self::normalizeEol($json),
                false,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw new \RuntimeException('composer-json-invalid');
        }

        if (!$data instanceof \stdClass) {
            throw new \RuntimeException('composer-json-object-invalid');
        }

        return self::decodeTopLevelObject($data);
    }

    /**
     * Canonical composer.json encoding used by repository tooling:
     * - UTF-8 text
     * - LF-only + final newline
     * - 2-space indentation
     * - object key order preserved
     * - floats forbidden
     *
     * @param array<string,mixed> $data
     */
    public static function encodeCanonical(array $data): string
    {
        if ($data !== [] && array_is_list($data)) {
            throw new \RuntimeException('composer-json-object-invalid');
        }

        return self::normalizeToLfFinalNewline(
            self::encodeJsonObject($data, 0, 2),
        );
    }

    public static function normalizeEol(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    public static function normalizeToLfFinalNewline(string $content): string
    {
        $content = self::normalizeEol($content);

        if (!str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        return $content;
    }

    /**
     * @return array<string,mixed>
     */
    private static function decodeTopLevelObject(\stdClass $object): array
    {
        $out = [];

        foreach (new \ReflectionObject($object)->getProperties() as $property) {
            $key = $property->getName();
            $value = $property->getValue($object);

            if (self::arrayKeyWouldBecomeInteger($key)) {
                throw new \RuntimeException('composer-json-object-key-invalid');
            }

            $out[$key] = self::normalizeDecodedValue($value);
        }

        return $out;
    }

    private static function normalizeDecodedValue(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $out = [];
            $preserveObject = false;
            $hasProperties = false;

            foreach (new \ReflectionObject($value)->getProperties() as $property) {
                $key = $property->getName();
                $item = $property->getValue($value);
                $hasProperties = true;

                if (self::arrayKeyWouldBecomeInteger($key)) {
                    $preserveObject = true;
                }

                $out[$key] = self::normalizeDecodedValue($item);
            }

            if (!$hasProperties || $preserveObject) {
                $object = new \stdClass();

                foreach (new \ReflectionObject($value)->getProperties() as $property) {
                    $key = $property->getName();
                    $item = $property->getValue($value);
                    $object->{$key} = self::normalizeDecodedValue($item);
                }

                return $object;
            }

            return $out;
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $item) {
                $out[] = self::normalizeDecodedValue($item);
            }

            return $out;
        }

        return $value;
    }

    private static function arrayKeyWouldBecomeInteger(string $key): bool
    {
        $probe = [];
        $probe[$key] = true;

        return is_int(array_key_first($probe));
    }

    private static function encodeJsonValue(mixed $value, int $level, int $indentSize): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            throw new \RuntimeException('composer-json-float-forbidden');
        }

        if (is_string($value)) {
            return '"' . self::escapeJsonString($value) . '"';
        }

        if ($value instanceof \stdClass) {
            return self::encodeJsonObject($value, $level, $indentSize);
        }

        if (!is_array($value)) {
            throw new \RuntimeException('composer-json-type-unsupported');
        }

        $indent = str_repeat(' ', $level * $indentSize);
        $childIndent = str_repeat(' ', ($level + 1) * $indentSize);

        if (array_is_list($value)) {
            if ($value === []) {
                return '[]';
            }

            $parts = [];

            foreach ($value as $item) {
                $parts[] = $childIndent . self::encodeJsonValue($item, $level + 1, $indentSize);
            }

            return "[\n" . implode(",\n", $parts) . "\n" . $indent . ']';
        }

        return self::encodeJsonObject($value, $level, $indentSize);
    }

    private static function encodeJsonObject(array|\stdClass $value, int $level, int $indentSize): string
    {
        $indent = str_repeat(' ', $level * $indentSize);
        $childIndent = str_repeat(' ', ($level + 1) * $indentSize);
        $parts = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \RuntimeException('composer-json-object-key-invalid');
            }

            $parts[] = $childIndent
                . '"'
                . self::escapeJsonString($key)
                . '": '
                . self::encodeJsonValue($item, $level + 1, $indentSize);
        }

        if ($parts === []) {
            return '{}';
        }

        return "{\n" . implode(",\n", $parts) . "\n" . $indent . '}';
    }

    private static function escapeJsonString(string $value): string
    {
        if (preg_match('//u', $value) !== 1) {
            throw new \RuntimeException('composer-json-string-invalid');
        }

        $out = '';
        $len = strlen($value);

        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];
            $ord = ord($char);

            if ($char === '"') {
                $out .= '\\"';
                continue;
            }

            if ($char === '\\') {
                $out .= '\\\\';
                continue;
            }

            if ($ord === 8) {
                $out .= '\\b';
                continue;
            }

            if ($ord === 12) {
                $out .= '\\f';
                continue;
            }

            if ($ord === 10) {
                $out .= '\\n';
                continue;
            }

            if ($ord === 13) {
                $out .= '\\r';
                continue;
            }

            if ($ord === 9) {
                $out .= '\\t';
                continue;
            }

            if ($ord < 0x20) {
                $out .= sprintf('\\u%04x', $ord);
                continue;
            }

            $out .= $char;
        }

        $out = str_replace("\u{2028}", '\\u2028', $out);
        $out = str_replace("\u{2029}", '\\u2029', $out);

        return $out;
    }
}
