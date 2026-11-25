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
use Coretsia\Foundation\Serialization\StablePhpArrayDumper as FoundationStablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;

/**
 * Deterministic PHP-array artifact serialization encoder.
 *
 * This dumper emits one canonical data serialization using PHP-array syntax:
 *
 *     <?php
 *
 *     return [
 *         ...
 *     ];
 *
 * The emitted artifact bytes are decoded by StablePhpArrayParser and MUST be
 * treated as serialized data rather than executable PHP source. The canonical
 * encoding is intentionally narrow:
 *
 * - no generated comments;
 * - no timestamps;
 * - no tool versions;
 * - no absolute paths;
 * - no hostnames;
 * - no user names;
 * - no process-specific data;
 * - LF-only output;
 * - exactly one final newline.
 *
 * Input normalization is delegated to PayloadNormalizer, which in turn delegates
 * baseline json-like normalization to Foundation. Therefore map ordering, list
 * ordering, scalar preservation, and rejected-type semantics remain aligned
 * with the canonical stable JSON rules.
 *
 * Canonical byte emission is delegated to Foundation's StablePhpArrayDumper
 * without repeating payload normalization.
 *
 * StablePhpArrayDumper and StablePhpArrayParser are the paired encoder/decoder
 * for this serialization format. The dumper does not validate artifact envelope
 * semantics. It emits the array it receives after normalization and MUST NOT
 * wrap the envelope in another root key. Envelope construction belongs to
 * ArtifactEnvelopeFactory, and envelope validation belongs to
 * ArtifactSchemaValidator.
 *
 * @internal
 */
final class StablePhpArrayDumper
{
    public function __construct(
        private readonly PayloadNormalizer $payloadNormalizer = new PayloadNormalizer(),
    ) {
    }

    /**
     * Encodes the normalized array into canonical PHP-array artifact bytes.
     *
     * @param array<int|string, mixed> $array
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function dump(array $array): string
    {
        return $this->dumpArray($array);
    }

    /**
     * Encodes the normalized canonical envelope into PHP-array artifact bytes.
     *
     * This method intentionally does not wrap the envelope in another root key.
     * Decoding the emitted bytes yields the normalized envelope array unchanged.
     *
     * @param array<int|string, mixed> $envelope
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function dumpEnvelope(array $envelope): string
    {
        return $this->dumpArray($envelope);
    }

    /**
     * Static convenience wrapper for deterministic PHP array dumping.
     *
     * @param array<int|string, mixed> $array
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public static function dumpStable(array $array): string
    {
        return new self()->dumpArray($array);
    }

    /**
     * Static convenience wrapper for deterministic PHP envelope dumping.
     *
     * @param array<int|string, mixed> $envelope
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public static function dumpStableEnvelope(array $envelope): string
    {
        return new self()->dumpEnvelope($envelope);
    }

    /**
     * @param array<int|string, mixed> $array
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private function dumpArray(array $array): string
    {
        $normalized = $this->payloadNormalizer->normalize($array, 'artifact');

        if (!\is_array($normalized)) {
            throw ArtifactPayloadInvalidException::atPath(
                'artifact',
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        try {
            return new FoundationStablePhpArrayDumper()->dumpNormalized($normalized);
        } catch (PhpArraySerializationException $exception) {
            throw ArtifactPayloadInvalidException::atPath(
                'artifact',
                match ($exception->reason()) {
                    PhpArraySerializationException::REASON_MAP_KEY_MUST_BE_STRING => ArtifactPayloadInvalidException::REASON_MAP_KEY_MUST_BE_STRING,
                    default => ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
                },
                $exception,
            );
        }
    }
}
