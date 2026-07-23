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

namespace Coretsia\Kernel\Artifacts\Generation;

/**
 * Immutable artifact generation identifier.
 *
 * A generation id is exactly the lowercase 64-character SHA-256 artifact
 * fingerprint shared by every artifact in the generation.
 *
 * @internal Kernel immutable artifact generation boundary.
 */
final readonly class ArtifactGenerationId
{
    private const string PATTERN = '/\A[a-f0-9]{64}\z/';

    private string $value;

    public function __construct(string $value)
    {
        if (\preg_match(self::PATTERN, $value) !== 1) {
            throw new \InvalidArgumentException('artifact-generation-id-invalid');
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
