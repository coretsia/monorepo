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

namespace Coretsia\Kernel\Module\Exception;

/**
 * Deterministic invalid mode preset failure.
 *
 * Used when a mode preset file exists but fails shape, schema, or safety
 * validation.
 *
 * Diagnostics intentionally expose only the safe preset name and stable reason
 * token. They must not expose preset file paths, skeleton root, defaults path,
 * overrides path, raw preset arrays, raw config payloads, or PHP warnings.
 *
 * @internal
 */
final class ModePresetInvalidException extends ModuleResolutionException
{
    public const string REASON_PRESET_INVALID = 'mode-preset-invalid';
    public const string REASON_PRESET_RETURN_TYPE_INVALID = 'mode-preset-return-type-invalid';
    public const string REASON_PRESET_ROOT_WRAPPER_FORBIDDEN = 'mode-preset-root-wrapper-forbidden';
    public const string REASON_SCHEMA_VERSION_INVALID = 'mode-preset-schema-version-invalid';
    public const string REASON_NAME_INVALID = 'mode-preset-name-invalid';
    public const string REASON_NAME_MISMATCH = 'mode-preset-name-mismatch';
    public const string REASON_DESCRIPTION_INVALID = 'mode-preset-description-invalid';
    public const string REASON_LIST_INVALID = 'mode-preset-list-invalid';
    public const string REASON_MODULE_ID_INVALID = 'mode-preset-module-id-invalid';
    public const string REASON_SETS_OVERLAP = 'mode-preset-sets-overlap';
    public const string REASON_FEATURE_BUNDLES_INVALID = 'mode-preset-feature-bundles-invalid';
    public const string REASON_METADATA_INVALID = 'mode-preset-metadata-invalid';
    public const string REASON_PATH_LIKE_VALUE_FORBIDDEN = 'mode-preset-path-like-value-forbidden';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_PRESET_INVALID => true,
        self::REASON_PRESET_RETURN_TYPE_INVALID => true,
        self::REASON_PRESET_ROOT_WRAPPER_FORBIDDEN => true,
        self::REASON_SCHEMA_VERSION_INVALID => true,
        self::REASON_NAME_INVALID => true,
        self::REASON_NAME_MISMATCH => true,
        self::REASON_DESCRIPTION_INVALID => true,
        self::REASON_LIST_INVALID => true,
        self::REASON_MODULE_ID_INVALID => true,
        self::REASON_SETS_OVERLAP => true,
        self::REASON_FEATURE_BUNDLES_INVALID => true,
        self::REASON_METADATA_INVALID => true,
        self::REASON_PATH_LIKE_VALUE_FORBIDDEN => true,
    ];

    private function __construct(
        string $presetName,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            ModuleErrorCodes::CORETSIA_MODE_PRESET_INVALID,
            $reason,
            [
                'preset' => $presetName,
            ],
            $previous,
        );
    }

    public static function forPreset(
        string $presetName,
        string $reason = self::REASON_PRESET_INVALID,
        ?\Throwable $previous = null,
    ): self {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('mode-preset-invalid-reason-invalid');
        }
        return new self($presetName, $reason, $previous);
    }
}
