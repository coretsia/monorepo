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

namespace Coretsia\Contracts\Mail;

/**
 * Deterministic contracts exception boundary for mail sending failures.
 *
 * The canonical mail delivery error code is the stable string code exposed by
 * MailException::CODE. It is intentionally separate from PHP's native integer
 * Throwable::getCode() value.
 *
 * This exception is intentionally payload-free and uses a fixed safe message.
 * It MUST NOT carry recipients, subject, body, headers, credentials, DSNs,
 * provider payloads, provider responses, request or response payloads, private
 * customer data, service instances, vendor objects, streams, resources,
 * closures, or filesystem handles.
 *
 * Runtime mail implementations that wrap provider failures must redact unsafe
 * provider data before creating or reporting this exception.
 *
 * Mapping mail failures to normalized errors is runtime-owned. This exception
 * does not construct, own, or require ErrorDescriptor.
 */
final class MailException extends \RuntimeException
{
    public const string CODE = 'CORETSIA_MAIL_DELIVERY_FAILED';
    public const string MESSAGE = 'Mail delivery failed.';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }

    /**
     * @return non-empty-string
     */
    public function errorCode(): string
    {
        return self::CODE;
    }
}
