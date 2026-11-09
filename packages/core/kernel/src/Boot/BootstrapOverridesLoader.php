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

namespace Coretsia\Kernel\Boot;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Boot\Exception\BootstrapException;

/**
 * Loads optional Bootstrap Phase A overrides from config/app.php.
 *
 * This file is a bootstrap-only input file. It is not a config root and MUST
 * NOT participate in ConfigKernel Phase B merge.
 *
 * The loader reads exactly one optional file:
 *
 * config/app.php
 *
 * It MUST NOT read config/modules.php or any full config file.
 *
 * Allowed override keys are intentionally narrow:
 *
 * - appEnv
 * - preset
 * - presets
 * - activeTargets
 * - moduleOverrides
 * - debug
 * - artifactsCacheDir
 *
 * `preset` is a global fallback preset override.
 *
 * `presets` is a bootstrap-only per-app preset override map:
 *
 *     [
 *         'api' => 'micro',
 *         'web' => 'express',
 *         'console' => 'hybrid',
 *         'worker' => 'enterprise',
 *     ]
 *
 * `presets` may be partial. Empty `presets` behaves as absent.
 *
 * `presets` does not select, infer, or modify app target. BootstrapConfigResolver
 * owns final preset precedence for the already selected explicit app target.
 *
 * Per-target moduleOverrides are shape-validated and every ModuleId is
 * lexically checked (including entries for inactive targets). ModuleGraph
 * rules, include/exclude conflicts and dependency policy remain Kernel owners.
 * Partial normalization preserves absent include/exclude until source merge.
 *
 * Values are never embedded in exception messages. Invalid overrides fail with
 * BootstrapException::REASON_OVERRIDES_INVALID.
 *
 * @internal
 */
