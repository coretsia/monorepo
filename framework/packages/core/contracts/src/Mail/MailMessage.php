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
 * Immutable contracts transport model for a mail message.
 *
 * This model may carry raw delivery data because runtime transports need the
 * actual recipients, subject, body, headers, and reply-to values to send mail.
 * Raw accessors are for runtime delivery only and MUST NOT be logged, printed,
 * traced, exported, copied into error descriptors, or emitted as metric labels.
 *
 * The public exported shape returned by toArray() is intentionally redacted. It
 * exposes only safe counts, deterministic lengths, normalized safe headers, and
 * normalized safe metadata. It never exposes raw recipients, subject, or body.
 *
 * This class is not a DTO-marker class by default. DTO policy applies only when
 * a future owner explicitly opts a class into the canonical DTO marker.
 */
final readonly class MailMessage
{
    private const int SCHEMA_VERSION = 1;

    /**
     * @var non-empty-list<non-empty-string>
     */
    private array $to;

    /**
     * @var list<non-empty-string>
     */
    private array $cc;

    /**
     * @var list<non-empty-string>
     */
    private array $bcc;

    /**
     * @var list<non-empty-string>
     */
    private array $replyTo;

    /**
     * @var non-empty-string
     */
    private string $subject;

    /**
     * @var non-empty-string
     */
    private string $body;

    /**
     * @var array<string,mixed>
     */
    private array $headers;

    /**
     * @var array<string,mixed>
     */
    private array $metadata;

    /**
     * Creates a mail message from exact caller-supplied delivery data.
     *
     * Text input is validated exactly as supplied. The constructor does not
     * trim, lowercase, uppercase, normalize, parse, MIME-encode, sanitize,
     * render, or otherwise rewrite delivery data.
     *
     * Recipients are structurally validated as non-empty safe single-line
     * strings. Full email syntax validation, display-name parsing, IDN policy,
     * sender identity, HTML/text semantics, MIME generation, and attachment
     * handling are runtime-owned.
     *
     * Headers and metadata are safe deterministic json-like maps. They MUST NOT
     * contain floats, objects, resources, closures, service instances, vendor
     * objects, credentials, tokens, DSNs, raw recipients, raw subject, or raw
     * body.
     *
     * @param non-empty-list<non-empty-string> $to
     * @param non-empty-string $subject
     * @param non-empty-string $body
     * @param list<non-empty-string> $cc
     * @param list<non-empty-string> $bcc
     * @param list<non-empty-string> $replyTo
     * @param array<string,mixed> $headers
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        array $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
        array $headers = [],
        array $metadata = [],
    ) {
        $this->to = self::normalizeRecipientList($to, 'to', true);
        $this->cc = self::normalizeRecipientList($cc, 'cc', false);
        $this->bcc = self::normalizeRecipientList($bcc, 'bcc', false);
        $this->replyTo = self::normalizeRecipientList($replyTo, 'replyTo', false);

        self::assertSafeSingleLineString($subject, 'subject');
        self::assertSafeBodyString($body, 'body');

        $this->subject = $subject;
        $this->body = $body;
        $this->headers = self::normalizeJsonLikeMap($headers, 'headers');
        $this->metadata = self::normalizeJsonLikeMap($metadata, 'metadata');
    }

    /**
     * Returns the stable mail message schema version.
     */
    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * Returns primary recipients for runtime delivery only.
     *
     * The returned raw recipient values are sensitive and MUST NOT be used as
     * diagnostics, logs, metric labels, span attributes, error extensions, CLI
     * output, health output, or generated artifact data.
     *
     * @return non-empty-list<non-empty-string>
     */
    public function to(): array
    {
        return $this->to;
    }

    /**
     * Returns carbon-copy recipients for runtime delivery only.
     *
     * The returned raw recipient values are sensitive and MUST NOT be logged or
     * exported.
     *
     * @return list<non-empty-string>
     */
    public function cc(): array
    {
        return $this->cc;
    }

    /**
     * Returns blind-carbon-copy recipients for runtime delivery only.
     *
     * The returned raw recipient values are sensitive and MUST NOT be logged or
     * exported.
     *
     * @return list<non-empty-string>
     */
    public function bcc(): array
    {
        return $this->bcc;
    }

    /**
     * Returns reply-to recipients for runtime delivery only.
     *
     * The returned raw recipient values are sensitive and MUST NOT be logged or
     * exported.
     *
     * @return list<non-empty-string>
     */
    public function replyTo(): array
    {
        return $this->replyTo;
    }

    /**
     * Returns the raw mail subject for runtime delivery only.
     *
     * The returned value is sensitive and MUST NOT be logged, printed, traced,
     * exported, copied into error descriptors, or emitted as a metric label.
     *
     * @return non-empty-string
     */
    public function subject(): string
    {
        return $this->subject;
    }

    /**
     * Returns the raw mail body for runtime delivery only.
     *
     * The returned value is sensitive and MUST NOT be logged, printed, traced,
     * exported, copied into error descriptors, or emitted as a metric label.
     *
     * @return non-empty-string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Returns normalized safe header-like metadata.
     *
     * Headers are not raw transport headers. They are safe owner-provided
     * deterministic metadata only.
     *
     * @return array<string,mixed>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Returns normalized safe runtime metadata.
     *
     * Metadata must remain safe for deterministic contract-level use and must
     * not contain raw delivery data, credentials, provider payloads, objects,
     * resources, closures, or runtime wiring handles.
     *
     * @return array<string,mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Returns a deterministic redacted public shape.
     *
     * This exported shape is safe for diagnostics because it exposes counts,
     * byte lengths, safe normalized headers, and safe normalized metadata only.
     * It never exposes raw recipients, raw subject, or raw body.
     *
     * @return array{
     *     bccCount:int<0,max>,
     *     bodyLength:int<1,max>,
     *     ccCount:int<0,max>,
     *     headers:array<string,mixed>,
     *     metadata:array<string,mixed>,
     *     replyToCount:int<0,max>,
     *     schemaVersion:int,
     *     subjectLength:int<1,max>,
     *     toCount:int<1,max>
     * }
     */
    public function toArray(): array
    {
        return [
            'bccCount' => \count($this->bcc),
            'bodyLength' => \strlen($this->body),
            'ccCount' => \count($this->cc),
            'headers' => $this->headers,
            'metadata' => $this->metadata,
            'replyToCount' => \count($this->replyTo),
            'schemaVersion' => self::SCHEMA_VERSION,
            'subjectLength' => \strlen($this->subject),
            'toCount' => \count($this->to),
        ];
    }

    /**
     * @return ($required is true ? non-empty-list<non-empty-string> : list<non-empty-string>)
     */
    private static function normalizeRecipientList(array $recipients, string $field, bool $required): array
    {
        if (!\array_is_list($recipients)) {
            throw new \InvalidArgumentException(\sprintf('Mail message %s must be a list.', $field));
        }

        if ($required && $recipients === []) {
            throw new \InvalidArgumentException(
                \sprintf('Mail message %s must contain at least one recipient.', $field)
            );
        }

        foreach ($recipients as $index => $recipient) {
            if (!\is_string($recipient)) {
                throw new \InvalidArgumentException(\sprintf('Mail message %s[%d] must be a string.', $field, $index));
            }

            self::assertSafeSingleLineString($recipient, \sprintf('%s[%d]', $field, $index));
        }

        return $recipients;
    }

    /**
     * @return array<string,mixed>
     */
    private static function normalizeJsonLikeMap(array $value, string $field): array
    {
        if ($value !== [] && \array_is_list($value)) {
            throw new \InvalidArgumentException(\sprintf('Mail message %s must be a map.', $field));
        }

        /** @var array<string,mixed> $normalized */
        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException(\sprintf('Mail message %s keys must be strings.', $field));
            }

            self::assertSafeSingleLineString($key, \sprintf('%s key', $field));

            $normalized[$key] = self::normalizeJsonLikeValue($item, \sprintf('%s.%s', $field, $key));
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    private static function normalizeJsonLikeValue(mixed $value, string $field): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            self::assertSafeString($value, $field);

            return $value;
        }

        if (\is_float($value)) {
            throw new \InvalidArgumentException(\sprintf('Mail message %s must not contain floats.', $field));
        }

        if (!\is_array($value)) {
            throw new \InvalidArgumentException(\sprintf('Mail message %s must be json-like.', $field));
        }

        if (\array_is_list($value)) {
            $normalized = [];

            foreach ($value as $index => $item) {
                $normalized[] = self::normalizeJsonLikeValue($item, \sprintf('%s[%d]', $field, $index));
            }

            return $normalized;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException(\sprintf('Mail message %s map keys must be strings.', $field));
            }

            self::assertSafeSingleLineString($key, \sprintf('%s key', $field));

            $normalized[$key] = self::normalizeJsonLikeValue($item, \sprintf('%s.%s', $field, $key));
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    private static function assertSafeSingleLineString(string $value, string $field): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException(\sprintf('Mail message %s must be non-empty.', $field));
        }

        if ($value !== \trim($value)) {
            throw new \InvalidArgumentException(
                \sprintf('Mail message %s must not contain leading or trailing whitespace.', $field)
            );
        }

        if (\preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException(\sprintf('Mail message %s must be a safe single-line string.', $field));
        }
    }

    private static function assertSafeBodyString(string $value, string $field): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException(\sprintf('Mail message %s must be non-empty.', $field));
        }

        if (\preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException(\sprintf('Mail message %s must be a safe string.', $field));
        }
    }

    private static function assertSafeString(string $value, string $field): void
    {
        if (\preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException(\sprintf('Mail message %s must be a safe string.', $field));
        }
    }
}
