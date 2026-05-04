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
 * Contracts-level application-facing mailer port.
 *
 * This interface is the stable boundary application code depends on when it
 * needs to send mail. Implementations own concrete orchestration, transport
 * selection, retry policy, queue adaptation, provider failure mapping,
 * credentials resolution, and safe observability behavior.
 *
 * The contract intentionally exposes only a single send operation accepting the
 * canonical MailMessage transport model. It does not introduce queue(), later(),
 * sendAsync(), transport discovery, config access, credentials access, provider
 * clients, provider responses, or runtime wiring handles.
 *
 * Runtime implementations must preserve the mail redaction boundary: raw
 * recipients, subject, body, headers, credentials, provider payloads, provider
 * responses, and connection data MUST NOT be leaked through exceptions, logs,
 * metrics, spans, health output, CLI output, or debug output.
 */
interface MailerInterface
{
    /**
     * Sends or schedules the supplied mail message according to runtime-owned
     * mailer policy.
     *
     * The interface does not prescribe whether an implementation sends
     * synchronously, delegates to a transport, hands the message to a queue, or
     * applies an implementation-owned retry mechanism. Those behaviors are
     * runtime-owned and MUST NOT change this contracts surface.
     *
     * Implementations MUST NOT expose transport-specific response objects,
     * provider payloads, message ids, queue ids, HTTP responses, SMTP responses,
     * raw recipients, raw subject, raw body, raw headers, credentials, provider
     * diagnostics, or connection data through return values, exception messages,
     * logs, metrics, spans, health output, CLI output, or debug output.
     *
     * @throws MailException When a mail failure is reported through the
     *                       contracts-level safe exception boundary.
     */
    public function send(MailMessage $message): void;
}
