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
 * Contracts-level swappable mail transport port.
 *
 * Implementations own concrete delivery behavior, provider adaptation, retry
 * behavior, backend failure policy, and provider response handling. The
 * contracts surface intentionally exposes only a safe transport name and a
 * send operation that accepts the canonical MailMessage transport model.
 *
 * This interface MUST NOT expose SMTP clients, HTTP clients, queue clients,
 * vendor clients, provider request or response objects, credentials, DSNs,
 * service containers, config repositories, logger objects, tracer objects,
 * metrics objects, streams, resources, closures, iterators, generators, or
 * runtime wiring handles.
 *
 * Runtime implementations must preserve the mail redaction boundary: raw
 * recipients, subject, body, headers, credentials, provider payloads, provider
 * responses, and connection data MUST NOT be leaked through exceptions, logs,
 * metrics, spans, health output, CLI output, or debug output.
 */
interface MailTransportInterface
{
    /**
     * Returns the stable safe transport implementation name.
     *
     * The returned name is a bounded diagnostics value, for example `smtp`,
     * `api`, `memory`, `null`, `queue`, or `external`. It MUST NOT contain
     * recipients, subject, body, tenant ids, user ids, request ids, correlation
     * ids, hostnames, DSNs, connection strings, credentials, tokens, usernames,
     * passwords, API keys, provider message ids, environment-specific
     * identifiers, or backend object metadata.
     *
     * This name does not imply transport discovery semantics, DI tag semantics,
     * config roots, config keys, backend selection policy, or runtime wiring
     * behavior.
     *
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * Sends the supplied mail message according to implementation-owned
     * transport semantics.
     *
     * Implementations may perform synchronous delivery, delegate to an API,
     * use SMTP, use an in-memory/null/test backend, or adapt to a queue-backed
     * runtime owner policy. Those behaviors are implementation-owned and MUST
     * NOT change this contracts surface.
     *
     * Implementations MUST NOT expose provider-specific objects, transport
     * response payloads, raw recipients, raw subject, raw body, raw headers,
     * credentials, provider payloads, provider responses, or connection data
     * through return values, exception messages, logs, metrics, spans, health
     * output, CLI output, or debug output.
     *
     * @throws MailException When a mail transport failure is reported through
     *                       the contracts-level safe exception boundary.
     */
    public function send(MailMessage $message): void;
}
