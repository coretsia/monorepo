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
 * Low-level deterministic PHP token-stream utilities for repository tooling.
 *
 * This class owns PHP lexical mechanics only. It intentionally contains no
 * DTO, package, observability, or gate policy.
 */
final class PhpTokenStream
{
    /**
     * @var list<array{0:int,1:string,2:int}|string>
     */
    private array $tokens;

    private string $namespace;

    /**
     * @var array<string,string>
     */
    private array $useAliases;

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     * @param array<string,string> $useAliases
     */
    private function __construct(array $tokens, string $namespace, array $useAliases)
    {
        $this->tokens = $tokens;
        $this->namespace = $namespace;
        $this->useAliases = $useAliases;
    }

    public static function fromFile(string $path): self
    {
        return self::fromSource(DeterministicFile::readTextNormalizedEol($path));
    }

    public static function fromParsedFile(string $path): self
    {
        return self::fromParsedSource(DeterministicFile::readTextNormalizedEol($path));
    }

    public static function fromSource(string $source): self
    {
        return self::fromSourceWithFlags($source, 0);
    }

    public static function fromParsedSource(string $source): self
    {
        return self::fromSourceWithFlags($source, TOKEN_PARSE);
    }

    /**
     * @return list<array{0:int,1:string,2:int}|string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function resolveClassName(string $rawName): string
    {
        $rawName = trim($rawName);

        if ($rawName === '') {
            return '';
        }

        $namespacePrefix = 'namespace\\';

        if (strncasecmp($rawName, $namespacePrefix, strlen($namespacePrefix)) === 0) {
            $relative = substr($rawName, strlen($namespacePrefix));

            if ($relative === '') {
                return $this->namespace;
            }

            return $this->namespace === ''
                ? $relative
                : $this->namespace . '\\' . $relative;
        }

        $isFullyQualified = str_starts_with($rawName, '\\');
        $name = trim($rawName, '\\');
        $segments = explode('\\', $name);
        $firstSegment = $segments[0] ?? '';
        $aliasKey = strtolower($firstSegment);

        if (!$isFullyQualified && isset($this->useAliases[$aliasKey])) {
            $segments[0] = $this->useAliases[$aliasKey];

            return implode('\\', $segments);
        }

        if ($isFullyQualified || $this->namespace === '') {
            return $name;
        }

        return $this->namespace . '\\' . $name;
    }

    /**
     * @return list<string>
     */
    public function attributeNames(): array
    {
        $names = [];
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $this->tokens[$i];

            if (!is_array($token) || $token[0] !== T_ATTRIBUTE) {
                continue;
            }

            [$attributeNames, $endIndex] = $this->parseAttributeGroup($i);

            foreach ($attributeNames as $attributeName) {
                $names[] = $attributeName;
            }

            $i = $endIndex;
        }

