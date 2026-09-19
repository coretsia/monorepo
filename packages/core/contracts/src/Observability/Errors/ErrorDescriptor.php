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

namespace Coretsia\Contracts\Observability\Errors;

/**
 * Format-neutral normalized error descriptor.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts shape policy.
 *
 * The descriptor MUST NOT expose raw Throwable objects, transport objects,
 * raw payloads, raw SQL, credentials, tokens, cookies, or profile payloads.
 *
 * Extension metadata is validated fail-closed for known forbidden semantic
 * channels and absolute local paths. ErrorDescriptor does not silently redact,
 * mask, hash, or rewrite producer input.
 *
 * Producers remain responsible for deriving safe metadata before construction.
 * Passing an allowed key does not authorize copying arbitrary raw source data
 * into extensions.
 *
 * Extension normalization also applies mandatory fixed resource bounds for
 * recursive container depth, total nodes, individual string bytes, and
 * aggregate string bytes. Oversized or recursive payloads fail closed and are
 * never truncated automatically.
 */
final readonly class ErrorDescriptor
{
    public const int SCHEMA_VERSION = 1;

    private const int MAX_EXTENSION_DEPTH = 8;

    private const int MAX_EXTENSION_NODES = 256;

    private const int MAX_EXTENSION_STRING_BYTES = 4096;

    private const int MAX_EXTENSION_TOTAL_STRING_BYTES = 65_536;

    private const string INVALID_EXTENSIONS_MESSAGE = 'Invalid error descriptor extensions.';

    /**
     * Normalized extension-key tokens that are not valid ErrorDescriptor
     * metadata channels.
     *
     * Keys are normalized by ASCII lowercasing and removing `_`, `-`, and `.`
     * before exact comparison.
     *
     * @var list<string>
     */
    private const array FORBIDDEN_EXTENSION_KEY_TOKENS = [
        'authorization',
        'authorizationdata',
        'authorizationheader',
        'auth',
        'authdata',
        'authidentifier',
        'authidentifiers',

        'header',
        'headers',

        'cookie',
        'cookies',
        'setcookie',

        'session',
        'sessionid',
        'sessionidentifier',
        'sessionidentifiers',

        'token',
        'tokens',
        'accesstoken',
        'refreshtoken',
        'apikey',

        'password',
        'passwords',
        'secret',
        'secrets',
        'credential',
        'credentials',
        'privatekey',
        'privatekeys',

        'dsn',
        'connectionstring',

        'raw',
        'rawbody',
        'body',
        'rawpayload',
        'payload',
        'request',
        'requestbody',
        'requestpayload',
        'response',
        'responsebody',
        'responsepayload',

        'rawsql',
        'sql',
        'query',
        'querystring',
        'statement',

        'stacktrace',
        'trace',
        'exception',
        'exceptionmessage',
        'throwable',
        'throwablemessage',

        'profile',
        'rawprofile',
        'profilepayload',
        'rawprofilepayload',
        'persistencepayload',
        'rawpersistencepayload',

        'email',
        'phone',
        'username',
        'fullname',
        'userid',
        'tenantid',
        'customer',
        'customerdata',
        'privatecustomerdata',

        'path',
        'filepath',
        'absolutepath',

        'env',
        'environment',
        'host',
        'hostname',

        'requestid',
        'correlationid',
    ];

    private string $code;
    private string $message;
    private ErrorSeverity $severity;
    private ?int $httpStatus;

    /**
     * Deterministic policy-validated extension map.
     *
     * Known forbidden semantic metadata channels and absolute local paths are
     * rejected recursively. Raw producer data is never automatically redacted.
     *
     * The extension map is resource-bounded during the same recursive traversal
     * used for validation and deterministic normalization.
     *
     * Values are normalized recursively and may contain only:
     * null, bool, int, string, list values, or string-keyed maps.
     *
     * Floats, objects, closures, resources, and throwables are rejected.
     *
     * @var array<string, mixed>
     */
    private array $extensions;

    /**
     * @param non-empty-string $code
     * @param non-empty-string $message
     * @param int<100,599>|null $httpStatus
     * @param array<string,mixed> $extensions
     */
    public function __construct(
        string $code,
        string $message,
        ErrorSeverity $severity = ErrorSeverity::Error,
        ?int $httpStatus = null,
        array $extensions = [],
    ) {
        $this->code = self::normalizeCode($code);
        $this->message = self::normalizeMessage($message);
        $this->severity = $severity;
        $this->httpStatus = self::normalizeHttpStatus($httpStatus);
        $this->extensions = self::normalizeExtensions($extensions);
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * @return non-empty-string
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * @return non-empty-string
     */
    public function message(): string
    {
        return $this->message;
    }

    public function severity(): ErrorSeverity
    {
        return $this->severity;
    }

    /**
     * Optional HTTP status hint only.
     *
     * Non-HTTP runtimes may ignore this value.
     *
     * @return int<100,599>|null
     */
    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Returns deterministic policy-validated extension metadata.
     *
     * @return array<string, mixed>
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    /**
     * @return array{
     *     code: non-empty-string,
     *     extensions: array<string,mixed>,
     *     httpStatus: int<100,599>|null,
     *     message: non-empty-string,
     *     schemaVersion: int,
     *     severity: non-empty-string
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'extensions' => $this->extensions,
            'httpStatus' => $this->httpStatus,
            'message' => $this->message,
            'schemaVersion' => self::SCHEMA_VERSION,
            'severity' => $this->severity->value,
        ];
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeCode(string $code): string
    {
        if ($code === '') {
            throw new \InvalidArgumentException('Invalid error descriptor code.');
        }

        if (!self::isSafeSingleLineString($code)) {
            throw new \InvalidArgumentException('Invalid error descriptor code.');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $code) !== 1) {
            throw new \InvalidArgumentException('Invalid error descriptor code.');
        }

        return $code;
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeMessage(string $message): string
    {
        if ($message === '') {
            throw new \InvalidArgumentException('Invalid error descriptor message.');
        }

        if (preg_match('/^\s|\s$/', $message) === 1) {
            throw new \InvalidArgumentException('Invalid error descriptor message.');
        }

        if (!self::isSafeSingleLineString($message)) {
            throw new \InvalidArgumentException('Invalid error descriptor message.');
        }

        return $message;
    }

    /**
     * @return int<100,599>|null
     */
    private static function normalizeHttpStatus(?int $httpStatus): ?int
    {
        if ($httpStatus === null) {
            return null;
        }

        if ($httpStatus < 100 || $httpStatus > 599) {
            throw new \InvalidArgumentException('Invalid error descriptor http status.');
        }

        return $httpStatus;
    }

    /**
     * @param array<string,mixed> $extensions
     *
     * @return array<string,mixed>
     */
    private static function normalizeExtensions(array $extensions): array
    {
        if (array_is_list($extensions) && $extensions !== []) {
            throw new \InvalidArgumentException('Error descriptor extensions must be a map.');
        }

        $visitedNodes = 0;
        $totalStringBytes = 0;

        /** @var array<string,mixed> $normalized */
        $normalized = self::normalizeJsonLikeMap(
            map: $extensions,
            path: 'extensions',
            containerDepth: 1,
            visitedNodes: $visitedNodes,
            totalStringBytes: $totalStringBytes,
        );

        return $normalized;
    }

    /**
     * @param array<mixed> $map
     *
     * @return array<string,mixed>
     */
    private static function normalizeJsonLikeMap(
        array $map,
        string $path,
        int $containerDepth,
        int &$visitedNodes,
        int &$totalStringBytes,
    ): array {
        self::assertExtensionDepth($containerDepth);

        self::assertRemainingExtensionNodes(
            count($map),
            $visitedNodes,
        );

        $out = [];

        foreach ($map as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Invalid error descriptor extension key at ' . $path . '.');
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid error descriptor extension key at ' . $path . '.');
            }

            self::consumeExtensionStringBytes(
                value: $key,
                totalStringBytes: $totalStringBytes,
            );

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid error descriptor extension key at ' . $path . '.');
            }

            self::assertSafeExtensionKey($key, $path);
            self::consumeExtensionNode($visitedNodes);

            $out[$key] = self::normalizeJsonLikeValue(
                value: $value,
                path: $path . '.' . $key,
                parentContainerDepth: $containerDepth,
                visitedNodes: $visitedNodes,
                totalStringBytes: $totalStringBytes,
            );
        }

        ksort($out, \SORT_STRING);

        /** @var array<string,mixed> $out */
        return $out;
    }

    private static function normalizeJsonLikeValue(
        mixed $value,
        string $path,
        int $parentContainerDepth,
        int &$visitedNodes,
        int &$totalStringBytes,
    ): mixed {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            self::consumeExtensionStringBytes(
                value: $value,
                totalStringBytes: $totalStringBytes,
            );

            if (!self::isSafeString($value) || self::isAbsoluteLocalPath($value)) {
                throw new \InvalidArgumentException('Invalid error descriptor extension string at ' . $path . '.');
            }

            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float error descriptor extension at ' . $path . '.');
        }

        if (is_array($value)) {
            $containerDepth = $parentContainerDepth + 1;

            self::assertExtensionDepth($containerDepth);

            if (array_is_list($value)) {
                return self::normalizeJsonLikeList(
                    list: $value,
                    path: $path,
                    containerDepth: $containerDepth,
                    visitedNodes: $visitedNodes,
                    totalStringBytes: $totalStringBytes,
                );
            }

            return self::normalizeJsonLikeMap(
                map: $value,
                path: $path,
                containerDepth: $containerDepth,
                visitedNodes: $visitedNodes,
                totalStringBytes: $totalStringBytes,
            );
        }

        throw new \InvalidArgumentException('Invalid error descriptor extension at ' . $path . '.');
    }

    /**
     * @param list<mixed> $list
     *
     * @return list<mixed>
     */
    private static function normalizeJsonLikeList(
        array $list,
        string $path,
        int $containerDepth,
        int &$visitedNodes,
        int &$totalStringBytes,
    ): array {
        self::assertExtensionDepth($containerDepth);

        self::assertRemainingExtensionNodes(
            count($list),
            $visitedNodes,
        );

        $out = [];

        foreach ($list as $item) {
            self::consumeExtensionNode($visitedNodes);

            $out[] = self::normalizeJsonLikeValue(
                value: $item,
                path: $path . '[]',
                parentContainerDepth: $containerDepth,
                visitedNodes: $visitedNodes,
                totalStringBytes: $totalStringBytes,
            );
        }

        return $out;
    }

    private static function assertExtensionDepth(int $containerDepth): void
    {
        if ($containerDepth <= self::MAX_EXTENSION_DEPTH) {
            return;
        }

        self::throwInvalidExtensionsBudget();
    }

    private static function assertRemainingExtensionNodes(
        int $incomingNodes,
        int $visitedNodes,
    ): void {
        if ($incomingNodes <= self::MAX_EXTENSION_NODES - $visitedNodes) {
            return;
        }

        self::throwInvalidExtensionsBudget();
    }

    private static function consumeExtensionNode(int &$visitedNodes): void
    {
        ++$visitedNodes;

        if ($visitedNodes <= self::MAX_EXTENSION_NODES) {
            return;
        }

        self::throwInvalidExtensionsBudget();
    }

    private static function consumeExtensionStringBytes(
        string $value,
        int &$totalStringBytes,
    ): void {
        $bytes = strlen($value);

        if ($bytes > self::MAX_EXTENSION_STRING_BYTES) {
            self::throwInvalidExtensionsBudget();
        }

        if ($bytes > self::MAX_EXTENSION_TOTAL_STRING_BYTES - $totalStringBytes) {
            self::throwInvalidExtensionsBudget();
        }

        $totalStringBytes += $bytes;
    }

    private static function throwInvalidExtensionsBudget(): never
    {
        throw new \InvalidArgumentException(self::INVALID_EXTENSIONS_MESSAGE);
    }

    private static function assertSafeExtensionKey(string $key, string $path): void
    {
        if (in_array(
            self::normalizeSemanticExtensionKey($key),
            self::FORBIDDEN_EXTENSION_KEY_TOKENS,
            true,
        )) {
            throw new \InvalidArgumentException('Unsafe error descriptor extension key at ' . $path . '.');
        }
    }

    private static function normalizeSemanticExtensionKey(string $key): string
    {
        return strtolower(str_replace(['_', '-', '.'], '', $key));
    }

    private static function isAbsoluteLocalPath(string $value): bool
    {
        $candidate = ltrim($value, " \t");

        if ($candidate === '') {
            return false;
        }

        if (str_starts_with($candidate, '/') || str_starts_with($candidate, '\\')) {
            return true;
        }

        if (preg_match('/\A[A-Za-z]:[\\\\\/]/', $candidate) === 1) {
            return true;
        }

        return preg_match('/\Afile:\/\/(?:\/|[^\/\\\\]+[\/\\\\])/i', $candidate) === 1;
    }

    private static function isSafeSingleLineString(string $value): bool
    {
        return self::isSafeString($value)
            && !str_contains($value, "\r")
            && !str_contains($value, "\n");
    }

    private static function isSafeString(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
