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

namespace Coretsia\Kernel\Artifacts\Builders;

use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;

/**
 * Builds the Kernel-owned `container@1` compiled-container artifact envelope.
 *
 * This builder receives deterministic compiled container graph data produced by
 * ContainerCompiler and wraps it in the canonical Kernel artifact envelope:
 *
 *     [
 *         '_meta' => <canonical header>,
 *         'payload' => <compiled-container-payload>,
 *     ]
 *
 * The builder intentionally does not assemble artifact envelopes directly.
 * Envelope/header ownership remains inside ArtifactEnvelopeFactory.
 *
 * The `container@1` compiled payload shape is:
 *
 *     [
 *         'aliases' => <alias map>,
 *         'compiled' => true,
 *         'kind' => 'compiled',
 *         'parameters' => <parameter map>,
 *         'services' => <service-definition map>,
 *         'tags' => <tag discovery map>,
 *     ]
 *
 * This builder intentionally does not:
 *
 * - compile the container graph;
 * - calculate fingerprints;
 * - read files;
 * - write files;
 * - validate existing artifacts;
 * - inspect runtime containers;
 * - instantiate runtime services;
 * - emit stdout/stderr;
 * - include timestamps, absolute paths, hostnames, user names, process ids,
 *   raw env values, raw config values, closure dumps, or source snippets.
 *
 * @internal
 */
