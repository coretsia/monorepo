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

namespace Coretsia\Kernel\Config\Loaders;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;

/**
 * Deterministic environment overlay loader.
 *
 * This loader builds Phase B env overlay config patches from the immutable
 * EnvRepositoryInterface snapshot produced by Bootstrap Phase A.
 *
 * It intentionally does not:
 *
 * - read $_ENV;
 * - read $_SERVER;
 * - call getenv();
 * - enumerate process environment variables;
 * - create config keys from unknown env names;
 * - create overlays for map/list values;
 * - invent rules for user-owned/custom roots.
 *
 * Overlay generation is allowlisted by loaded declarative ConfigRuleset
 * instances and optional explicit env overlay mappings supplied by a future
 * application/module mapping mechanism.
 *
 * Config path projection is deterministic and locale-independent:
 *
 *     kernel.boot.default_env => KERNEL_BOOT_DEFAULT_ENV
 *
 * Projection rules:
 *
 * - ASCII letters are uppercased;
 * - "." maps to "_";
 * - "-" maps to "_";
 * - "_" is preserved;
 * - ASCII digits are preserved.
 *
 * Only scalar baseline env coercion is supported:
 *
 * - string
 * - non-empty-string
 * - non-empty-string-no-ws
 * - bool
 * - int
 *
 * Boolean env coercion is exact and single-choice:
 *
 * - "true" and "1" map to true;
 * - "false" and "0" map to false;
 * - every other token fails deterministically.
 *
 * Diagnostics MUST NOT expose raw env values, raw config values, secrets,
 * tokens, DSNs, cookies, headers, raw SQL, payloads, PHP warnings, stack traces,
 * absolute paths, or previous throwable messages.
 *
 * @internal
 */
