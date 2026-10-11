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

namespace Coretsia\Platform\Redaction\Redaction;

use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;

/**
 * Stateless, domain-separated deterministic SHA-256 summary hasher.
 *
 * Hashes are correlation metadata, not encryption or a guarantee that
 * low-entropy source material cannot be reconstructed.
 *
 * @internal
 */
final class StableRedactionHasher
{
    public function hashString(
        string $bytes,
        RedactionKind $kind,
        RedactionContext $context,
    ): string {
        return self::hash($bytes, $kind, $context, 'string');
    }

    public function hashJsonLike(
        string $bytes,
        RedactionKind $kind,
        RedactionContext $context,
    ): string {
        return self::hash($bytes, $kind, $context, 'json-like');
    }

    private static function hash(
        string $bytes,
        RedactionKind $kind,
        RedactionContext $context,
        string $representation,
    ): string {
        return 'sha256:' . \hash(
            'sha256',
            "coretsia.redaction@1\0" . $context->scope() . "\0" . $kind->value . "\0" . $representation . "\0" . $bytes,
        );
    }
}
