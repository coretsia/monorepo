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

namespace Coretsia\Contracts\Security;

/**
 * Immutable contracts-level redacted summary value.
 *
 * The value contains only classification and explicitly selected disclosure
 * metadata. It never retains the original value, raw bytes, redaction context,
 * path, source metadata, or a previous Throwable.
 */
final readonly class RedactedValue
{
    public const int SCHEMA_VERSION = 1;

    private RedactionKind $kind;
    private RedactionMode $mode;
    private ?int $length;
    private ?string $hash;

    public function __construct(
        RedactionKind $kind,
        RedactionMode $mode,
        ?int $length,
        ?string $hash,
    ) {
        if (!self::isValidShape($mode, $length, $hash)) {
            throw new \InvalidArgumentException('redacted-value-shape-invalid');
        }

        $this->kind = $kind;
        $this->mode = $mode;
        $this->length = $length;
        $this->hash = $hash;
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function kind(): RedactionKind
    {
        return $this->kind;
    }

    public function mode(): RedactionMode
    {
        return $this->mode;
    }

    /**
     * @return int<0,max>|null
     */
    public function length(): ?int
    {
        return $this->length;
    }

    public function hash(): ?string
    {
        return $this->hash;
    }

    /**
     * @return array{
     *     hash: ?string,
     *     kind: string,
     *     length: ?int,
     *     mode: string,
     *     redacted: true,
     *     schemaVersion: 1,
     * }
     */
    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'kind' => $this->kind->value,
            'length' => $this->length,
            'mode' => $this->mode->value,
            'redacted' => true,
            'schemaVersion' => self::SCHEMA_VERSION,
        ];
    }

    private static function isValidShape(
        RedactionMode $mode,
        ?int $length,
        ?string $hash,
    ): bool {
        if ($length !== null && $length < 0) {
            return false;
        }

        if ($hash !== null && preg_match('/\Asha256:[a-f0-9]{64}\z/', $hash) !== 1) {
            return false;
        }

        return match ($mode) {
            RedactionMode::Placeholder => $length === null && $hash === null,
            RedactionMode::Length => $length !== null && $hash === null,
            RedactionMode::Hash => $length === null && $hash !== null,
            RedactionMode::HashAndLength => $length !== null && $hash !== null,
        };
    }
}
