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

namespace Coretsia\Kernel\Artifacts\Php;

use Coretsia\Foundation\Serialization\Exception\PhpArraySerializationException;
use Coretsia\Foundation\Serialization\StablePhpArrayParser as FoundationStablePhpArrayParser;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;

/**
 * Decoder for the canonical deterministic PHP-array artifact serialization.
 *
 * StablePhpArrayParser is the decoding counterpart of StablePhpArrayDumper.
 * Canonical byte decoding is delegated to Foundation's StablePhpArrayParser,
 * which treats PHP-looking artifact source strictly as serialized data.
 * Kernel retains ownership of artifact-specific exception translation.
 *
 * Accepted values are limited to the serialization domain emitted by the
 * dumper: null, bool, int, string, list, and string-keyed map values. Layout,
 * indentation, string escapes, integer spelling, map ordering, separators, and
 * the document prefix/suffix are all canonical and fail closed when they drift.
 *
 * Diagnostics are intentionally safe. Serialization failures expose only the
 * stable ArtifactInvalidException reason token and never include source bytes,
 * literals, offsets, paths, or parser state.
 *
 * @internal
 */
final class StablePhpArrayParser
{
    /**
     * @return array<int|string, mixed>
     *
     * @throws ArtifactInvalidException
     */
    public function parse(string $bytes): array
    {
        try {
            return new FoundationStablePhpArrayParser()->parse($bytes);
        } catch (PhpArraySerializationException $exception) {
            throw ArtifactInvalidException::withReason(
                ArtifactInvalidException::REASON_SERIALIZATION_INVALID,
                $exception,
            );
        }
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws ArtifactInvalidException
     */
    public static function parseStable(string $bytes): array
    {
        return new self()->parse($bytes);
    }
}
