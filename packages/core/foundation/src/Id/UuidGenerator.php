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

namespace Coretsia\Foundation\Id;

use Coretsia\Foundation\Id\Exception\IdGenerationFailedException;

/**
 * Canonical Foundation UUID generator.
 *
 * Generates lowercase textual UUID v4 values.
 *
 * This generator is available as an optional generic runtime id generator via
 * IdGeneratorInterface selection. It MUST NOT affect correlation id generation.
 */
final class UuidGenerator implements IdGeneratorInterface
{
    public function generate(): string
    {
        try {
            $bytes = \random_bytes(16);
        } catch (\Throwable $exception) {
            throw IdGenerationFailedException::entropyUnavailable($exception);
        }

        /*
         * UUID version 4:
         * - high nibble of byte 6 is 0100
         * - high two bits of byte 8 are 10
         */
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        $hex = \bin2hex($bytes);

        return \substr($hex, 0, 8)
            . '-'
            . \substr($hex, 8, 4)
            . '-'
            . \substr($hex, 12, 4)
            . '-'
            . \substr($hex, 16, 4)
            . '-'
            . \substr($hex, 20, 12);
    }
}
