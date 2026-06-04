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

use Coretsia\Kernel\Boot\Exception\BootstrapException;

/**
 * Loads optional Bootstrap Phase A overrides from skeleton/config/app.php.
 *
 * This file is a bootstrap-only input file. It is not a config root and MUST
 * NOT participate in ConfigKernel Phase B merge.
 *
 * The loader reads exactly one optional file:
 *
 * skeleton/config/app.php
 *
 * It MUST NOT read skeleton/config/modules.php or any full config file.
 *
 * Allowed override keys are intentionally narrow:
 *
 * - appEnv
 * - preset
 * - presets
 * - debug
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
 * Module enable/disable composition is not handled here. It belongs to the
 * ModulePlan epic and is resolved from preset files plus composer metadata.
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
    private const string KEY_DEBUG = 'debug';

    /**
     * @var array<string, true>
     */
    private const array ALLOWED_KEYS = [
        self::KEY_APP_ENV => true,
        self::KEY_PRESET => true,
        self::KEY_PRESETS => true,
        self::KEY_DEBUG => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array APP_TARGETS = [
        'api' => true,
        'console' => true,
        'web' => true,
        'worker' => true,
    ];

    /**
     * Loads bootstrap-only overrides from skeleton/config/app.php.
     *
     * Missing file is treated as no overrides.
     *
     * @return array{
     *     appEnv?: non-empty-string,
     *     preset?: non-empty-string,
     *     presets?: array<string, non-empty-string>,
     *     debug?: bool
     * }
     */
    public function load(BootstrapInput $input): array
    {
        $file = self::joinPath($input->skeletonRoot(), self::OVERRIDE_FILE);

        if (!\file_exists($file)) {
            return [];
        }

        if (!\is_file($file) || !\is_readable($file)) {
            throw BootstrapException::withReason(
                BootstrapException::REASON_OVERRIDES_INVALID,
            );
        }

        $payload = self::loadArrayFile($file);

        return self::normalizeOverrides($payload);
    }

    private static function joinPath(string $root, string $relativePath): string
    {
        $root = \rtrim($root, '/\\');

        if ($root === '') {
            return $relativePath;
        }

        return $root . '/' . $relativePath;
    }

    /**
     * @return array<mixed>
     */
    private static function loadArrayFile(string $file): array
    {
        \set_error_handler(
            static function (): never {
                throw BootstrapException::withReason(
                    BootstrapException::REASON_OVERRIDES_INVALID,
                );
            },
        );

        try {
            /** @var mixed $payload */
            $payload = include $file;
        } catch (BootstrapException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw BootstrapException::withReason(
                BootstrapException::REASON_OVERRIDES_INVALID,
            );
        } finally {
            \restore_error_handler();
        }

        if (!\is_array($payload)) {
            throw BootstrapException::withReason(
                BootstrapException::REASON_OVERRIDES_INVALID,
            );
        }

        return $payload;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array{
     *     appEnv?: non-empty-string,
     *     preset?: non-empty-string,
     *     presets?: array<string, non-empty-string>,
     *     debug?: bool
     * }
     */
    private static function normalizeOverrides(array $payload): array
    {
        if (\array_is_list($payload) && $payload !== []) {
            throw BootstrapException::withReason(
                BootstrapException::REASON_OVERRIDES_INVALID,
            );
        }

        /**
         * @var array{
         *     appEnv?: non-empty-string,
         *     preset?: non-empty-string,
         *     presets?: array<string, non-empty-string>,
         *     debug?: bool
         * } $out
         */
        $out = [];

        foreach ($payload as $key => $value) {
            if (!\is_string($key) || !isset(self::ALLOWED_KEYS[$key])) {
                throw BootstrapException::withReason(
                    BootstrapException::REASON_OVERRIDES_INVALID,
                );
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

            if (!\is_bool($value)) {
                throw BootstrapException::withReason(
                    BootstrapException::REASON_OVERRIDES_INVALID,
                );
            }

            $out[self::KEY_DEBUG] = $value;
        }

        return $out;
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeSafeStringOverride(mixed $value): string
    {
        if (!\is_string($value) || !self::isNonEmptySafeSingleLineString($value)) {
            throw BootstrapException::withReason(
                BootstrapException::REASON_OVERRIDES_INVALID,
            );
        }

        return $value;
    }

    /**
     * @return array<string, non-empty-string>
     */
    private static function normalizePresets(mixed $value): array
    {
        if (!\is_array($value)) {
            throw BootstrapException::withReason(
                BootstrapException::REASON_OVERRIDES_INVALID,
            );
        }

        if ($value !== [] && \array_is_list($value)) {
            throw BootstrapException::withReason(
                BootstrapException::REASON_OVERRIDES_INVALID,
            );
        }

        $out = [];

        foreach ($value as $appTarget => $presetName) {
            if (!\is_string($appTarget) || !isset(self::APP_TARGETS[$appTarget])) {
                throw BootstrapException::withReason(
                    BootstrapException::REASON_OVERRIDES_INVALID,
                );
            }

            $out[$appTarget] = self::normalizeSafeStringOverride($presetName);
        }

        \ksort($out, \SORT_STRING);

        /**
         * @var array<string, non-empty-string> $out
         */
        return $out;
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