final readonly class EnvironmentOverlayLoader
{
    private const int DEFAULT_PRECEDENCE = 500;

    private const string TYPE_STRING = 'string';
    private const string TYPE_NON_EMPTY_STRING = 'non-empty-string';
    private const string TYPE_NON_EMPTY_STRING_NO_WS = 'non-empty-string-no-ws';
    private const string TYPE_BOOL = 'bool';
    private const string TYPE_INT = 'int';

    /**
     * @var array<string, true>
     */
    private const array SUPPORTED_ENV_TYPES = [
        self::TYPE_BOOL => true,
        self::TYPE_INT => true,
        self::TYPE_NON_EMPTY_STRING => true,
        self::TYPE_NON_EMPTY_STRING_NO_WS => true,
        self::TYPE_STRING => true,
    ];

    private const string MAPPING_KIND_RULESET = 'ruleset';
    private const string MAPPING_KIND_EXPLICIT = 'explicit';

    /**
     * Builds one env overlay config patch.
     *
     * `$explicitMappings` is reserved for future user/module-owned env overlay
     * mappings. It is intentionally explicit and path-based, so user-owned roots
     * without rulesets are not env-overlay-expanded automatically.
     *
     * Explicit mapping shape:
     *
     * ```php
     * [
     *     'path' => 'custom.feature.enabled',
     *     'env' => 'CUSTOM_FEATURE_ENABLED',
     *     'type' => 'string',
     *     'sourceId' => 'custom/env/custom_feature_enabled',
     *     'precedence' => 500,
     *     'allowedValues' => ['on', 'off'],
     * ]
     * ```
     *
     * `allowedValues` is optional and is checked after coercion using strict
     * comparison. Values MUST be null/bool/int/string; floats, arrays, objects,
     * resources, and closures are rejected.
     *
     * @param list<ConfigRuleset> $rulesets
     * @param list<array{
     *     path: string,
     *     env: string,
     *     type: string,
     *     sourceId?: string|null,
     *     precedence?: int|null,
     *     allowedValues?: list<null|bool|int|string>
     * }> $explicitMappings
     *
     * @return array{
     *     config: array<string, mixed>,
     *     sources: array<string, ConfigValueSource>,
     *     mappings: list<array{
     *         env: non-empty-string,
     *         kind: non-empty-string,
     *         path: non-empty-string,
     *         root: non-empty-string,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>
     * }
     *
     * @throws ConfigInvalidException
     */
    public function load(
        EnvRepositoryInterface $env,
        array $rulesets,
        array $explicitMappings = [],
        int $precedence = self::DEFAULT_PRECEDENCE,
    ): array {
        if ($precedence < 0) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $mappings = self::collectMappings(
            rulesets: $rulesets,
            explicitMappings: $explicitMappings,
            defaultPrecedence: $precedence,
        );

        if ($mappings === []) {
            return [
                'config' => [],
                'mappings' => [],
                'sources' => [],
            ];
        }

        $config = [];
        $sourceMetadata = [];
        $mappingMetadata = [];

        foreach ($mappings as $mapping) {
            $envValue = $env->get($mapping['env']);

            if ($envValue->isMissing()) {
                continue;
            }

            $coercedValue = self::coerceEnvValue(
                rawValue: $envValue->value(),
                type: $mapping['type'],
            );

            if (!self::allowedValueMatches($mapping['allowedValues'], $coercedValue)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            self::assignConfigPath(
                config: $config,
                path: $mapping['path'],
                value: $coercedValue,
            );

            $source = $env->sourceOf($mapping['env']);

            $sourceMetadata[$mapping['path']] = new ConfigValueSource(
                type: ConfigSourceType::Env,
                root: $mapping['root'],
                sourceId: $mapping['sourceId'],
                path: null,
                keyPath: $mapping['path'],
                directive: null,
                precedence: $mapping['precedence'],
                redacted: true,
                meta: [
                    'envName' => $mapping['env'],
                    'envSourceId' => $source?->sourceId(),
                    'envSourceType' => $source?->type()->value,
                    'kind' => 'env_overlay',
                    'mappingKind' => $mapping['kind'],
                    'valueType' => $mapping['type'],
                ],
            );

            $mappingMetadata[$mapping['path']] = [
                'env' => $mapping['env'],
                'kind' => $mapping['kind'],
                'path' => $mapping['path'],
                'root' => $mapping['root'],
                'sourceId' => $mapping['sourceId'],
                'type' => $mapping['type'],
            ];
        }

        \ksort($config, \SORT_STRING);
        \ksort($sourceMetadata, \SORT_STRING);
        \ksort($mappingMetadata, \SORT_STRING);

        return [
            'config' => $config,
            'mappings' => \array_values($mappingMetadata),
            'sources' => $sourceMetadata,
        ];
    }

    /**
     * Projects a config dot path to its canonical env name.
     *
     * @return non-empty-string
     *
     * @throws ConfigInvalidException
     */
    public static function envNameForConfigPath(string $path): string
    {
        self::assertSafeConfigPath($path);

        $env = '';

        for ($i = 0; $i < \strlen($path); $i++) {
            $char = $path[$i];

            if ($char === '.' || $char === '-') {
                $env .= '_';

                continue;
            }

            if ($char >= 'a' && $char <= 'z') {
                $env .= \chr(\ord($char) - 32);

                continue;
            }

            if (
                ($char >= 'A' && $char <= 'Z')
                || ($char >= '0' && $char <= '9')
                || $char === '_'
            ) {
                $env .= $char;

                continue;
            }

            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if ($env === '') {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $env;
    }

    /**
     * @param list<ConfigRuleset> $rulesets
     * @param list<array<string, mixed>> $explicitMappings
     *
     * @return array<string, array{
     *     allowedValues: list<null|bool|int|string>|null,
     *     env: non-empty-string,
     *     kind: non-empty-string,
     *     path: non-empty-string,
     *     precedence: int,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * }>
     *
     * @throws ConfigInvalidException
     */
    private static function collectMappings(
        array $rulesets,
        array $explicitMappings,
        int $defaultPrecedence,
    ): array {
        if (!\array_is_list($rulesets) || !\array_is_list($explicitMappings)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $mappings = [];

        foreach ($rulesets as $ruleset) {
            if (!$ruleset instanceof ConfigRuleset) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            self::collectRulesetMappings(
                root: $ruleset->root(),
                rule: $ruleset->rules(),
                path: $ruleset->root(),
                mappings: $mappings,
                precedence: $defaultPrecedence,
            );
        }

        foreach ($explicitMappings as $mapping) {
            self::addExplicitMapping(
                mappings: $mappings,
                mapping: $mapping,
                defaultPrecedence: $defaultPrecedence,
            );
        }

        return self::normalizeMappingsForReturn($mappings);
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, array{
     *     allowedValues: list<null|bool|int|string>|null,
     *     env: string,
     *     kind: string,
     *     path: string,
     *     precedence: int,
     *     root: string,
     *     sourceId: string,
     *     type: string
     * }> $mappings
     *
     * @throws ConfigInvalidException
     */
    private static function collectRulesetMappings(
        string $root,
        array $rule,
        string $path,
        array &$mappings,
        int $precedence,
    ): void {
        if (!self::isValidRoot($root)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (isset($rule['keys'])) {
            if (!\is_array($rule['keys']) || \array_is_list($rule['keys'])) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if (isset($rule['type']) && $rule['type'] !== 'map') {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            $keys = $rule['keys'];

            \ksort($keys, \SORT_STRING);

            foreach ($keys as $key => $childRule) {
                if (!\is_string($key) || !self::isSafePathSegment($key)) {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_SOURCE_INVALID,
                    );
                }

                if (!\is_array($childRule) || (\array_is_list($childRule) && $childRule !== [])) {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_SOURCE_INVALID,
                    );
                }

                self::collectRulesetMappings(
                    root: $root,
                    rule: $childRule,
                    path: $path . '.' . $key,
                    mappings: $mappings,
                    precedence: $precedence,
                );
            }

            return;
        }

        if (isset($rule['type']) && \is_string($rule['type']) && isset(self::SUPPORTED_ENV_TYPES[$rule['type']])) {
            $envName = self::envNameForConfigPath($path);

            self::addMapping(
                mappings: $mappings,
                path: $path,
                env: $envName,
                type: $rule['type'],
                kind: self::MAPPING_KIND_RULESET,
                precedence: $precedence,
                sourceId: 'env-overlay/ruleset/' . $envName,
                allowedValues: self::normalizeAllowedValues($rule['allowedValues'] ?? null),
            );

            return;
        }

        if (!isset($rule['type']) || $rule['type'] === 'map' || $rule['type'] === 'list') {
            return;
        }

        throw ConfigInvalidException::withReason(
            ConfigInvalidException::REASON_SOURCE_INVALID,
        );
    }

    /**
     * @param array<string, array{
     *     allowedValues: list<null|bool|int|string>|null,
     *     env: string,
     *     kind: string,
     *     path: string,
     *     precedence: int,
     *     root: string,
     *     sourceId: string,
     *     type: string
     * }> $mappings
     * @param array<string, mixed> $mapping
     *
     * @throws ConfigInvalidException
     */
    private static function addExplicitMapping(
        array &$mappings,
        array $mapping,
        int $defaultPrecedence,
    ): void {
        $path = self::requiredString($mapping, 'path');
        $env = self::requiredString($mapping, 'env');
        $type = self::requiredString($mapping, 'type');
        $sourceId = self::optionalString($mapping, 'sourceId');
        $precedence = self::optionalNonNegativeInt($mapping, 'precedence') ?? $defaultPrecedence;

        self::assertSafeConfigPath($path);
        self::assertSafeEnvName($env);

        if (!isset(self::SUPPORTED_ENV_TYPES[$type])) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $root = self::rootFromPath($path);

        $sourceId ??= 'env-overlay/explicit/' . $env;

        self::addMapping(
            mappings: $mappings,
            path: $path,
            env: $env,
            type: $type,
            kind: self::MAPPING_KIND_EXPLICIT,
            precedence: $precedence,
            sourceId: $sourceId,
            allowedValues: self::normalizeAllowedValues($mapping['allowedValues'] ?? null),
        );
    }

    /**
     * @param array<string, array{
     *     allowedValues: list<null|bool|int|string>|null,
     *     env: string,
     *     kind: string,
     *     path: string,
     *     precedence: int,
     *     root: string,
     *     sourceId: string,
     *     type: string
     * }> $mappings
     *
     * @return array<string, array{
     *     allowedValues: list<null|bool|int|string>|null,
     *     env: non-empty-string,
     *     kind: non-empty-string,
     *     path: non-empty-string,
     *     precedence: int,
     *     root: non-empty-string,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * }>
     *
     * @throws ConfigInvalidException
     */
    private static function normalizeMappingsForReturn(array $mappings): array
    {
        $normalized = [];

        foreach ($mappings as $mapping) {
            $path = $mapping['path'];
            $env = $mapping['env'];
            $kind = $mapping['kind'];
            $root = $mapping['root'];
            $sourceId = $mapping['sourceId'];
            $type = $mapping['type'];
            $precedence = $mapping['precedence'];
            $allowedValues = $mapping['allowedValues'];

            self::assertSafeConfigPath($path);
            self::assertSafeEnvName($env);
            self::assertSafeSourceId($sourceId);

            if (
                $kind === ''
                || $root === ''
                || $type === ''
                || $precedence < 0
                || !self::isValidRoot($root)
                || !isset(self::SUPPORTED_ENV_TYPES[$type])
            ) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            /** @var non-empty-string $path */
            /** @var non-empty-string $env */
            /** @var non-empty-string $kind */
            /** @var non-empty-string $root */
            /** @var non-empty-string $sourceId */
            /** @var non-empty-string $type */

            $normalized[$path] = [
                'allowedValues' => $allowedValues,
                'env' => $env,
                'kind' => $kind,
                'path' => $path,
                'precedence' => $precedence,
                'root' => $root,
                'sourceId' => $sourceId,
                'type' => $type,
            ];
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string, array{
     *     allowedValues: list<null|bool|int|string>|null,
     *     env: string,
     *     kind: string,
     *     path: string,
     *     precedence: int,
     *     root: string,
     *     sourceId: string,
     *     type: string
     * }> $mappings
     * @param list<null|bool|int|string>|null $allowedValues
     *
     * @throws ConfigInvalidException
     */
    private static function addMapping(
        array &$mappings,
        string $path,
        string $env,
        string $type,
        string $kind,
        int $precedence,
        string $sourceId,
        ?array $allowedValues,
    ): void {
        self::assertSafeConfigPath($path);
        self::assertSafeEnvName($env);
        self::assertSafeSourceId($sourceId);

        if ($precedence < 0 || !isset(self::SUPPORTED_ENV_TYPES[$type])) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        /** @var non-empty-string $path */
        /** @var non-empty-string $env */
        /** @var non-empty-string $type */
        /** @var non-empty-string $kind */
        /** @var non-empty-string $sourceId */

        $root = self::rootFromPath($path);

        if (isset($mappings[$path])) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $mappings[$path] = [
            'allowedValues' => $allowedValues,
            'env' => $env,
            'kind' => $kind,
            'path' => $path,
            'precedence' => $precedence,
            'root' => $root,
            'sourceId' => $sourceId,
            'type' => $type,
        ];
    }

    /**
     * @param array<string, mixed> $source
     *
     * @throws ConfigInvalidException
     */
    private static function requiredString(array $source, string $key): string
    {
        if (!\array_key_exists($key, $source) || !\is_string($source[$key]) || $source[$key] === '') {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $source[$key];
    }

    /**
     * @param array<string, mixed> $source
     *
     * @throws ConfigInvalidException
     */
    private static function optionalString(array $source, string $key): ?string
    {
        if (!\array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return null;
        }

        if (!\is_string($source[$key])) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $source[$key];
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return int<0, max>|null
     *
     * @throws ConfigInvalidException
     */
    private static function optionalNonNegativeInt(array $source, string $key): ?int
    {
        if (!\array_key_exists($key, $source) || $source[$key] === null) {
            return null;
        }

        if (!\is_int($source[$key]) || $source[$key] < 0) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $source[$key];
    }

    /**
     * @return list<null|bool|int|string>|null
     *
     * @throws ConfigInvalidException
     */
    private static function normalizeAllowedValues(mixed $allowedValues): ?array
    {
        if ($allowedValues === null) {
            return null;
        }

        if (!\is_array($allowedValues) || !\array_is_list($allowedValues)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $normalized = [];

        foreach ($allowedValues as $allowedValue) {
            if (
                $allowedValue !== null
                && !\is_bool($allowedValue)
                && !\is_int($allowedValue)
                && !\is_string($allowedValue)
            ) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            $normalized[] = $allowedValue;
        }

        return $normalized;
    }

    /**
     * @param list<null|bool|int|string>|null $allowedValues
     */
    private static function allowedValueMatches(?array $allowedValues, mixed $value): bool
    {
        if ($allowedValues === null) {
            return true;
        }

        foreach ($allowedValues as $allowedValue) {
            if ($allowedValue === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool|int|string
     *
     * @throws ConfigInvalidException
     */
    private static function coerceEnvValue(
        string $rawValue,
        string $type,
    ): bool|int|string {
        return match ($type) {
            self::TYPE_STRING => $rawValue,
            self::TYPE_NON_EMPTY_STRING => self::coerceNonEmptyString($rawValue),
            self::TYPE_NON_EMPTY_STRING_NO_WS => self::coerceNonEmptyStringNoWs($rawValue),
            self::TYPE_BOOL => self::coerceBool($rawValue),
            self::TYPE_INT => self::coerceInt($rawValue),
            default => throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            ),
        };
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function coerceNonEmptyString(string $rawValue): string
    {
        if ($rawValue === '') {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $rawValue;
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function coerceNonEmptyStringNoWs(string $rawValue): string
    {
        if ($rawValue === '' || \preg_match('/\s/u', $rawValue) === 1) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $rawValue;
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function coerceBool(string $rawValue): bool
    {
        return match ($rawValue) {
            'true',
            '1' => true,

            'false',
            '0' => false,

            default => throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            ),
        };
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function coerceInt(string $rawValue): int
    {
        if (\preg_match('/\A(?:0|-?[1-9][0-9]*)\z/', $rawValue) !== 1) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $value = (int)$rawValue;

        if ((string)$value !== $rawValue) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws ConfigInvalidException
     */
    private static function assignConfigPath(
        array &$config,
        string $path,
        null|bool|int|string $value,
    ): void {
        self::assertSafeConfigPath($path);

        $segments = \explode('.', $path);
        $cursor = &$config;

        foreach ($segments as $index => $segment) {
            if ($index === \count($segments) - 1) {
                if (\array_key_exists($segment, $cursor)) {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_SOURCE_INVALID,
                    );
                }

                $cursor[$segment] = $value;

                return;
            }

            if (!\array_key_exists($segment, $cursor)) {
                $cursor[$segment] = [];
            }

            if (
                !\is_array($cursor[$segment])
                || ($cursor[$segment] !== [] && \array_is_list($cursor[$segment]))
            ) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            $cursor = &$cursor[$segment];
        }
    }

    /**
     * @return non-empty-string
     *
     * @throws ConfigInvalidException
     */
    private static function rootFromPath(string $path): string
    {
        self::assertSafeConfigPath($path);

        $root = \explode('.', $path, 2)[0];

        if (!self::isValidRoot($root)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $root;
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertSafeConfigPath(string $path): void
    {
        if ($path === '' || \str_contains($path, "\0") || \str_contains($path, "\r") || \str_contains($path, "\n")) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (\str_contains($path, '..') || \str_starts_with($path, '.') || \str_ends_with($path, '.')) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $segments = \explode('.', $path);

        if (\count($segments) < 2) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        foreach ($segments as $index => $segment) {
            if ($segment === '' || !self::isSafePathSegment($segment)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if ($index === 0 && !self::isValidRoot($segment)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }
        }
    }

    private static function isSafePathSegment(string $segment): bool
    {
        return \preg_match('/\A[A-Za-z_][A-Za-z0-9_-]*\z/', $segment) === 1;
    }

    private static function isValidRoot(string $root): bool
    {
        return \preg_match('/\A[a-z][a-z0-9_]*\z/', $root) === 1;
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertSafeEnvName(string $env): void
    {
        if ($env === '' || \preg_match('/\A[A-Z][A-Z0-9_]*\z/', $env) !== 1) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertSafeSourceId(string $sourceId): void
    {
        if ($sourceId === '') {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (\preg_match('/\s/u', $sourceId) === 1) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (
            \str_contains($sourceId, "\0")
            || \str_contains($sourceId, "\r")
            || \str_contains($sourceId, "\n")
            || \str_contains($sourceId, ':')
            || \str_contains($sourceId, '://')
            || \str_starts_with($sourceId, '/')
            || \str_starts_with($sourceId, '\\')
        ) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $normalized = \str_replace('\\', '/', $sourceId);

        if (
            $normalized === '..'
            || \str_starts_with($normalized, '../')
            || \str_contains($normalized, '/../')
            || \str_ends_with($normalized, '/..')
        ) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }
    }
}
