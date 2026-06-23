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

namespace Coretsia\Foundation\Tag;

/**
 * Canonical framework-reserved DI tag identifier registry.
 *
 * This class owns reserved DI tag identifier strings only.
 *
 * Importing a tag constant does not grant ownership of that tag's runtime
 * semantics. Semantics, metadata shape, discovery, dispatch, ordering, and
 * validation remain owned by the semantic owner package declared in
 * docs/ssot/tags.md.
 *
 * Packages may reference a reserved tag only when they are either:
 *
 * - the semantic owner of that tag; or
 * - contributing to a documented tag-based extension point owned by another
 *   package.
 */
final class ReservedTags
{
    public const string CLI_COMMAND = 'cli.command';

    public const string ERROR_MAPPER = 'error.mapper';

    public const string HEALTH_CHECK = 'health.check';

    public const string HTTP_MIDDLEWARE_APP = 'http.middleware.app';
    public const string HTTP_MIDDLEWARE_APP_POST = 'http.middleware.app_post';
    public const string HTTP_MIDDLEWARE_APP_PRE = 'http.middleware.app_pre';
    public const string HTTP_MIDDLEWARE_ROUTE = 'http.middleware.route';
    public const string HTTP_MIDDLEWARE_ROUTE_POST = 'http.middleware.route_post';
    public const string HTTP_MIDDLEWARE_ROUTE_PRE = 'http.middleware.route_pre';
    public const string HTTP_MIDDLEWARE_SYSTEM = 'http.middleware.system';
    public const string HTTP_MIDDLEWARE_SYSTEM_POST = 'http.middleware.system_post';
    public const string HTTP_MIDDLEWARE_SYSTEM_PRE = 'http.middleware.system_pre';

    public const string KERNEL_HOOK_AFTER_UOW = 'kernel.hook.after_uow';
    public const string KERNEL_HOOK_BEFORE_UOW = 'kernel.hook.before_uow';
    public const string KERNEL_RESET = 'kernel.reset';
    public const string KERNEL_STATEFUL = 'kernel.stateful';

    /**
     * @var list<non-empty-string>
     */
    private const array ALL = [
        self::CLI_COMMAND,
        self::ERROR_MAPPER,
        self::HEALTH_CHECK,
        self::HTTP_MIDDLEWARE_APP,
        self::HTTP_MIDDLEWARE_APP_POST,
        self::HTTP_MIDDLEWARE_APP_PRE,
        self::HTTP_MIDDLEWARE_ROUTE,
        self::HTTP_MIDDLEWARE_ROUTE_POST,
        self::HTTP_MIDDLEWARE_ROUTE_PRE,
        self::HTTP_MIDDLEWARE_SYSTEM,
        self::HTTP_MIDDLEWARE_SYSTEM_POST,
        self::HTTP_MIDDLEWARE_SYSTEM_PRE,
        self::KERNEL_HOOK_AFTER_UOW,
        self::KERNEL_HOOK_BEFORE_UOW,
        self::KERNEL_RESET,
        self::KERNEL_STATEFUL,
    ];

    private function __construct()
    {
    }

    public static function isKnown(string $tag): bool
    {
        return \in_array($tag, self::ALL, true);
    }

    /**
     * Returns all framework-reserved DI tag identifiers.
     *
     * Ordering is stable and intentionally deterministic.
     *
     * @return list<non-empty-string>
     */
    public static function all(): array
    {
        return self::ALL;
    }
}
