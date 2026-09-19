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

namespace Coretsia\Foundation\Tag\Internal;

/**
 * Canonical Foundation tag-name validation policy.
 *
 * Shared by imperative TagRegistry registration and the declarative container
 * definition model.
 *
 * @internal
 */
final class TagNamePolicy
{
    private const int MAX_TAG_BYTES = 256;

    private const string TAG_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/';

    private function __construct()
    {
    }

    public static function isValid(string $tag): bool
    {
        return \strlen($tag) <= self::MAX_TAG_BYTES
            && \preg_match(self::TAG_PATTERN, $tag) === 1;
    }
}
