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

namespace Coretsia\Kernel\Artifacts\Fingerprint;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;

/**
 * Builds the deterministic safe fingerprint bucket for one compiled runtime
 * container graph.
 *
 * DefinitionGraph::toArray() is the canonical graph representation. This
 * builder hashes only those canonical json-like bytes and exposes safe summary
 * counts. It does not include provider planning metadata, filesystem paths, or
 * runtime instances.
 *
 * @internal
 */
final readonly class ContainerGraphFingerprintBucketBuilder
{
    public const int SCHEMA_VERSION = 1;

    private const int MAX_SAFE_COUNT = 1_000_000_000;

    /**
     * @return array{
     *     schemaVersion: int,
     *     sha256: string,
     *     serviceCount: int,
     *     aliasCount: int,
     *     parameterCount: int,
     *     tagCount: int
     * }
     */
    public function build(DefinitionGraph $graph): array
    {
        $payload = $graph->toArray();
        $json = StableJsonEncoder::encodeStable($payload);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'sha256' => \hash('sha256', $json),
            'serviceCount' => self::safeCount($payload['services'] ?? null),
            'aliasCount' => self::safeCount($payload['aliases'] ?? null),
            'parameterCount' => self::safeCount($payload['parameters'] ?? null),
            'tagCount' => self::safeCount($payload['tags'] ?? null),
        ];
    }

    private static function safeCount(mixed $value): int
    {
        if (!\is_array($value)) {
            return 0;
        }

        return \min(\count($value), self::MAX_SAFE_COUNT);
    }
}
