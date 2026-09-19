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

namespace Coretsia\Kernel\Module\Warning;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;

/**
 * Non-fatal warning for an optional preset module that is not installed.
 *
 * This warning is emitted when a mode preset lists an optional module and the
 * installed Composer module manifest does not contain that module.
 *
 * The warning is intentionally safe and deterministic. It contains only the
 * stable warning code, canonical module id, selected preset name, and stable
 * reason token.
 *
 * It must not contain filesystem paths, raw preset payloads, raw Composer
 * payloads, secrets, PII, filesystem layout, stack traces, or environment-
 * specific values.
 */
final readonly class ModuleOptionalMissingWarning
{
    public const string REASON_PRESET_OPTIONAL_MODULE_MISSING = 'preset-optional-module-missing';

    private const int MAX_PRESET_BYTES = 64;

    private const string SAFE_PRESET_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789-';
    private const string SAFE_REASON_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789-';

    private string $code;
    private string $moduleId;
    private string $preset;
    private string $reason;

    private function __construct(
        ModuleId $moduleId,
        string $preset,
        string $reason,
    ) {
        if (!self::isSafePresetName($preset)) {
            throw new \InvalidArgumentException('module-optional-missing-warning-preset-invalid');
        }

        if (!self::isSafeReasonToken($reason)) {
            throw new \InvalidArgumentException('module-optional-missing-warning-reason-invalid');
        }

        $this->code = ModuleErrorCodes::CORETSIA_MODULE_OPTIONAL_MISSING;
        $this->moduleId = $moduleId->value();
        $this->preset = $preset;
        $this->reason = $reason;
    }

    public static function forPresetOptionalModule(
        ModuleId $moduleId,
        string $preset,
    ): self {
        return new self(
            $moduleId,
            $preset,
            self::REASON_PRESET_OPTIONAL_MODULE_MISSING,
        );
    }

    public function code(): string
    {
        return $this->code;
    }

    public function moduleId(): string
    {
        return $this->moduleId;
    }

    public function preset(): string
    {
        return $this->preset;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function canonicalKey(): string
    {
        return $this->code
            . "\0" . $this->preset
            . "\0" . $this->moduleId
            . "\0" . $this->reason;
    }

    /**
     * @return array{
     *     code: string,
     *     moduleId: string,
     *     preset: string,
     *     reason: string
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'moduleId' => $this->moduleId,
            'preset' => $this->preset,
            'reason' => $this->reason,
        ];
    }

    private static function isSafePresetName(string $preset): bool
    {
        return self::isNonEmptySafeString(
            value: $preset,
            allowedCharacters: self::SAFE_PRESET_CHARS,
            maxBytes: self::MAX_PRESET_BYTES,
        );
    }

    private static function isSafeReasonToken(string $reason): bool
    {
        return self::isNonEmptySafeString(
            value: $reason,
            allowedCharacters: self::SAFE_REASON_CHARS,
            maxBytes: 128,
        );
    }

    private static function isNonEmptySafeString(
        string $value,
        string $allowedCharacters,
        int $maxBytes,
    ): bool {
        if ($value === '') {
            return false;
        }

        if (\strlen($value) > $maxBytes) {
            return false;
        }

        if (\str_contains($value, '..')) {
            return false;
        }

        return \strspn($value, $allowedCharacters) === \strlen($value);
    }
}
