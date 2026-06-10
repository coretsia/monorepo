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

namespace Coretsia\Kernel\Artifacts\Php;

use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;

/**
 * Deterministic PHP array artifact dumper.
 *
 * This dumper emits PHP files that return a single array expression:
 *
 *     <?php
 *
 *     return [
 *         ...
 *     ];
 *
 * The emitted artifact bytes are intentionally narrow:
 *
 * - no generated comments;
 * - no timestamps;
 * - no tool versions;
 * - no absolute paths;
 * - no hostnames;
 * - no user names;
 * - no process-specific data;
 * - LF-only output;
 * - exactly one final newline.
 *
 * Input normalization is delegated to PayloadNormalizer, which in turn delegates
 * baseline json-like normalization to Foundation. Therefore map ordering, list
 * ordering, scalar preservation, and rejected-type semantics remain aligned
 * with the canonical stable JSON rules.
 *
 * The dumper does not validate artifact envelope semantics. It emits the array
 * it receives after normalization and MUST NOT wrap the envelope in another root
 * key. Envelope construction belongs to ArtifactEnvelopeFactory, and envelope
 * validation belongs to ArtifactSchemaValidator.
 *
 * @internal
 */
final class StablePhpArrayDumper
{
    private const string INDENT = '    ';

    public function __construct(
        private readonly PayloadNormalizer $payloadNormalizer = new PayloadNormalizer(),
    ) {
    }

    /**
     * Dumps a PHP file returning the normalized array.
     *
     * @param array<int|string, mixed> $array
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function dump(array $array): string
    {
        return $this->dumpArray($array);
    }

    /**
     * Dumps a PHP file returning the normalized canonical envelope array.
     *
     * This method intentionally does not wrap the envelope in another root key.
     * The returned PHP file returns the normalized envelope array unchanged.
     *
     * @param array<int|string, mixed> $envelope
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function dumpEnvelope(array $envelope): string
    {
        return $this->dumpArray($envelope);
    }

    /**
     * Static convenience wrapper for deterministic PHP array dumping.
     *
     * @param array<int|string, mixed> $array
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public static function dumpStable(array $array): string
    {
        return new self()->dumpArray($array);
    }

    /**
     * Static convenience wrapper for deterministic PHP envelope dumping.
     *
     * @param array<int|string, mixed> $envelope
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public static function dumpStableEnvelope(array $envelope): string
    {
        return new self()->dumpEnvelope($envelope);
    }

    /**
     * @param array<int|string, mixed> $array
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private function dumpArray(array $array): string
    {
        $normalized = $this->payloadNormalizer->normalize($array, 'artifact');

        if (!\is_array($normalized)) {
            throw ArtifactPayloadInvalidException::atPath(
                'artifact',
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        $bytes = "<?php\n\nreturn " . self::dumpValue($normalized, 0) . ";\n";

        return self::ensureLfOnlyWithSingleFinalNewline($bytes);
    }

    /**
     * @param null|bool|int|string|array<int|string, mixed> $value
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
            return (string)$value;
        }

        if (\is_string($value)) {
            return self::dumpString($value);
        }

        if (\is_array($value)) {
            return self::dumpArrayExpression($value, $depth);
        }

        /*
         * This point should be unreachable because PayloadNormalizer rejects
         * non-json-like values before dumping starts.
         */
        throw ArtifactPayloadInvalidException::atPath(
            'artifact',
            ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
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
     * @param array<string, mixed> $map
     */
    private static function dumpMapExpression(array $map, int $depth): string
    {
        $nextDepth = $depth + 1;
        $currentIndent = self::indent($depth);
        $nextIndent = self::indent($nextDepth);

        $lines = ['['];

        foreach ($map as $key => $value) {
            if (!\is_string($key)) {
                throw ArtifactPayloadInvalidException::atPath(
                    'artifact',
                    ArtifactPayloadInvalidException::REASON_MAP_KEY_MUST_BE_STRING,
                );
            }

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
     * generated artifact LF-only even when the represented string value contains
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
