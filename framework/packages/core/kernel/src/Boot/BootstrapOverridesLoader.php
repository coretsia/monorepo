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
 * - debug
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
    private const string KEY_DEBUG = 'debug';

    /**
     * @var array<string, true>
     */
    private const array ALLOWED_KEYS = [
        self::KEY_APP_ENV => true,
        self::KEY_PRESET => true,
        self::KEY_DEBUG => true,
    ];

    /**
     * Loads bootstrap-only overrides from skeleton/config/app.php.
     *
     * Missing file is treated as no overrides.
     *
     * @return array{
     *     appEnv?: non-empty-string,
     *     preset?: non-empty-string,
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

        $out = [];

        foreach ($payload as $key => $value) {
            if (!\is_string($key) || !isset(self::ALLOWED_KEYS[$key])) {
                throw BootstrapException::withReason(
                    BootstrapException::REASON_OVERRIDES_INVALID,
                );
            }

            if ($key === self::KEY_APP_ENV) {
                if (!\is_string($value) || !self::isNonEmptySafeSingleLineString($value)) {
                    throw BootstrapException::withReason(
                        BootstrapException::REASON_OVERRIDES_INVALID,
                    );
                }

                $out[self::KEY_APP_ENV] = $value;

                continue;
            }

            if ($key === self::KEY_PRESET) {
                if (!\is_string($value) || !self::isNonEmptySafeSingleLineString($value)) {
                    throw BootstrapException::withReason(
                        BootstrapException::REASON_OVERRIDES_INVALID,
                    );
                }

                $out[self::KEY_PRESET] = $value;

                continue;
            }

            if (!\is_bool($value)) {
                throw BootstrapException::withReason(
                    BootstrapException::REASON_OVERRIDES_INVALID,
                );
            }

            $out[self::KEY_DEBUG] = $value;
        }

        /**
         * @var array{
         *     appEnv?: non-empty-string,
         *     preset?: non-empty-string,
         *     debug?: bool
         * } $out
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
