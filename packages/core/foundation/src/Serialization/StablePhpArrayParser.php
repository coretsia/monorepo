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

use Coretsia\Foundation\Serialization\Exception\PhpArraySerializationException;

/**
 * Decoder for the canonical deterministic PHP-array serialization.
 *
 * StablePhpArrayParser is the decoding counterpart of StablePhpArrayDumper.
 * It understands only the canonical byte grammar emitted by that dumper and
 * treats PHP-looking source strictly as serialized data. It does not
 * invoke the PHP lexer, evaluator, include/require machinery, or runtime source
 * execution while decoding.
 *
 * Accepted values are limited to the serialization domain emitted by the
 * dumper: null, bool, int, string, list, and string-keyed map values. Layout,
 * indentation, string escapes, integer spelling, map ordering, separators, and
 * the document prefix/suffix are all canonical and fail closed when they drift.
 *
 * Diagnostics are intentionally safe. Serialization failures expose only the
 * stable reason token and never include source bytes, literals, offsets,
 * paths, or parser state. Both entrypoints use the same iterative decoder.
 * Bounded parsing accounts for container depth, entries, and decoded string
 * bytes (including map keys) before allocating them.
 */
final class StablePhpArrayParser
{
    private const string DOCUMENT_PREFIX = "<?php\n\nreturn ";
    private const string DOCUMENT_SUFFIX = ";\n";
    private const string INDENT = '    ';

    /**
     * @return array<int|string, mixed>
     *
     * @throws PhpArraySerializationException
     */
    public function parse(string $bytes): array
    {
        return self::parseDocument($bytes, null);
    }

