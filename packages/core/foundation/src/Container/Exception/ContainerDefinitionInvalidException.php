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

namespace Coretsia\Foundation\Container\Exception;

/**
 * Deterministic rejection of an invalid declarative container definition.
 *
 * The public message and reason are bounded safe tokens. They MUST NOT contain
 * service ids, class names, method names, raw arguments, config values,
 * filesystem paths, source snippets, environment values, or previous throwable
 * messages.
 */
final class ContainerDefinitionInvalidException extends \InvalidArgumentException
{
    public const string ERROR_CODE = 'CORETSIA_CONTAINER_DEFINITION_INVALID';

    public const string MESSAGE_TOKEN = 'container-definition-invalid';

    public const string REASON_DEFINITION_INVALID = 'definition-invalid';
    public const string REASON_REFERENCE_INVALID = 'reference-invalid';
    public const string REASON_PROVIDER_INVALID = 'provider-invalid';
    public const string REASON_REQUIRED_SERVICE_INVALID = 'required-service-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_DEFINITION_INVALID => true,
        self::REASON_REFERENCE_INVALID => true,
        self::REASON_PROVIDER_INVALID => true,
        self::REASON_REQUIRED_SERVICE_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('container-definition-invalid-reason-invalid');
        }

        parent::__construct(
            self::ERROR_CODE . ': ' . self::MESSAGE_TOKEN,
            0,
            $previous,
        );
    }

    public static function withReason(
        string $reason = self::REASON_DEFINITION_INVALID,
        ?\Throwable $previous = null,
    ): self {
        return new self($reason, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function messageToken(): string
    {
        return self::MESSAGE_TOKEN;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
