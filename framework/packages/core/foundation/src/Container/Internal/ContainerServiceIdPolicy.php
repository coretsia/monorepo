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

namespace Coretsia\Foundation\Container\Internal;

use Coretsia\Foundation\Container\Exception\ContainerException;

/**
 * Canonical Foundation service-id validation policy.
 *
 * This policy is shared by the imperative source container and the declarative
 * definition model so both paths accept and reject exactly the same service
 * identifiers.
 *
 * @internal
 */
final class ContainerServiceIdPolicy
{
    private const int MAX_ID_BYTES = 256;

    private const string FORBIDDEN_CHARACTER_PATTERN = '/\s/u';
    private const string INTEGER_LIKE_PATTERN = '/\A[+-]?[0-9]+\z/D';

    private function __construct()
    {
    }

    public static function assertValid(string $id): void
    {
        if ($id === '') {
            throw new ContainerException('container-service-id-empty');
        }

        if (self::hasForbiddenCharactersOrInvalidUtf8($id)) {
            throw new ContainerException('container-service-id-whitespace-forbidden');
        }

        if (\strlen($id) > self::MAX_ID_BYTES || self::isIntegerLike($id)) {
            throw new ContainerException('container-service-id-invalid');
        }
    }

    public static function isValid(string $id): bool
    {
        return $id !== ''
            && \strlen($id) <= self::MAX_ID_BYTES
            && !self::hasForbiddenCharactersOrInvalidUtf8($id)
            && !self::isIntegerLike($id);
    }

    public static function hasForbiddenCharactersOrInvalidUtf8(
        string $id,
    ): bool {
        /*
         * Only exact 0 is accepted:
         *
         * - 0 means valid UTF-8 without forbidden characters;
         * - 1 means whitespace exists;
         * - false means invalid UTF-8 or regex failure.
         */
        return \preg_match(
            self::FORBIDDEN_CHARACTER_PATTERN,
            $id,
        ) !== 0;
    }

    private static function isIntegerLike(string $id): bool
    {
        /*
         * Integer-like strings are forbidden because PHP may coerce them to
         * integer array keys. Rejecting all decimal forms also avoids
         * architecture-dependent behavior for values outside native int range.
         */
        return \preg_match(
            self::INTEGER_LIKE_PATTERN,
            $id,
        ) === 1;
    }
}