    /**
     * Decodes the canonical grammar within the supplied json-like budget.
     *
     * @return array<int|string, mixed>
     *
     * @throws PhpArraySerializationException
     */
    public function parseBounded(
        string $bytes,
        JsonLikeNormalizationLimits $limits,
    ): array {
        return self::parseDocument($bytes, $limits);
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws PhpArraySerializationException
     */
    private static function parseDocument(
        string $bytes,
        ?JsonLikeNormalizationLimits $limits,
    ): array {
        $nodeCount = 0;
        $totalStringBytes = 0;
        $cursor = 0;
        $length = \strlen($bytes);

        self::expectLiteral(
            bytes: $bytes,
            cursor: $cursor,
            literal: self::DOCUMENT_PREFIX,
        );

        if (!self::startsWithAt($bytes, $cursor, '[')) {
            self::invalid();
        }

        $root = null;

        /**
         * @var list<array{
         *     depth: int,
         *     kind: null|'list'|'map',
         *     values: array<int|string, mixed>,
         *     previousKey: null|string,
         *     hasEntries: bool,
         *     state: 'entry-or-close'|'await-child',
         *     pendingKind: null|'list'|'map',
         *     pendingKey: null|string
         * }> $stack
         */
        $stack = [];

        $initial = self::beginArray(
            bytes: $bytes,
            cursor: $cursor,
            depth: 0,
            limits: $limits,
        );

        if ($initial['complete']) {
            if ($initial['value'] === null) {
                self::invalid();
            }

            $root = $initial['value'];
        } else {
            if ($initial['frame'] === null) {
                self::invalid();
            }

            $stack[] = $initial['frame'];
        }

        while ($root === null) {
            if ($stack === []) {
                self::invalid();
            }

            $frameIndex = \count($stack) - 1;
            $frame = &$stack[$frameIndex];

            if ($frame['state'] === 'await-child') {
                self::invalid();
            }

            if (
                $frame['hasEntries']
                && self::startsWithAt(
                    $bytes,
                    $cursor,
                    self::indent($frame['depth']) . ']',
                )
            ) {
                self::expectLiteral(
                    bytes: $bytes,
                    cursor: $cursor,
                    literal: self::indent($frame['depth']) . ']',
                );

                $completed = $frame['values'];
                \array_pop($stack);

                if ($stack === []) {
                    $root = $completed;

                    continue;
                }

                self::attachCompletedChild(
                    stack: $stack,
                    value: $completed,
                    bytes: $bytes,
                    cursor: $cursor,
                );

                continue;
            }

            self::consumeNode($limits, $nodeCount);

            self::expectLiteral(
                bytes: $bytes,
                cursor: $cursor,
                literal: self::indent($frame['depth'] + 1),
            );

            if ($frame['kind'] === 'map') {
                if (!self::startsWithAt($bytes, $cursor, '"')) {
                    self::invalid();
                }

                $key = self::parseString($bytes, $cursor, $limits, $totalStringBytes);
                self::assertCanonicalMapKey(
                    key: $key,
                    previousKey: $frame['previousKey'],
                );
                self::expectLiteral(
                    bytes: $bytes,
                    cursor: $cursor,
                    literal: ' => ',
                );

                $frame['previousKey'] = $key;

                self::parseAndAttachValue(
                    stack: $stack,
                    frameIndex: $frameIndex,
                    valueDepth: $frame['depth'] + 1,
                    pendingKind: 'map',
                    pendingKey: $key,
                    bytes: $bytes,
                    cursor: $cursor,
                    limits: $limits,
                    totalStringBytes: $totalStringBytes,
                );

                continue;
            }

            if ($frame['kind'] === null && self::startsWithAt($bytes, $cursor, '"')) {
                $string = self::parseString($bytes, $cursor, $limits, $totalStringBytes);

                if (self::startsWithAt($bytes, $cursor, ' => ')) {
                    self::assertCanonicalMapKey(
                        key: $string,
                        previousKey: null,
                    );
                    self::expectLiteral(
                        bytes: $bytes,
                        cursor: $cursor,
                        literal: ' => ',
                    );

                    $frame['kind'] = 'map';
                    $frame['previousKey'] = $string;

                    self::parseAndAttachValue(
                        stack: $stack,
                        frameIndex: $frameIndex,
                        valueDepth: $frame['depth'] + 1,
                        pendingKind: 'map',
                        pendingKey: $string,
                        bytes: $bytes,
                        cursor: $cursor,
                        limits: $limits,
                        totalStringBytes: $totalStringBytes,
                    );

                    continue;
                }

                $frame['kind'] = 'list';
                $frame['values'][] = $string;
                $frame['hasEntries'] = true;

                self::expectLiteral(
                    bytes: $bytes,
                    cursor: $cursor,
                    literal: ",\n",
                );

                continue;
            }

            if ($frame['kind'] === null) {
                $frame['kind'] = 'list';
            }

            self::parseAndAttachValue(
                stack: $stack,
                frameIndex: $frameIndex,
                valueDepth: $frame['depth'] + 1,
                pendingKind: 'list',
                pendingKey: null,
                bytes: $bytes,
                cursor: $cursor,
                limits: $limits,
                totalStringBytes: $totalStringBytes,
            );
        }

        self::expectLiteral(
            bytes: $bytes,
            cursor: $cursor,
            literal: self::DOCUMENT_SUFFIX,
        );

        if ($cursor !== $length) {
            self::invalid();
        }

        if (!\is_array($root)) {
            self::invalid();
        }

        return $root;
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws PhpArraySerializationException
     */
    public static function parseStable(string $bytes): array
    {
        return new self()->parse($bytes);
    }

    /**
     * @return array{
     *     complete: true,
     *     value: array{},
     *     frame: null
     * }|array{
     *     complete: false,
     *     value: null,
     *     frame: array{
     *         depth: int,
     *         kind: null,
     *         values: array{},
     *         previousKey: null,
     *         hasEntries: false,
     *         state: 'entry-or-close',
     *         pendingKind: null,
     *         pendingKey: null
     *     }
     * }
     *
     * @throws PhpArraySerializationException
     */
    private static function beginArray(
        string $bytes,
        int &$cursor,
        int $depth,
        ?JsonLikeNormalizationLimits $limits,
    ): array {
        if ($limits !== null && $depth >= $limits->maxDepth) {
            self::invalid(PhpArraySerializationException::REASON_MAX_DEPTH_EXCEEDED);
        }

        if (self::startsWithAt($bytes, $cursor, '[]')) {
            $cursor += 2;

            return [
                'complete' => true,
                'value' => [],
                'frame' => null,
            ];
        }

        self::expectLiteral(
            bytes: $bytes,
            cursor: $cursor,
            literal: "[\n",
        );

        return [
            'complete' => false,
            'value' => null,
            'frame' => [
                'depth' => $depth,
                'kind' => null,
                'values' => [],
                'previousKey' => null,
                'hasEntries' => false,
                'state' => 'entry-or-close',
                'pendingKind' => null,
                'pendingKey' => null,
            ],
        ];
    }

    /**
     * @param list<array{
     *     depth: int,
     *     kind: null|'list'|'map',
     *     values: array<int|string, mixed>,
     *     previousKey: null|string,
     *     hasEntries: bool,
     *     state: 'entry-or-close'|'await-child',
     *     pendingKind: null|'list'|'map',
     *     pendingKey: null|string
     * }> $stack
     * @param 'list'|'map' $pendingKind
     *
     * @throws PhpArraySerializationException
     */
    private static function parseAndAttachValue(
        array &$stack,
        int $frameIndex,
        int $valueDepth,
        string $pendingKind,
        ?string $pendingKey,
        string $bytes,
        int &$cursor,
        ?JsonLikeNormalizationLimits $limits,
        int &$totalStringBytes,
    ): void {
        if (self::startsWithAt($bytes, $cursor, '[')) {
            $array = self::beginArray(
                bytes: $bytes,
                cursor: $cursor,
                depth: $valueDepth,
                limits: $limits,
            );

            if ($array['complete']) {
                self::attachValue(
                    stack: $stack,
                    frameIndex: $frameIndex,
                    pendingKind: $pendingKind,
                    pendingKey: $pendingKey,
                    value: $array['value'],
                );
                self::expectLiteral(
                    bytes: $bytes,
                    cursor: $cursor,
                    literal: ",\n",
                );

                return;
            }

            $frame = &$stack[$frameIndex];
            $frame['state'] = 'await-child';
            $frame['pendingKind'] = $pendingKind;
            $frame['pendingKey'] = $pendingKey;
            if ($array['frame'] === null) {
                self::invalid();
            }

            $stack[] = $array['frame'];

            return;
        }

        $value = self::parseScalar($bytes, $cursor, $limits, $totalStringBytes);

        self::attachValue(
            stack: $stack,
            frameIndex: $frameIndex,
            pendingKind: $pendingKind,
            pendingKey: $pendingKey,
            value: $value,
        );
        self::expectLiteral(
            bytes: $bytes,
            cursor: $cursor,
            literal: ",\n",
        );
    }

    /**
     * @param list<array{
     *     depth: int,
     *     kind: null|'list'|'map',
     *     values: array<int|string, mixed>,
     *     previousKey: null|string,
     *     hasEntries: bool,
     *     state: 'entry-or-close'|'await-child',
     *     pendingKind: null|'list'|'map',
     *     pendingKey: null|string
     * }> $stack
     *
     * @throws PhpArraySerializationException
     */
    private static function attachCompletedChild(
        array &$stack,
        array $value,
        string $bytes,
        int &$cursor,
    ): void {
        $frameIndex = \count($stack) - 1;
        $frame = &$stack[$frameIndex];

        if (
            $frame['state'] !== 'await-child'
            || $frame['pendingKind'] === null
        ) {
            self::invalid();
        }

        self::attachValue(
            stack: $stack,
            frameIndex: $frameIndex,
            pendingKind: $frame['pendingKind'],
            pendingKey: $frame['pendingKey'],
            value: $value,
        );
        self::expectLiteral(
            bytes: $bytes,
            cursor: $cursor,
            literal: ",\n",
        );
    }

    /**
     * @param list<array{
     *     depth: int,
     *     kind: null|'list'|'map',
     *     values: array<int|string, mixed>,
     *     previousKey: null|string,
     *     hasEntries: bool,
     *     state: 'entry-or-close'|'await-child',
     *     pendingKind: null|'list'|'map',
     *     pendingKey: null|string
     * }> $stack
     * @param 'list'|'map' $pendingKind
     */
    private static function attachValue(
        array &$stack,
        int $frameIndex,
        string $pendingKind,
        ?string $pendingKey,
        mixed $value,
    ): void {
        $frame = &$stack[$frameIndex];

        if ($pendingKind === 'list') {
            $frame['values'][] = $value;
        } elseif ($pendingKind === 'map' && $pendingKey !== null) {
            $frame['values'][$pendingKey] = $value;
        } else {
            self::invalid();
        }

        $frame['hasEntries'] = true;
        $frame['state'] = 'entry-or-close';
        $frame['pendingKind'] = null;
        $frame['pendingKey'] = null;
    }

    /**
     * @return null|bool|int|string
     *
     * @throws PhpArraySerializationException
     */
    private static function parseScalar(
        string $bytes,
        int &$cursor,
        ?JsonLikeNormalizationLimits $limits,
        int &$totalStringBytes,
    ): null|bool|int|string {
        $byte = $bytes[$cursor] ?? null;

        if ($byte === 'n') {
            self::expectLiteral($bytes, $cursor, 'null');

            return null;
        }

        if ($byte === 't') {
            self::expectLiteral($bytes, $cursor, 'true');

            return true;
        }

        if ($byte === 'f') {
            self::expectLiteral($bytes, $cursor, 'false');

            return false;
        }

        if ($byte === '"') {
            return self::parseString($bytes, $cursor, $limits, $totalStringBytes);
        }

        if ($byte === '-' || ($byte !== null && $byte >= '0' && $byte <= '9')) {
            return self::parseInteger($bytes, $cursor);
        }

        self::invalid();
    }

    /**
     * @throws PhpArraySerializationException
     */
    private static function parseInteger(
        string $bytes,
        int &$cursor,
    ): int {
        $start = $cursor;
        $negative = self::startsWithAt($bytes, $cursor, '-');

        if ($negative) {
            ++$cursor;
        }

        $firstDigit = $bytes[$cursor] ?? null;

        if ($firstDigit === null || $firstDigit < '0' || $firstDigit > '9') {
            self::invalid();
        }

        if ($firstDigit === '0') {
            if ($negative) {
                self::invalid();
            }

            ++$cursor;
        } else {
            if ($firstDigit < '1' || $firstDigit > '9') {
                self::invalid();
            }

            $maxLiteralBytes = \strlen((string) \PHP_INT_MIN);

            do {
                ++$cursor;
                if ($cursor - $start > $maxLiteralBytes) {
                    self::invalid();
                }

                $next = $bytes[$cursor] ?? null;
            } while ($next !== null && $next >= '0' && $next <= '9');
        }

        $literal = \substr($bytes, $start, $cursor - $start);

        if ($literal === '') {
            self::invalid();
        }

        $value = (int) $literal;

        if ((string) $value !== $literal) {
            self::invalid();
        }

        return $value;
    }

    /**
     * @throws PhpArraySerializationException
     */
    private static function parseString(
        string $bytes,
        int &$cursor,
        ?JsonLikeNormalizationLimits $limits,
        int &$totalStringBytes,
    ): string {
        self::expectLiteral($bytes, $cursor, '"');

        $result = '';
        $length = \strlen($bytes);
        $stringBytes = 0;
        $remainingStringBytes = $limits?->maxTotalStringBytes === null
            ? null
            : $limits->maxTotalStringBytes - $totalStringBytes;

        while ($cursor < $length) {
            $character = $bytes[$cursor];

            if ($character === '"') {
                ++$cursor;

                if ($remainingStringBytes !== null) {
                    if ($stringBytes > $remainingStringBytes) {
                        self::invalid(PhpArraySerializationException::REASON_TOTAL_STRING_BYTES_EXCEEDED);
                    }

                    $totalStringBytes += $stringBytes;
                }

                return $result;
            }

            if ($character === '\\') {
                $character = self::parseEscape($bytes, $cursor);
            } else {
                $byte = \ord($character);

                if (
                    $character === '$'
                    || $byte < 0x20
                    || $byte === 0x7F
                ) {
                    self::invalid();
                }

                ++$cursor;
            }

            if ($limits !== null) {
                if ($stringBytes >= $limits->maxStringBytes) {
                    self::invalid(PhpArraySerializationException::REASON_STRING_BYTES_EXCEEDED);
                }

                ++$stringBytes;
            }

            // Preserve individual-string limit precedence without allocating
            // bytes beyond the remaining aggregate budget.
            if ($remainingStringBytes === null || $stringBytes <= $remainingStringBytes) {
                $result .= $character;
            }
        }

        self::invalid();
    }

    private static function consumeNode(
        ?JsonLikeNormalizationLimits $limits,
        int &$nodeCount,
    ): void {
        if ($limits === null) {
            return;
        }

        if ($nodeCount >= $limits->maxNodes) {
            self::invalid(PhpArraySerializationException::REASON_MAX_NODES_EXCEEDED);
        }

        ++$nodeCount;
    }

    /**
     * @throws PhpArraySerializationException
     */
    private static function parseEscape(
        string $bytes,
        int &$cursor,
    ): string {
        self::expectLiteral($bytes, $cursor, '\\');

        $escape = $bytes[$cursor] ?? null;

        if ($escape === null) {
            self::invalid();
        }

        ++$cursor;

        return match ($escape) {
            '\\' => '\\',
            '"' => '"',
            '$' => '$',
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'x' => self::parseHexEscape($bytes, $cursor),
            default => self::invalid(),
        };
    }

    /**
     * @throws PhpArraySerializationException
     */
    private static function parseHexEscape(
        string $bytes,
        int &$cursor,
    ): string {
        $high = $bytes[$cursor] ?? null;
        $low = $bytes[$cursor + 1] ?? null;

        if (
            $high === null
            || $low === null
            || !self::isCanonicalHexDigit($high)
            || !self::isCanonicalHexDigit($low)
        ) {
            self::invalid();
        }

        $literal = $high . $low;
        $cursor += 2;
        $byte = (int) \hexdec($literal);

        if (
            ($byte >= 0x20 && $byte !== 0x7F)
            || $byte === 0x09
            || $byte === 0x0A
            || $byte === 0x0D
        ) {
            self::invalid();
        }

        return \chr($byte);
    }

    private static function isCanonicalHexDigit(string $character): bool
    {
        return ($character >= '0' && $character <= '9')
            || ($character >= 'A' && $character <= 'F');
    }

    /**
     * @throws PhpArraySerializationException
     */
    private static function assertCanonicalMapKey(
        string $key,
        ?string $previousKey,
    ): void {
        $probe = [$key => true];

        if (!\is_string(\array_key_first($probe))) {
            self::invalid();
        }

        if ($previousKey !== null && \strcmp($previousKey, $key) >= 0) {
            self::invalid();
        }
    }

    /**
     * @throws PhpArraySerializationException
     */
    private static function expectLiteral(
        string $bytes,
        int &$cursor,
        string $literal,
    ): void {
        if (!self::startsWithAt($bytes, $cursor, $literal)) {
            self::invalid();
        }

        $cursor += \strlen($literal);
    }

    private static function startsWithAt(
        string $bytes,
        int $cursor,
        string $literal,
    ): bool {
        return \substr_compare(
            $bytes,
            $literal,
            $cursor,
            \strlen($literal),
        ) === 0;
    }

    private static function indent(int $depth): string
    {
        if ($depth <= 0) {
            return '';
        }

        return \str_repeat(self::INDENT, $depth);
    }

    /**
     * @throws PhpArraySerializationException
     */
    private static function invalid(
        string $reason = PhpArraySerializationException::REASON_SERIALIZATION_INVALID,
    ): never {
        throw PhpArraySerializationException::withReason($reason);
    }
}