final readonly class CompiledContainerBuilder
{
    private const string KIND_COMPILED = 'compiled';

    private const string PAYLOAD_KEY_ALIASES = 'aliases';
    private const string PAYLOAD_KEY_COMPILED = 'compiled';
    private const string PAYLOAD_KEY_KIND = 'kind';
    private const string PAYLOAD_KEY_PARAMETERS = 'parameters';
    private const string PAYLOAD_KEY_SERVICES = 'services';
    private const string PAYLOAD_KEY_TAGS = 'tags';

    private const int MAX_DEPTH = 32;
    private const int MAX_MAP_KEYS = 8192;
    private const int MAX_LIST_ITEMS = 8192;
    private const int MAX_STRING_BYTES = 2048;

    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SOURCE_SNIPPET_PATTERN = '/<\?php|<\?=|\bfunction\s*\(|\bfn\s*\(|=>\s*\{|;\s*}/i';
    private const string ENV_LIKE_PATTERN = '/\$\{[A-Z_][A-Z0-9_]*}|%env\(|\benv\s*\(/i';
    private const string SENSITIVE_VALUE_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key)(?![A-Za-z0-9])/i';

    public function __construct(
        private ArtifactEnvelopeFactory $envelopeFactory,
    ) {
    }

    /**
     * Builds a canonical `container@1` compiled-container artifact envelope.
     *
     * The fingerprint is supplied by the artifact/fingerprint layer. This
     * builder MUST NOT calculate it.
     *
     * @param array<string, mixed>|null $requires
     *
     * @return array{_meta: array<string, mixed>, payload: array<string, mixed>}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function build(
        DefinitionGraph $graph,
        string $fingerprint,
        ?array $requires = null,
    ): array {
        return $this->envelopeFactory->container(
            fingerprint: $fingerprint,
            payload: self::payload($graph),
            requires: $requires,
        );
    }

    /**
     * Builds the `container@1` compiled payload map.
     *
     * @return array{
     *     aliases: array<string, string>,
     *     compiled: true,
     *     kind: 'compiled',
     *     parameters: array<string, mixed>,
     *     services: array<string, array<string, mixed>>,
     *     tags: array<string, list<array{id: string, priority: int}>>
     * }
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private static function payload(DefinitionGraph $graph): array
    {
        $graphPayload = $graph->toArray();

        self::assertGraphPayloadShape($graphPayload);

        $payload = [
            self::PAYLOAD_KEY_ALIASES => self::normalizeMap(
                $graphPayload[self::PAYLOAD_KEY_ALIASES],
                'payload.aliases',
            ),
            self::PAYLOAD_KEY_COMPILED => true,
            self::PAYLOAD_KEY_KIND => self::KIND_COMPILED,
            self::PAYLOAD_KEY_PARAMETERS => self::normalizeMap(
                $graphPayload[self::PAYLOAD_KEY_PARAMETERS],
                'payload.parameters',
            ),
            self::PAYLOAD_KEY_SERVICES => self::normalizeMap(
                $graphPayload[self::PAYLOAD_KEY_SERVICES],
                'payload.services',
            ),
            self::PAYLOAD_KEY_TAGS => self::normalizeMap(
                $graphPayload[self::PAYLOAD_KEY_TAGS],
                'payload.tags',
            ),
        ];

        self::assertCompiledPayloadShape($payload);

        return $payload;
    }

    private static function isMapArray(array $value): bool
    {
        return $value === [] || !\array_is_list($value);
    }

    /**
     * @param array<string, mixed> $graphPayload
     *
     * @throws ArtifactPayloadInvalidException
     */
    private static function assertGraphPayloadShape(array $graphPayload): void
    {
        self::assertExactKeys(
            $graphPayload,
            [
                self::PAYLOAD_KEY_ALIASES,
                self::PAYLOAD_KEY_PARAMETERS,
                self::PAYLOAD_KEY_SERVICES,
                self::PAYLOAD_KEY_TAGS,
            ],
            'payload',
        );

        foreach (
            [
                self::PAYLOAD_KEY_ALIASES,
                self::PAYLOAD_KEY_PARAMETERS,
                self::PAYLOAD_KEY_SERVICES,
                self::PAYLOAD_KEY_TAGS,
            ] as $key
        ) {
            $value = $graphPayload[$key] ?? null;

            if (!\is_array($value) || !self::isMapArray($value)) {
                throw ArtifactPayloadInvalidException::atPath(
                    'payload.' . $key,
                    ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ArtifactPayloadInvalidException
     */
    private static function assertCompiledPayloadShape(array $payload): void
    {
        self::assertExactKeys(
            $payload,
            [
                self::PAYLOAD_KEY_ALIASES,
                self::PAYLOAD_KEY_COMPILED,
                self::PAYLOAD_KEY_KIND,
                self::PAYLOAD_KEY_PARAMETERS,
                self::PAYLOAD_KEY_SERVICES,
                self::PAYLOAD_KEY_TAGS,
            ],
            'payload',
        );

        if (($payload[self::PAYLOAD_KEY_KIND] ?? null) !== self::KIND_COMPILED) {
            throw ArtifactPayloadInvalidException::atPath(
                'payload.kind',
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        if (($payload[self::PAYLOAD_KEY_COMPILED] ?? null) !== true) {
            throw ArtifactPayloadInvalidException::atPath(
                'payload.compiled',
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $expectedKeys
     *
     * @throws ArtifactPayloadInvalidException
     */
    private static function assertExactKeys(
        array $map,
        array $expectedKeys,
        string $path,
    ): void {
        $actual = \array_keys($map);
        \sort($actual, \SORT_STRING);

        $expected = $expectedKeys;
        \sort($expected, \SORT_STRING);

        if ($actual !== $expected) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }
    }

    /**
     * @param array<string, mixed> $map
     *
     * @return array<string, mixed>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private static function normalizeMap(array $map, string $path): array
    {
        if (!self::isMapArray($map)) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        $normalized = self::normalizeValue($map, $path, 0);

        if (!\is_array($normalized) || !self::isMapArray($normalized)) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        /** @var array<string, mixed> $normalized */
        return $normalized;
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private static function normalizeValue(
        mixed $value,
        string $path,
        int $depth,
    ): null|bool|int|string|array {
        if ($depth > self::MAX_DEPTH) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            self::assertSafePayloadString($value, $path);

            return $value;
        }

        if (\is_float($value)) {
            throw JsonFloatForbiddenException::atPath($path);
        }

        if (\is_resource($value)) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_RESOURCE_FORBIDDEN,
            );
        }

        if ($value instanceof \Closure) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_CLOSURE_FORBIDDEN,
            );
        }

        if (\is_object($value)) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_OBJECT_FORBIDDEN,
            );
        }

        if (!\is_array($value)) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        if (\array_is_list($value)) {
            return self::normalizeList($value, $path, $depth + 1);
        }

        return self::normalizeAssociativeMap($value, $path, $depth + 1);
    }

    /**
     * @param list<mixed> $list
     *
     * @return list<mixed>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private static function normalizeList(array $list, string $path, int $depth): array
    {
        if (\count($list) > self::MAX_LIST_ITEMS) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        $normalized = [];

        foreach ($list as $index => $item) {
            $normalized[] = self::normalizeValue(
                value: $item,
                path: $path . '[' . $index . ']',
                depth: $depth,
            );
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $map
     *
     * @return array<string, mixed>
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private static function normalizeAssociativeMap(array $map, string $path, int $depth): array
    {
        if (\count($map) > self::MAX_MAP_KEYS) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        $normalized = [];

        foreach ($map as $key => $item) {
            if (!\is_string($key)) {
                throw ArtifactPayloadInvalidException::atPath(
                    $path . '[<key>]',
                    ArtifactPayloadInvalidException::REASON_MAP_KEY_MUST_BE_STRING,
                );
            }

            self::assertSafePayloadMapKey($key, $path . '[<key>]');

            $normalized[$key] = self::normalizeValue(
                value: $item,
                path: $path . '[<key>]',
                depth: $depth,
            );
        }

        \uksort(
            $normalized,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $normalized;
    }

    /**
     * @throws ArtifactPayloadInvalidException
     */
    private static function assertSafePayloadMapKey(string $key, string $path): void
    {
        if (
            $key === ''
            || \strlen($key) > self::MAX_STRING_BYTES
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $key) === 1
            || \str_contains($key, '://')
            || \str_contains($key, '::')
            || self::looksLikeAbsolutePath($key)
            || self::looksLikeSourceSnippet($key)
            || self::looksLikeEnvValue($key)
            || self::looksSensitive($key)
        ) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }
    }

    /**
     * @throws ArtifactPayloadInvalidException
     */
    private static function assertSafePayloadString(string $value, string $path): void
    {
        if (
            \strlen($value) > self::MAX_STRING_BYTES
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $value) === 1
            || \str_contains($value, '://')
            || \str_contains($value, '::')
            || self::looksLikeAbsolutePath($value)
            || self::looksLikeSourceSnippet($value)
            || self::looksLikeEnvValue($value)
            || self::looksSensitive($value)
        ) {
            throw ArtifactPayloadInvalidException::atPath(
                $path,
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $value) === 1;
    }

    private static function looksLikeSourceSnippet(string $value): bool
    {
        return \preg_match(self::SOURCE_SNIPPET_PATTERN, $value) === 1;
    }

    private static function looksLikeEnvValue(string $value): bool
    {
        return \preg_match(self::ENV_LIKE_PATTERN, $value) === 1;
    }

    private static function looksSensitive(string $value): bool
    {
        return \preg_match(self::SENSITIVE_VALUE_PATTERN, $value) === 1;
    }
}