        return $names;
    }

    /**
     * @return array{0:list<string>,1:int}
     */
    public function parseAttributeGroup(int $startIndex): array
    {
        $names = [];
        $count = count($this->tokens);
        $parenDepth = 0;
        $expectName = true;

        for ($i = $startIndex + 1; $i < $count; $i++) {
            $token = $this->tokens[$i];

            if ($token === '(') {
                $parenDepth++;
                continue;
            }

            if ($token === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                continue;
            }

            if ($token === ']' && $parenDepth === 0) {
                return [$names, $i];
            }

            if ($token === ',' && $parenDepth === 0) {
                $expectName = true;
                continue;
            }

            if (!$expectName || $parenDepth !== 0) {
                continue;
            }

            if (!self::isAttributeNameToken($token)) {
                continue;
            }

            $name = '';

            while (
                $i < $count
                && self::isAttributeNameToken($this->tokens[$i])
            ) {
                $current = $this->tokens[$i];
                $name .= is_array($current) ? $current[1] : $current;
                $i++;
            }

            $i--;

            if ($name !== '') {
                $names[] = $name;
                $expectName = false;
            }
        }

        return [$names, $count - 1];
    }

    public function previousSignificantTokenIsNew(int $index): bool
    {
        return $this->previousSignificantTokenIs($index, T_NEW);
    }

    public function previousSignificantTokenIs(int $index, int $tokenId): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $this->tokens[$i];

            if (!self::isSignificantToken($token)) {
                continue;
            }

            return is_array($token) && $token[0] === $tokenId;
        }

        return false;
    }

    public function nextClassLikeName(int $index): ?string
    {
        $count = count($this->tokens);

        for ($i = $index + 1; $i < $count; $i++) {
            $token = $this->tokens[$i];

            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            if (self::isSignificantToken($token)) {
                return null;
            }
        }

        return null;
    }

    public function findNextSymbolIndex(int $start, string $needle): ?int
    {
        $count = count($this->tokens);

        for ($i = $start; $i < $count; $i++) {
            if ($this->tokens[$i] === $needle) {
                return $i;
            }
        }

        return null;
    }

    public function findMatchingPair(int $openIndex, string $open, string $close): ?int
    {
        $depth = 0;
        $count = count($this->tokens);

        for ($i = $openIndex; $i < $count; $i++) {
            $token = $this->tokens[$i];

            if (
                $token === $open
                || ($open === '{' && self::isCurlyOpenToken($token))
            ) {
                $depth++;
                continue;
            }

            if ($token !== $close) {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                return $i;
            }
        }

        return null;
    }

    public function skipTrivia(int $index): int
    {
        $count = count($this->tokens);

        while ($index < $count && self::isTriviaToken($this->tokens[$index])) {
            $index++;
        }

        return $index;
    }

    /**
     * @return list<array{
     *     name: string|null,
     *     param_start: int|null,
     *     param_end: int|null,
     *     body_start: int|null,
     *     body_end: int|null
     * }>
     */
    public function methods(int $classBodyStart, int $classBodyEnd): array
    {
        $methods = [];
        $depth = 0;

        for ($i = $classBodyStart + 1; $i < $classBodyEnd; $i++) {
            $token = $this->tokens[$i];

            if (self::isCurlyOpenToken($token)) {
                $depth++;
                continue;
            }

            if ($token === '}') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth !== 0 || !is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $name = $this->methodNameAfterFunctionToken($i);
            $paramStart = $this->findNextSymbolIndex($i, '(');
            $paramEnd = $paramStart === null
                ? null
                : $this->findMatchingPair($paramStart, '(', ')');

            $bodyStart = $paramEnd === null
                ? null
                : $this->findMethodBodyStart($paramEnd, $classBodyEnd);

            $bodyEnd = $bodyStart === null
                ? null
                : $this->findMatchingPair($bodyStart, '{', '}');

            $methods[] = [
                'name' => $name,
                'param_start' => $paramStart,
                'param_end' => $paramEnd,
                'body_start' => $bodyStart,
                'body_end' => $bodyEnd,
            ];

            if ($bodyEnd !== null) {
                $i = $bodyEnd;
            }
        }

        return $methods;
    }

    /**
     * @return list<array{
     *     name:string,
     *     property_name:string,
     *     param_start:int|null,
     *     param_end:int|null,
     *     body_start:int,
     *     body_end:int
     * }>
     */
    public function propertyHooks(int $classBodyStart, int $classBodyEnd): array
    {
        $hooks = [];
        $segmentStart = $classBodyStart + 1;
        $depth = 0;

        for ($i = $classBodyStart + 1; $i < $classBodyEnd; $i++) {
            $token = $this->tokens[$i];

            if ($depth === 0 && $token === '{') {
                $segment = array_slice(
                    $this->tokens,
                    $segmentStart,
                    $i - $segmentStart,
                );
                $propertyName = null;
                $hasFunction = false;

                foreach ($segment as $segmentToken) {
                    if (!is_array($segmentToken)) {
                        continue;
                    }

                    if ($segmentToken[0] === T_FUNCTION) {
                        $hasFunction = true;
                    } elseif ($segmentToken[0] === T_VARIABLE) {
                        $propertyName = ltrim($segmentToken[1], '$');
                    }
                }

                if (!$hasFunction && $propertyName !== null && $propertyName !== '') {
                    $hookBlockEnd = $this->findMatchingPair($i, '{', '}');

                    if ($hookBlockEnd === null || $hookBlockEnd > $classBodyEnd) {
                        continue;
                    }

                    foreach (
                        $this->parsePropertyHookBlock(
                            $i,
                            $hookBlockEnd,
                            $propertyName,
                        ) as $hook
                    ) {
                        $hooks[] = $hook;
                    }

                    $i = $hookBlockEnd;
                    $segmentStart = $i + 1;
                    continue;
                }
            }

            if (self::isCurlyOpenToken($token)) {
                $depth++;
                continue;
            }

            if ($token === '}') {
                if ($depth > 0) {
                    $depth--;

                    if ($depth === 0) {
                        $segmentStart = $i + 1;
                    }
                }

                continue;
            }

            if ($depth === 0 && $token === ';') {
                $segmentStart = $i + 1;
            }
        }

        return $hooks;
    }

    /**
     * @return list<array{
     *     kind:string,
     *     name:string|null,
     *     token_index:int,
     *     body_start:int,
     *     body_end:int,
     *     anonymous:bool
     * }>
     */
    public function classLikes(): array
    {
        $out = [];
        $count = count($this->tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $this->tokens[$i];

            if (
                !is_array($token)
                || (
                    $token[0] !== T_CLASS
                    && $token[0] !== T_INTERFACE
                    && $token[0] !== T_TRAIT
                    && $token[0] !== T_ENUM
                )
            ) {
                continue;
            }

            if ($token[0] === T_CLASS && $this->previousSignificantTokenIs($i, T_DOUBLE_COLON)) {
                continue;
            }

            $anonymous = $token[0] === T_CLASS && $this->previousSignificantTokenIsNew($i);
            $name = $anonymous ? null : $this->nextClassLikeName($i);
            $bodyStart = $this->findNextSymbolIndex($i, '{');

            if ($bodyStart === null) {
                continue;
            }

            $bodyEnd = $this->findMatchingPair($bodyStart, '{', '}');

            if ($bodyEnd === null) {
                continue;
            }

            $kind = 'class';

            if ($token[0] === T_INTERFACE) {
                $kind = 'interface';
            } elseif ($token[0] === T_TRAIT) {
                $kind = 'trait';
            } elseif ($token[0] === T_ENUM) {
                $kind = 'enum';
            }

            $out[] = [
                'kind' => $kind,
                'name' => $name,
                'token_index' => $i,
                'body_start' => $bodyStart,
                'body_end' => $bodyEnd,
                'anonymous' => $anonymous,
            ];

            $i = $bodyEnd;
        }

        return $out;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    public static function nextSignificantIndex(array $tokens, int $start): ?int
    {
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            if (self::isSignificantToken($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    public static function previousSignificantIndex(array $tokens, int $start): ?int
    {
        for ($i = $start; $i >= 0; $i--) {
            if (self::isSignificantToken($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     *
     * @return list<array{0:int,1:string,2:int}|string>
     */
    public static function significantTokens(array $tokens): array
    {
        $out = [];

        foreach ($tokens as $token) {
            if (self::isSignificantToken($token)) {
                $out[] = $token;
            }
        }

        return $out;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    public static function findNextSymbolAfter(array $tokens, int $start, string $symbol): ?int
    {
        $count = count($tokens);

        for ($i = $start + 1; $i < $count; $i++) {
            if ($tokens[$i] === $symbol) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    public static function findMatchingPairIn(
        array $tokens,
        int $openIndex,
        string $open,
        string $close,
    ): ?int {
        $depth = 0;
        $count = count($tokens);

        for ($i = $openIndex; $i < $count; $i++) {
            $token = $tokens[$i];

            if (
                $token === $open
                || ($open === '{' && self::isCurlyOpenToken($token))
            ) {
                $depth++;
                continue;
            }

            if ($token !== $close) {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     *
     * @return list<array{0:int,1:string,2:int}|string>
     */
    public static function statementSegment(array $tokens, int $index): array
    {
        $start = $index;

        for ($i = $index; $i >= 0; $i--) {
            if ($tokens[$i] === ';' || $tokens[$i] === '{' || $tokens[$i] === '}') {
                $start = $i + 1;
                break;
            }

            if ($i === 0) {
                $start = 0;
            }
        }

        $end = $index;
        $count = count($tokens);

        for ($i = $index; $i < $count; $i++) {
            if ($tokens[$i] === ';') {
                $end = $i;
                break;
            }

            if ($i === $count - 1) {
                $end = $count - 1;
            }
        }

        return array_slice($tokens, $start, $end - $start + 1);
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    public static function findStatementEnd(array $tokens, int $start): ?int
    {
        $depth = 0;
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '(' || $token === '[' || self::isCurlyOpenToken($token)) {
                $depth++;
                continue;
            }

            if ($token === ')' || $token === ']' || $token === '}') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth === 0 && $token === ';') {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     *
     * @return list<list<array{0:int,1:string,2:int}|string>>
     */
    public static function splitTopLevel(array $tokens, string $delimiter): array
    {
        $parts = [];
        $current = [];
        $depth = 0;

        foreach ($tokens as $token) {
            if ($token === '(' || $token === '[' || self::isCurlyOpenToken($token)) {
                $depth++;
            } elseif ($token === ')' || $token === ']' || $token === '}') {
                $depth = max(0, $depth - 1);
            }

            if ($depth === 0 && $token === $delimiter) {
                $parts[] = $current;
                $current = [];
                continue;
            }

            $current[] = $token;
        }

        $parts[] = $current;

        return $parts;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    public static function findTopLevelToken(array $tokens, int $tokenId): ?int
    {
        $depth = 0;

        foreach ($tokens as $index => $token) {
            if ($token === '(' || $token === '[' || self::isCurlyOpenToken($token)) {
                $depth++;
                continue;
            }

            if ($token === ')' || $token === ']' || $token === '}') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth === 0 && self::isToken($token, $tokenId)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    public static function containsTopLevelSymbol(array $tokens, string $symbol): bool
    {
        $depth = 0;

        foreach ($tokens as $token) {
            if ($token === '(' || $token === '[' || self::isCurlyOpenToken($token)) {
                $depth++;
                continue;
            }

            if ($token === ')' || $token === ']' || $token === '}') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth === 0 && $token === $symbol) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    public static function isToken(array|string $token, int $tokenId): bool
    {
        return is_array($token) && $token[0] === $tokenId;
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    public static function isObjectOperator(array|string $token): bool
    {
        return self::isToken($token, T_OBJECT_OPERATOR)
            || (
                defined('T_NULLSAFE_OBJECT_OPERATOR')
                && self::isToken($token, T_NULLSAFE_OBJECT_OPERATOR)
            );
    }

    public static function isNameTokenId(int $tokenId): bool
    {
        return $tokenId === T_STRING
            || (defined('T_NAME_QUALIFIED') && $tokenId === T_NAME_QUALIFIED)
            || (defined('T_NAME_FULLY_QUALIFIED') && $tokenId === T_NAME_FULLY_QUALIFIED)
            || (defined('T_NAME_RELATIVE') && $tokenId === T_NAME_RELATIVE);
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    public static function tokenTextLower(array|string $token): string
    {
        return strtolower(is_array($token) ? $token[1] : $token);
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $segment
     */
    public static function segmentContainsToken(array $segment, int $tokenId): bool
    {
        foreach ($segment as $token) {
            if (self::isToken($token, $tokenId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $segment
     */
    public static function segmentHasVisibility(array $segment): bool
    {
        return self::segmentContainsToken($segment, T_PRIVATE)
            || self::segmentContainsToken($segment, T_PROTECTED)
            || self::segmentContainsToken($segment, T_PUBLIC);
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $argument
     */
    public static function isNamedArgument(array $argument): bool
    {
        $significant = self::significantTokens($argument);

        return count($significant) >= 2
            && self::isToken($significant[0], T_STRING)
            && $significant[1] === ':';
    }

    public static function decodeStringLiteral(string $literal): string
    {
        $length = strlen($literal);
        $quoteIndex = 0;

        if (
            $length >= 3
            && ($literal[0] === 'b' || $literal[0] === 'B')
            && ($literal[1] === "'" || $literal[1] === '"')
        ) {
            $quoteIndex = 1;
        }

        if ($length - $quoteIndex < 2) {
            return $literal;
        }

        $quote = $literal[$quoteIndex];

        if (($quote !== "'" && $quote !== '"') || $literal[$length - 1] !== $quote) {
            return $literal;
        }

        $inner = substr(
            $literal,
            $quoteIndex + 1,
            $length - $quoteIndex - 2,
        );

        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $inner);
        }

        $out = '';
        $innerLength = strlen($inner);

        for ($i = 0; $i < $innerLength; $i++) {
            $char = $inner[$i];

            if ($char !== '\\' || $i + 1 >= $innerLength) {
                $out .= $char;
                continue;
            }

            $next = $inner[$i + 1];
            $simpleEscapes = [
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'v' => "\v",
                'e' => "\x1B",
                'f' => "\f",
                '\\' => '\\',
                '$' => '$',
                '"' => '"',
            ];

            if (isset($simpleEscapes[$next])) {
                $out .= $simpleEscapes[$next];
                $i++;
                continue;
            }

            if ($next >= '0' && $next <= '7') {
                $digits = $next;
                $cursor = $i + 2;

                while (
                    $cursor < $innerLength
                    && strlen($digits) < 3
                    && $inner[$cursor] >= '0'
                    && $inner[$cursor] <= '7'
                ) {
                    $digits .= $inner[$cursor];
                    $cursor++;
                }

                $out .= chr(octdec($digits) & 0xFF);
                $i = $cursor - 1;
                continue;
            }

            if ($next === 'x' || $next === 'X') {
                $digits = '';
                $cursor = $i + 2;

                while (
                    $cursor < $innerLength
                    && strlen($digits) < 2
                    && str_contains('0123456789abcdefABCDEF', $inner[$cursor])
                ) {
                    $digits .= $inner[$cursor];
                    $cursor++;
                }

                if ($digits !== '') {
                    $out .= chr(hexdec($digits));
                    $i = $cursor - 1;
                    continue;
                }
            }

            if (
                $next === 'u'
                && $i + 2 < $innerLength
                && $inner[$i + 2] === '{'
            ) {
                $end = strpos($inner, '}', $i + 3);

                if ($end !== false) {
                    $digits = substr($inner, $i + 3, $end - $i - 3);

                    if (
                        $digits !== ''
                        && strspn($digits, '0123456789abcdefABCDEF') === strlen($digits)
                    ) {
                        $significantDigits = ltrim($digits, '0');
                        $significantDigits = $significantDigits === ''
                            ? '0'
                            : $significantDigits;

                        if (strlen($significantDigits) <= 6) {
                            $codePoint = hexdec($significantDigits);
                            $decoded = self::utf8FromCodePoint($codePoint);

                            if ($decoded !== null) {
                                $out .= $decoded;
                                $i = $end;
                                continue;
                            }
                        }
                    }
                }
            }

            $out .= '\\' . $next;
            $i++;
        }

        return $out;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $segment
     *
     * @return array<string,string>
     */
    public static function parsePrivateStringConstants(array $segment): array
    {
        if (
            !self::segmentContainsToken($segment, T_PRIVATE)
            || !self::segmentContainsToken($segment, T_CONST)
        ) {
            return [];
        }

        $hasStringType = false;

        foreach ($segment as $token) {
            if (is_array($token) && strtolower($token[1]) === 'string') {
                $hasStringType = true;
                break;
            }
        }

        if (!$hasStringType) {
            return [];
        }

        $out = [];

        foreach (self::splitTopLevel($segment, ',') as $declarator) {
            $significant = self::significantTokens($declarator);

            if ($significant !== [] && end($significant) === ';') {
                array_pop($significant);
            }

            $equalsIndex = null;

            foreach ($significant as $index => $token) {
                if ($token === '=') {
                    $equalsIndex = $index;
                    break;
                }
            }

            if ($equalsIndex === null || $equalsIndex === 0) {
                continue;
            }

            $nameToken = $significant[$equalsIndex - 1];

            if (
                !self::isToken($nameToken, T_STRING)
                || strtolower($nameToken[1]) === 'string'
            ) {
                continue;
            }

            $valueTokens = array_slice($significant, $equalsIndex + 1);

            if (
                count($valueTokens) !== 1
                || !self::isToken($valueTokens[0], T_CONSTANT_ENCAPSED_STRING)
            ) {
                continue;
            }

            $out[$nameToken[1]] = self::decodeStringLiteral($valueTokens[0][1]);
        }

        return $out;
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    public static function isCurlyOpenToken(array|string $token): bool
    {
        return $token === '{'
            || (
                is_array($token)
                && (
                    $token[0] === T_CURLY_OPEN
                    || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES
                )
            );
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    public static function isIgnorableBetweenAttributeAndClassLike(array|string $token): bool
    {
        if (is_string($token)) {
            return false;
        }

        return $token[0] === T_WHITESPACE
            || $token[0] === T_COMMENT
            || $token[0] === T_DOC_COMMENT
            || $token[0] === T_FINAL
            || $token[0] === T_ABSTRACT
            || $token[0] === T_READONLY;
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    public static function isSignificantToken(array|string $token): bool
    {
        if (is_string($token)) {
            return trim($token) !== '';
        }

        return $token[0] !== T_WHITESPACE
            && $token[0] !== T_COMMENT
            && $token[0] !== T_DOC_COMMENT;
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    public static function isTriviaToken(array|string $token): bool
    {
        return is_array($token)
            && (
                $token[0] === T_WHITESPACE
                || $token[0] === T_COMMENT
                || $token[0] === T_DOC_COMMENT
            );
    }

    private static function fromSourceWithFlags(string $source, int $flags): self
    {
        try {
            /** @var list<array{0:int,1:string,2:int}|string> $tokens */
            $tokens = token_get_all($source, $flags);
        } catch (\Throwable) {
            throw new \RuntimeException('php-tokenize-failed');
        }

        return new self(
            $tokens,
            self::extractNamespace($tokens),
            self::extractUseAliases($tokens),
        );
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    private static function extractNamespace(array $tokens): string
    {
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token) || $token[0] !== T_NAMESPACE) {
                continue;
            }

            $name = '';

            for ($i++; $i < $count; $i++) {
                $current = $tokens[$i];

                if ($current === ';' || $current === '{') {
                    break;
                }

                if (
                    is_array($current)
                    && (
                        $current[0] === T_STRING
                        || $current[0] === T_NAME_QUALIFIED
                        || $current[0] === T_NAME_FULLY_QUALIFIED
                        || $current[0] === T_NAME_RELATIVE
                        || $current[0] === T_NS_SEPARATOR
                    )
                ) {
                    $name .= $current[1];
                }
            }

            return trim($name, '\\');
        }

        return '';
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     *
     * @return array<string,string>
     */
    private static function extractUseAliases(array $tokens): array
    {
        $aliases = [];
        $count = count($tokens);
        $namespaceDepth = self::namespaceScopeDepth($tokens);
        $braceDepth = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (self::isCurlyOpenToken($token)) {
                $braceDepth++;
                continue;
            }

            if ($token === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                continue;
            }

            if (
                $braceDepth !== $namespaceDepth
                || !is_array($token)
                || $token[0] !== T_USE
            ) {
                continue;
            }

            $next = self::nextSignificantTokenIndex($tokens, $i + 1);
            if ($next === null) {
                continue;
            }

            $nextToken = $tokens[$next];
            if (is_array($nextToken) && ($nextToken[0] === T_FUNCTION || $nextToken[0] === T_CONST)) {
                continue;
            }

            $previous = self::previousSignificantToken($tokens, $i - 1);
            if ($previous === ')') {
                continue;
            }

            $statement = '';

            for ($j = $i + 1; $j < $count; $j++) {
                $current = $tokens[$j];

                if ($current === ';') {
                    break;
                }

                if (
                    is_array($current)
                    && ($current[0] === T_COMMENT || $current[0] === T_DOC_COMMENT)
                ) {
                    $statement .= ' ';
                    continue;
                }

                $statement .= is_array($current) ? $current[1] : $current;
            }

            foreach (self::parseUseStatement($statement) as $alias => $fqcn) {
                $aliases[strtolower($alias)] = $fqcn;
            }
        }

        ksort($aliases, SORT_STRING);

        return $aliases;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    private static function namespaceScopeDepth(array $tokens): int
    {
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token) || $token[0] !== T_NAMESPACE) {
                continue;
            }

            for ($i++; $i < $count; $i++) {
                if ($tokens[$i] === ';') {
                    return 0;
                }

                if ($tokens[$i] === '{') {
                    return 1;
                }
            }
        }

        return 0;
    }

    /**
     * @return array<string,string>
     */
    private static function parseUseStatement(string $statement): array
    {
        $statement = trim($statement);

        if ($statement === '') {
            return [];
        }

        $openBrace = strpos($statement, '{');
        $closeBrace = strrpos($statement, '}');

        if ($openBrace !== false && $closeBrace !== false && $closeBrace > $openBrace) {
            $prefix = rtrim(trim(substr($statement, 0, $openBrace)), "\\ \t\n\r\0\x0B");
            $inside = substr($statement, $openBrace + 1, $closeBrace - $openBrace - 1);
            $aliases = [];

            foreach (self::splitUseParts($inside) as $part) {
                $parsed = self::parseUsePart($part, $prefix);

                if ($parsed !== null) {
                    $aliases[$parsed['alias']] = $parsed['fqcn'];
                }
            }

            return $aliases;
        }

        $aliases = [];

        foreach (self::splitUseParts($statement) as $part) {
            $parsed = self::parseUsePart($part, null);

            if ($parsed !== null) {
                $aliases[$parsed['alias']] = $parsed['fqcn'];
            }
        }

        return $aliases;
    }

    /**
     * @return list<string>
     */
    private static function splitUseParts(string $statement): array
    {
        $parts = [];
        $current = '';
        $braceDepth = 0;
        $length = strlen($statement);

        for ($i = 0; $i < $length; $i++) {
            $char = $statement[$i];

            if ($char === '{') {
                $braceDepth++;
                $current .= $char;
                continue;
            }

            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                $current .= $char;
                continue;
            }

            if ($char === ',' && $braceDepth === 0) {
                $part = trim($current);

                if ($part !== '') {
                    $parts[] = $part;
                }

                $current = '';
                continue;
            }

            $current .= $char;
        }

        $part = trim($current);

        if ($part !== '') {
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @return array{alias:string,fqcn:string}|null
     */
    private static function parseUsePart(string $part, ?string $prefix): ?array
    {
        $part = trim($part);

        if ($part === '' || preg_match('/^(?:function|const)\b/i', $part) === 1) {
            return null;
        }

        $alias = null;

        if (preg_match('/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $part, $matches) === 1) {
            $fqcnPart = trim($matches[1]);
            $alias = $matches[2];
        } else {
            $fqcnPart = $part;
        }

        $fqcnPart = trim($fqcnPart, "\\ \t\n\r\0\x0B");

        if ($fqcnPart === '') {
            return null;
        }

        $fqcn = $prefix === null || $prefix === ''
            ? $fqcnPart
            : $prefix . '\\' . $fqcnPart;
        $fqcn = trim($fqcn, "\\ \t\n\r\0\x0B");

        if ($fqcn === '') {
            return null;
        }

        if ($alias === null) {
            $segments = explode('\\', $fqcn);
            $last = end($segments);

            if (!is_string($last) || $last === '') {
                return null;
            }

            $alias = $last;
        }

        return [
            'alias' => $alias,
            'fqcn' => $fqcn,
        ];
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    private static function isAttributeNameToken(array|string $token): bool
    {
        if (is_string($token)) {
            return $token === '\\';
        }

        return $token[0] === T_STRING
            || $token[0] === T_NAME_QUALIFIED
            || $token[0] === T_NAME_FULLY_QUALIFIED
            || $token[0] === T_NAME_RELATIVE;
    }

    private function methodNameAfterFunctionToken(int $functionIndex): ?string
    {
        $count = count($this->tokens);
        $i = $this->skipTrivia($functionIndex + 1);

        if ($i < $count && $this->tokens[$i] === '&') {
            $i = $this->skipTrivia($i + 1);
        }

        if ($i < $count && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_STRING) {
            return $this->tokens[$i][1];
        }

        return null;
    }

    private function findMethodBodyStart(int $paramEnd, int $classBodyEnd): ?int
    {
        for ($i = $paramEnd + 1; $i < $classBodyEnd; $i++) {
            $token = $this->tokens[$i];

            if ($token === '{') {
                return $i;
            }

            if ($token === ';') {
                return null;
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *     name:string,
     *     property_name:string,
     *     param_start:int|null,
     *     param_end:int|null,
     *     body_start:int,
     *     body_end:int
     * }>
     */
    private function parsePropertyHookBlock(
        int $blockStart,
        int $blockEnd,
        string $propertyName,
    ): array {
        $hooks = [];

        for ($i = $blockStart + 1; $i < $blockEnd; $i++) {
            $token = $this->tokens[$i];

            if (
                !is_array($token)
                || $token[0] !== T_STRING
                || (
                    strtolower($token[1]) !== 'get'
                    && strtolower($token[1]) !== 'set'
                )
            ) {
                continue;
            }

            $name = strtolower($token[1]);
            $paramStart = null;
            $paramEnd = null;
            $next = self::nextSignificantTokenIndex($this->tokens, $i + 1);

            if ($next === null || $next >= $blockEnd) {
                continue;
            }

            if ($this->tokens[$next] === '(') {
                $paramStart = $next;
                $paramEnd = $this->findMatchingPair($paramStart, '(', ')');

                if ($paramEnd === null || $paramEnd >= $blockEnd) {
                    continue;
                }

                $next = self::nextSignificantTokenIndex($this->tokens, $paramEnd + 1);

                if ($next === null || $next >= $blockEnd) {
                    continue;
                }
            }

            $bodyStartToken = $this->tokens[$next];

            if ($bodyStartToken === '{') {
                $bodyEnd = $this->findMatchingPair($next, '{', '}');

                if ($bodyEnd === null || $bodyEnd > $blockEnd) {
                    continue;
                }

                $hooks[] = [
                    'name' => $name,
                    'property_name' => $propertyName,
                    'param_start' => $paramStart,
                    'param_end' => $paramEnd,
                    'body_start' => $next,
                    'body_end' => $bodyEnd,
                ];

                $i = $bodyEnd;
                continue;
            }

            if (self::isToken($bodyStartToken, T_DOUBLE_ARROW)) {
                $bodyEnd = self::findStatementEnd($this->tokens, $next + 1);

                if ($bodyEnd === null || $bodyEnd > $blockEnd) {
                    continue;
                }

                $hooks[] = [
                    'name' => $name,
                    'property_name' => $propertyName,
                    'param_start' => $paramStart,
                    'param_end' => $paramEnd,
                    'body_start' => $next,
                    'body_end' => $bodyEnd,
                ];

                $i = $bodyEnd;
            }
        }

        return $hooks;
    }

    private static function utf8FromCodePoint(int $codePoint): ?string
    {
        if (
            $codePoint < 0
            || $codePoint > 0x10FFFF
            || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)
        ) {
            return null;
        }

        if ($codePoint <= 0x7F) {
            return chr($codePoint);
        }

        if ($codePoint <= 0x7FF) {
            return chr(0xC0 | ($codePoint >> 6))
                . chr(0x80 | ($codePoint & 0x3F));
        }

        if ($codePoint <= 0xFFFF) {
            return chr(0xE0 | ($codePoint >> 12))
                . chr(0x80 | (($codePoint >> 6) & 0x3F))
                . chr(0x80 | ($codePoint & 0x3F));
        }

        return chr(0xF0 | ($codePoint >> 18))
            . chr(0x80 | (($codePoint >> 12) & 0x3F))
            . chr(0x80 | (($codePoint >> 6) & 0x3F))
            . chr(0x80 | ($codePoint & 0x3F));
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     * @param int $index
     *
     * @return int|string|null
     */
    private static function previousSignificantToken(array $tokens, int $index): int|string|null
    {
        for ($i = $index; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (!self::isSignificantToken($token)) {
                continue;
            }

            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    private static function nextSignificantTokenIndex(array $tokens, int $start): ?int
    {
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            if (self::isSignificantToken($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }
}
