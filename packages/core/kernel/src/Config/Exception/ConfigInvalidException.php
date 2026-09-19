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

namespace Coretsia\Kernel\Config\Exception;

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValidationViolation;

/**
 * Deterministic Kernel config validation/config loading failure.
 *
 * This exception is used by ConfigKernel Phase B, config rules loading,
 * declarative config validation, environment overlay coercion, and final
 * merged config validation.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_CONFIG_INVALID: reason-token
 *
 * Validation details, when available, are exposed through ConfigValidationResult
 * and ConfigValidationViolation instances. Those diagnostics are expected to
 * contain only safe structural metadata: config root, dot path, reason token,
 * expected type, and actual type.
 *
 * Messages and diagnostics MUST NOT include raw config values, raw environment
 * values, dotenv values, secrets, tokens, DSNs, cookies, headers, raw SQL,
 * object dumps, PHP warnings, absolute local paths, stack traces, or previous
 * throwable messages.
 *
 * @internal
 */
final class ConfigInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONFIG_INVALID';

    public const string REASON_INVALID = 'config-invalid';
    public const string REASON_FILE_RETURN_TYPE_INVALID = 'config-file-return-type-invalid';
    public const string REASON_RULES_FILE_RETURN_TYPE_INVALID = 'config-rules-file-return-type-invalid';
    public const string REASON_RULESET_INVALID = 'config-ruleset-invalid';
    public const string REASON_VALIDATION_FAILED = 'config-validation-failed';
    public const string REASON_ENV_OVERLAY_INVALID = 'config-env-overlay-invalid';
    public const string REASON_ENV_VALUE_INVALID = 'config-env-value-invalid';
    public const string REASON_SOURCE_INVALID = 'config-source-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_FILE_RETURN_TYPE_INVALID => true,
        self::REASON_RULES_FILE_RETURN_TYPE_INVALID => true,
        self::REASON_RULESET_INVALID => true,
        self::REASON_VALIDATION_FAILED => true,
        self::REASON_ENV_OVERLAY_INVALID => true,
        self::REASON_ENV_VALUE_INVALID => true,
        self::REASON_SOURCE_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
        private readonly ?ConfigValidationResult $validationResult = null,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('config-invalid-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('config-invalid-reason-invalid');
        }

        if ($validationResult !== null && $validationResult->isSuccess()) {
            throw new \InvalidArgumentException('config-invalid-validation-result-success');
        }

        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function withReason(
        string $reason = self::REASON_INVALID,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: $reason,
            validationResult: null,
            previous: $previous,
        );
    }

    public static function fromValidationResult(
        ConfigValidationResult $validationResult,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            reason: self::REASON_VALIDATION_FAILED,
            validationResult: $validationResult,
            previous: $previous,
        );
    }

    public static function forViolation(
        ConfigValidationViolation $violation,
        ?\Throwable $previous = null,
    ): self {
        return self::fromValidationResult(
            ConfigValidationResult::failure([$violation]),
            $previous,
        );
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function validationResult(): ?ConfigValidationResult
    {
        return $this->validationResult;
    }

    /**
     * @return list<ConfigValidationViolation>
     */
    public function violations(): array
    {
        if ($this->validationResult === null) {
            return [];
        }

        return $this->validationResult->violations();
    }

    private static function message(string $reason): string
    {
        return self::ERROR_CODE . ': ' . $reason;
    }
}
