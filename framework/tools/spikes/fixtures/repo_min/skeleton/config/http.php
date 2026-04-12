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

$catalog = require __DIR__ . '/../../../http_middleware_catalog.php';
if (!\is_array($catalog)) {
    throw new \RuntimeException('http-middleware-catalog-invalid');
}

$slots = $catalog['slots'] ?? null;
if (!\is_array($slots)) {
    throw new \RuntimeException('http-middleware-catalog-slots-missing');
}

/**
 * Phase 0 spikes slot taxonomy (cemented).
 *
 * @var array<string, string> $slotMap
 */
$slotMap = [
    'system_pre' => 'http.middleware.system_pre',
    'system' => 'http.middleware.system',
    'system_post' => 'http.middleware.system_post',
    'app_pre' => 'http.middleware.app_pre',
    'app' => 'http.middleware.app',
    'app_post' => 'http.middleware.app_post',
    'route_pre' => 'http.middleware.route_pre',
    'route' => 'http.middleware.route',
    'route_post' => 'http.middleware.route_post',
];

$out = [
    'middleware' => [],
];

foreach ($slotMap as $shortKey => $catalogKey) {
    $items = $slots[$catalogKey] ?? null;
    if (!\is_array($items)) {
        throw new \RuntimeException('http-middleware-slot-missing');
    }

    /** @var list<string> $classes */
    $classes = [];
    foreach ($items as $row) {
        if (!\is_array($row)) {
            throw new \RuntimeException('http-middleware-slot-row-invalid');
        }

        $class = $row['class'] ?? null;
        if (!\is_string($class) || $class === '') {
            throw new \RuntimeException('http-middleware-slot-class-missing');
        }

        $classes[] = $class;
    }

    $out['middleware'][$shortKey] = $classes;
}

$optIn = $catalog['opt_in'] ?? null;
if (\is_array($optIn)) {
    $out['opt_in'] = $optIn;
}

return $out;
