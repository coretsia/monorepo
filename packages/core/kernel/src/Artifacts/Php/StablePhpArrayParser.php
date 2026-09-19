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

use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;

/**
 * Decoder for the canonical deterministic PHP-array artifact serialization.
 *
 * StablePhpArrayParser is the decoding counterpart of StablePhpArrayDumper.
 * It understands only the canonical byte grammar emitted by that dumper and
 * treats PHP-looking artifact source strictly as serialized data. It does not
 * invoke the PHP lexer, evaluator, include/require machinery, or runtime source
 * execution while decoding artifacts.
 *
 * Accepted values are limited to the serialization domain emitted by the
 * dumper: null, bool, int, string, list, and string-keyed map values. Layout,
 * indentation, string escapes, integer spelling, map ordering, separators, and
 * the document prefix/suffix are all canonical and fail closed when they drift.
 *
 * Diagnostics are intentionally safe. Serialization failures expose only the
 * stable ArtifactInvalidException reason token and never include source bytes,
 * literals, offsets, paths, or parser state.
 *
 * @internal
 */
final class StablePhpArrayParser
{
    private const string DOCUMENT_PREFIX = "<?php\n\nreturn ";
    private const string DOCUMENT_SUFFIX = ";\n";
    private const string INDENT = '    ';

    /**
     * @return array<int|string, mixed>
     *
     * @throws ArtifactInvalidException
     */
    public function parse(string $bytes): array
    {
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
            $frame = $stack[$frameIndex];

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

            self::expectLiteral(
                bytes: $bytes,
                cursor: $cursor,
                literal: self::indent($frame['depth'] + 1),
            );

            if ($frame['kind'] === 'map') {
                if (!self::startsWithAt($bytes, $cursor, '"')) {
                    self::invalid();
                }

                $key = self::parseString($bytes, $cursor);
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
                $stack[$frameIndex] = $frame;

                self::parseAndAttachValue(
                    stack: $stack,
                    frameIndex: $frameIndex,
                    valueDepth: $frame['depth'] + 1,
                    pendingKind: 'map',
                    pendingKey: $key,
                    bytes: $bytes,
                    cursor: $cursor,
                );

                continue;
            }

            if ($frame['kind'] === null && self::startsWithAt($bytes, $cursor, '"')) {
                $string = self::parseString($bytes, $cursor);

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
                    $stack[$frameIndex] = $frame;

                    self::parseAndAttachValue(
                        stack: $stack,
                        frameIndex: $frameIndex,
                        valueDepth: $frame['depth'] + 1,
                        pendingKind: 'map',
                        pendingKey: $string,
                        bytes: $bytes,
                        cursor: $cursor,
                    );

                    continue;
                }

                $frame['kind'] = 'list';
                $frame['values'][] = $string;
                $frame['hasEntries'] = true;
                $stack[$frameIndex] = $frame;

                self::expectLiteral(
                    bytes: $bytes,
                    cursor: $cursor,
                    literal: ",\n",
                );

                continue;
            }

            if ($frame['kind'] === null) {
                $frame['kind'] = 'list';
                $stack[$frameIndex] = $frame;
            }

            self::parseAndAttachValue(
                stack: $stack,
                frameIndex: $frameIndex,
                valueDepth: $frame['depth'] + 1,
                pendingKind: 'list',
                pendingKey: null,
                bytes: $bytes,
                cursor: $cursor,
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
     * @throws ArtifactInvalidException
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
     * @throws ArtifactInvalidException
     */
    private static function beginArray(
        string $bytes,
        int &$cursor,
        int $depth,
    ): array {
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
     * @throws ArtifactInvalidException
     */
    private static function parseAndAttachValue(
        array &$stack,
        int $frameIndex,
        int $valueDepth,
        string $pendingKind,
        ?string $pendingKey,
        string $bytes,
        int &$cursor,
    ): void {
        if (self::startsWithAt($bytes, $cursor, '[')) {
            $array = self::beginArray(
                bytes: $bytes,
                cursor: $cursor,
                depth: $valueDepth,
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

            $frame = $stack[$frameIndex];
            $frame['state'] = 'await-child';
            $frame['pendingKind'] = $pendingKind;
            $frame['pendingKey'] = $pendingKey;
            $stack[$frameIndex] = $frame;
            if ($array['frame'] === null) {
                self::invalid();
            }

            $stack[] = $array['frame'];

            return;
        }

        $value = self::parseScalar($bytes, $cursor);

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
     * @throws ArtifactInvalidException
     */
    private static function attachCompletedChild(
        array &$stack,
        array $value,
        string $bytes,
        int &$cursor,
    ): void {
        $frameIndex = \count($stack) - 1;
        $frame = $stack[$frameIndex];

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
        $frame = $stack[$frameIndex];

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
        $stack[$frameIndex] = $frame;
    }

    /**
     * @return null|bool|int|string
     *
     * @throws ArtifactInvalidException
     */
    private static function parseScalar(
        string $bytes,
        int &$cursor,
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
            return self::parseString($bytes, $cursor);
        }

        if ($byte === '-' || ($byte !== null && $byte >= '0' && $byte <= '9')) {
            return self::parseInteger($bytes, $cursor);
        }

        self::invalid();
    }

    /**
     * @throws ArtifactInvalidException
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

            do {
                ++$cursor;
                $next = $bytes[$cursor] ?? null;
            } while ($next !== null && $next >= '0' && $next <= '9');
        }

        $literal = \substr($bytes, $start, $cursor - $start);

        if ($literal === '') {
            self::invalid();
        }

        $value = (int)$literal;

        if ((string)$value !== $literal) {
            self::invalid();
        }

        return $value;
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function parseString(
        string $bytes,
        int &$cursor,
    ): string {
        self::expectLiteral($bytes, $cursor, '"');

        $result = '';
        $length = \strlen($bytes);

        while ($cursor < $length) {
            $character = $bytes[$cursor];

            if ($character === '"') {
                ++$cursor;

                return $result;
            }

            if ($character === '\\') {
                $result .= self::parseEscape($bytes, $cursor);

                continue;
            }

            $byte = \ord($character);

            if (
                $character === '$'
                || $byte < 0x20
                || $byte === 0x7F
            ) {
                self::invalid();
            }

            $result .= $character;
            ++$cursor;
        }

        self::invalid();
    }

    /**
     * @throws ArtifactInvalidException
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
     * @throws ArtifactInvalidException
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
        $byte = (int)\hexdec($literal);

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
     * @throws ArtifactInvalidException
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
     * @throws ArtifactInvalidException
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
     * @return never
     * @throws ArtifactInvalidException
     *
     */
    private static function invalid(): never
    {
        throw ArtifactInvalidException::withReason(
            ArtifactInvalidException::REASON_SERIALIZATION_INVALID,
        );
    }
}
