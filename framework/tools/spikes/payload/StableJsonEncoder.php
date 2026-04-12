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

namespace Coretsia\Tools\Spikes\payload;

use Coretsia\Devtools\InternalToolkit\Json;
use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

/**
 * Thin stable JSON encoder wrapper for the payload spike (Epic 0.70.0).
 *
 * MUST:
 * - delegate to \Coretsia\Devtools\InternalToolkit\Json::encodeStable(...)
 * - keep spike code free from ad-hoc json_encode flags / ordering rules
 * - surface deterministic failures as DeterministicException
 *
 * Notes:
 * - InternalToolkit\Json::encodeStable already cements:
 *   - deterministic normalization (maps sorted by strcmp, lists preserved)
 *   - json-like scalar policy (floats forbidden; NaN/INF/-INF forbidden)
 *   - deterministic exception codes/messages
 */
final class StableJsonEncoder
{
    private function __construct()
    {
    }

    /**
     * @throws DeterministicException
     */
    public static function encode(array $value): string
    {
        try {
            $encoded = Json::encodeStable($value);
        } catch (\Throwable $e) {
            self::failEncode($e);
        }

        if (!\is_string($encoded) || $encoded === '') {
            self::failEncode();
        }

        return $encoded;
    }

    /**
     * @throws DeterministicException
     */
    private static function failEncode(?\Throwable $previous = null): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_PAYLOAD_JSON_ENCODE_FAILED,
            ErrorCodes::CORETSIA_PAYLOAD_JSON_ENCODE_FAILED,
            $previous,
        );
    }
}
