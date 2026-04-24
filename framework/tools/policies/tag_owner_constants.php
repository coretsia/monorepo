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

return [
    'cli.command' => [
        'owner_package_id' => 'platform/cli',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/cli/src/Provider/Tags.php',
        'constant_name' => 'CLI_COMMAND',
    ],
    'error.mapper' => [
        'owner_package_id' => 'platform/errors',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/errors/src/Provider/Tags.php',
        'constant_name' => 'ERROR_MAPPER',
    ],
    'health.check' => [
        'owner_package_id' => 'platform/health',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/health/src/Provider/Tags.php',
        'constant_name' => 'HEALTH_CHECK',
    ],
    'http.middleware.app' => [
        'owner_package_id' => 'platform/http',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/http/src/Provider/Tags.php',
        'constant_name' => 'HTTP_MIDDLEWARE_APP',
    ],
    'http.middleware.app_post' => [
        'owner_package_id' => 'platform/http',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/http/src/Provider/Tags.php',
        'constant_name' => 'HTTP_MIDDLEWARE_APP_POST',
    ],
    'http.middleware.app_pre' => [
        'owner_package_id' => 'platform/http',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/http/src/Provider/Tags.php',
        'constant_name' => 'HTTP_MIDDLEWARE_APP_PRE',
    ],
    'http.middleware.route' => [
        'owner_package_id' => 'platform/http',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/http/src/Provider/Tags.php',
        'constant_name' => 'HTTP_MIDDLEWARE_ROUTE',
    ],
    'http.middleware.route_post' => [
        'owner_package_id' => 'platform/http',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/http/src/Provider/Tags.php',
        'constant_name' => 'HTTP_MIDDLEWARE_ROUTE_POST',
    ],
    'http.middleware.route_pre' => [
        'owner_package_id' => 'platform/http',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/http/src/Provider/Tags.php',
        'constant_name' => 'HTTP_MIDDLEWARE_ROUTE_PRE',
    ],
    'http.middleware.system' => [
        'owner_package_id' => 'platform/http',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/http/src/Provider/Tags.php',
        'constant_name' => 'HTTP_MIDDLEWARE_SYSTEM',
    ],
    'http.middleware.system_post' => [
        'owner_package_id' => 'platform/http',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/http/src/Provider/Tags.php',
        'constant_name' => 'HTTP_MIDDLEWARE_SYSTEM_POST',
    ],
    'http.middleware.system_pre' => [
        'owner_package_id' => 'platform/http',
        'constant_required' => false,
        'constant_path' => 'framework/packages/platform/http/src/Provider/Tags.php',
        'constant_name' => 'HTTP_MIDDLEWARE_SYSTEM_PRE',
    ],
    'kernel.hook.after_uow' => [
        'owner_package_id' => 'core/kernel',
        'constant_required' => false,
        'constant_path' => 'framework/packages/core/kernel/src/Provider/Tags.php',
        'constant_name' => 'KERNEL_HOOK_AFTER_UOW',
    ],
    'kernel.hook.before_uow' => [
        'owner_package_id' => 'core/kernel',
        'constant_required' => false,
        'constant_path' => 'framework/packages/core/kernel/src/Provider/Tags.php',
        'constant_name' => 'KERNEL_HOOK_BEFORE_UOW',
    ],
    'kernel.reset' => [
        'owner_package_id' => 'core/foundation',
        'constant_required' => false,
        'constant_path' => 'framework/packages/core/foundation/src/Provider/Tags.php',
        'constant_name' => 'KERNEL_RESET',
    ],
    'kernel.stateful' => [
        'owner_package_id' => 'core/foundation',
        'constant_required' => false,
        'constant_path' => 'framework/packages/core/foundation/src/Provider/Tags.php',
        'constant_name' => 'KERNEL_STATEFUL',
    ],
];