final readonly class BootstrapOverridesLoader
{
    private const string OVERRIDE_FILE = 'config/app.php';

    private const string KEY_APP_ENV = 'appEnv';
    private const string KEY_PRESET = 'preset';
    private const string KEY_PRESETS = 'presets';
    private const string KEY_MODULE_OVERRIDES = 'moduleOverrides';
    private const string KEY_ACTIVE_TARGETS = 'activeTargets';
    private const string KEY_DEBUG = 'debug';
    private const string KEY_ARTIFACTS_CACHE_DIR = 'artifactsCacheDir';

    /**
     * @var array<string, true>
     */
    private const array ALLOWED_KEYS = [
        self::KEY_APP_ENV => true,
        self::KEY_PRESET => true,
        self::KEY_PRESETS => true,
        self::KEY_MODULE_OVERRIDES => true,
        self::KEY_ACTIVE_TARGETS => true,
        self::KEY_DEBUG => true,
        self::KEY_ARTIFACTS_CACHE_DIR => true,
    ];

    /**
     * Loads bootstrap-only overrides from config/app.php.
     *
     * Missing file is treated as no overrides.
     *
     * @return array{
     *     appEnv?: non-empty-string,
     *     preset?: non-empty-string,
     *     presets?: array<string, non-empty-string>,
     *     activeTargets?: non-empty-list<string>,
     *     moduleOverrides?: array<string, array{include:list<string>, exclude:list<string>}>,
     *     debug?: bool,
     *     artifactsCacheDir?: non-empty-string
     * }
     */
    public function load(BootstrapInput $input): array
    {
        return self::completeModuleOverrides(
            $this->loadPartialFromApplicationRoot($input->applicationRoot()),
        );
    }

    /**
     * Project-wide Phase A source layer. Does not select an AppTarget.
     * Absent include/exclude fields stay absent until source merge finishes.
     *
     * @return array<string,mixed>
     */
    public function loadPartialFromApplicationRoot(string $applicationRoot): array
    {
        if (!self::isNonEmptySafeSingleLineString($applicationRoot)) {
            throw BootstrapException::withReason(BootstrapException::REASON_INVALID_APPLICATION_ROOT);
        }

        $file = self::joinPath($applicationRoot, self::OVERRIDE_FILE);

        if (!\file_exists($file)) {
            if (\is_link($file)) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            return [];
        }

        if (!\is_file($file) || !\is_readable($file)) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        return self::normalizePartialOverrides(self::loadArrayFile($file));
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array{
     *     appEnv?: non-empty-string,
     *     preset?: non-empty-string,
     *     presets?: array<string, non-empty-string>,
     *     activeTargets?: non-empty-list<string>,
     *     moduleOverrides?: array<string, array{include?:list<string>, exclude?:list<string>}>,
     *     debug?: bool,
     *     artifactsCacheDir?: non-empty-string
     * }
     */
    public static function normalizePartialOverrides(array $payload): array
    {
        if (\array_is_list($payload) && $payload !== []) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        /**
         * @var array{
         *     appEnv?: non-empty-string,
         *     preset?: non-empty-string,
         *     presets?: array<string, non-empty-string>,
         *     activeTargets?: non-empty-list<string>,
         *     moduleOverrides?: array<string, array{include?:list<string>, exclude?:list<string>}>,
         *     debug?: bool,
         *     artifactsCacheDir?: non-empty-string
         * } $out
         */
        $out = [];

        foreach ($payload as $key => $value) {
            if (!\is_string($key) || !isset(self::ALLOWED_KEYS[$key])) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            if ($key === self::KEY_APP_ENV) {
                $out[self::KEY_APP_ENV] = self::normalizeSafeStringOverride($value);
                continue;
            }

            if ($key === self::KEY_PRESET) {
                $out[self::KEY_PRESET] = self::normalizeSafeStringOverride($value);
                continue;
            }

            if ($key === self::KEY_PRESETS) {
                $presets = self::normalizePresets($value);

                if ($presets !== []) {
                    $out[self::KEY_PRESETS] = $presets;
                }

                continue;
            }

            if ($key === self::KEY_MODULE_OVERRIDES) {
                $out[self::KEY_MODULE_OVERRIDES] = self::normalizeModuleOverrides($value);
                continue;
            }

            if ($key === self::KEY_ACTIVE_TARGETS) {
                $out[self::KEY_ACTIVE_TARGETS] = self::normalizeActiveTargets($value);
                continue;
            }

            if ($key === self::KEY_ARTIFACTS_CACHE_DIR) {
                $out[self::KEY_ARTIFACTS_CACHE_DIR] = self::normalizeArtifactsCacheDirOverride($value);
                continue;
            }

            if (!\is_bool($value)) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            $out[self::KEY_DEBUG] = $value;
        }

        return $out;
    }

    /**
     * Restores the legacy complete moduleOverrides output shape only after
     * validation. Source orchestration uses the partial shape instead.
     *
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    public static function completeModuleOverrides(array $overrides): array
    {
        foreach ($overrides[self::KEY_MODULE_OVERRIDES] ?? [] as $target => $entry) {
            $overrides[self::KEY_MODULE_OVERRIDES][$target] = [
                'include' => $entry['include'] ?? [],
                'exclude' => $entry['exclude'] ?? [],
            ];
        }

        return $overrides;
    }

    private static function joinPath(string $root, string $relativePath): string
    {
        $originalRoot = $root;
        $root = \rtrim($root, '/\\');

        if ($root === '') {
            return $originalRoot === '' ? $relativePath : $originalRoot[0] . $relativePath;
        }

        return $root . '/' . $relativePath;
    }

    /**
     * @return array<mixed>
     */
    private static function loadArrayFile(string $file): array
    {
        $initialBufferLevel = \ob_get_level();
        $outputProduced = false;
        $bufferDisciplineValid = true;
        $handlerDisciplineValid = true;
        $includeFailed = false;
        $payload = null;
        $handlerInstalled = false;
        $priorHandler = null;

        $handler = static function (): never {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        };

        $capture = static function (string $chunk) use (&$outputProduced): string {
            if ($chunk !== '') {
                $outputProduced = true;
            }

            return '';
        };

        if (!\ob_start($capture)) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        try {
            \ob_start($capture);
            $priorHandler = \set_error_handler($handler);
            $handlerInstalled = true;

            try {
                /** @var mixed $payload */
                $payload = (static function (string $source): mixed {
                    return include $source;
                })(
                    $file,
                );
            } catch (\Throwable) {
                $includeFailed = true;
            }

            if (\ob_get_level() !== $initialBufferLevel + 2) {
                $bufferDisciplineValid = false;
            }
        } finally {
            $cleanupHandlerInstalled = false;

            try {
                \set_error_handler(static function (): bool {
                    return true;
                });
                $cleanupHandlerInstalled = true;

                while (\ob_get_level() > $initialBufferLevel) {
                    $pending = \ob_get_contents();

                    if ($pending === false) {
                        $bufferDisciplineValid = false;
                    } elseif ($pending !== '') {
                        $outputProduced = true;
                    }

                    $levelBeforeClose = \ob_get_level();

                    try {
                        self::cleanOutputBuffer($levelBeforeClose);
                    } catch (\Throwable) {
                        $bufferDisciplineValid = false;
                    }

                    if (\ob_get_level() >= $levelBeforeClose) {
                        $bufferDisciplineValid = false;
                        break;
                    }
                }
            } catch (\Throwable) {
                $bufferDisciplineValid = false;
            } finally {
                if ($cleanupHandlerInstalled) {
                    \restore_error_handler();
                }

                if ($handlerInstalled) {
                    try {
                        $handlerDisciplineValid = self::restoreIncludeErrorHandler(
                            $handler,
                            $priorHandler,
                        );
                    } catch (\Throwable) {
                        $handlerDisciplineValid = false;
                    }
                }

                if (\ob_get_level() !== $initialBufferLevel) {
                    $bufferDisciplineValid = false;
                }
            }
        }

        if (
            $includeFailed || $outputProduced || !$bufferDisciplineValid
            || !$handlerDisciplineValid || !\is_array($payload)
        ) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        return $payload;
    }

    /**
     * Closes exactly one output buffer. User-defined output handlers may throw.
     */
    private static function cleanOutputBuffer(int $levelBeforeClose): void
    {
        \ob_end_clean();

        if (\ob_get_level() !== $levelBeforeClose - 1) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }
    }

    /**
     * Restores the previous handler and rejects unexpected handler changes.
     */
    private static function restoreIncludeErrorHandler(\Closure $guard, mixed $previous): bool
    {
        $unchanged = true;
        $probe = static function (): bool {
            return true;
        };

        while (true) {
            $current = \set_error_handler($probe);
            \restore_error_handler();

            if ($current === $guard) {
                \restore_error_handler();

                return $unchanged;
            }

            // The include may already have removed our guard. Do not pop
            // an error handler belonging to the caller.
            if ($current === $previous) {
                return false;
            }

            // The include may have popped handlers owned by the caller.
            // A missing stack bottom cannot be reconstructed in-process.
            if ($current === null) {
                if ($previous !== null) {
                    \set_error_handler($previous);
                }

                return false;
            }

            $unchanged = false;

            \restore_error_handler();
        }
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeSafeStringOverride(mixed $value): string
    {
        if (!\is_string($value) || !self::isNonEmptySafeSingleLineString($value)) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        return $value;
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeArtifactsCacheDirOverride(mixed $value): string
    {
        if (!BootstrapArtifactsCacheDir::isValid($value)) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        /** @var non-empty-string $value */
        return $value;
    }

    /**
     * @return array<string, non-empty-string>
     */
    private static function normalizePresets(mixed $value): array
    {
        if (!\is_array($value)) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        if ($value !== [] && \array_is_list($value)) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        $out = [];

        foreach ($value as $appTarget => $presetName) {
            if (!\is_string($appTarget) || !AppTarget::isKnown($appTarget)) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            $out[$appTarget] = self::normalizeSafeStringOverride($presetName);
        }

        \ksort($out, \SORT_STRING);

        /**
         * @var array<string, non-empty-string> $out
         */
        return $out;
    }

    /** @return list<string> */
    private static function normalizeActiveTargets(mixed $value): array
    {
        if (!\is_array($value) || !\array_is_list($value) || $value === []) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        $targets = [];
        $seen = [];

        foreach ($value as $target) {
            if (!\is_string($target) || !AppTarget::isKnown($target) || isset($seen[$target])) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            $seen[$target] = true;
            $targets[] = $target;
        }

        \sort($targets, \SORT_STRING);

        return $targets;
    }

    /** @return array<string, array{include?:list<string>, exclude?:list<string>}> */
    private static function normalizeModuleOverrides(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && \array_is_list($value))) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        $out = [];

        foreach ($value as $target => $entry) {
            if (
                !\is_string($target) || !AppTarget::isKnown($target)
                || !\is_array($entry) || ($entry !== [] && \array_is_list($entry))
            ) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            $normalized = [];

            foreach ($entry as $key => $list) {
                if ($key !== 'include' && $key !== 'exclude') {
                    throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
                }

                $normalized[$key] = self::normalizeModuleIdList($list);
            }

            $out[$target] = $normalized;
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    /** @return list<string> */
    private static function normalizeModuleIdList(mixed $value): array
    {
        if (!\is_array($value) || !\array_is_list($value)) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        $seen = [];

        foreach ($value as $id) {
            if (!\is_string($id) || !self::isNonEmptySafeSingleLineString($id)) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            try {
                $canonical = ModuleId::fromString($id)->value();
            } catch (\InvalidArgumentException) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            if ($canonical !== $id || isset($seen[$canonical])) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            $seen[$canonical] = true;
        }

        return $value;
    }

    private static function isNonEmptySafeSingleLineString(string $value): bool
    {
        return $value !== ''
            && \trim($value) === $value
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n")
            && \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
