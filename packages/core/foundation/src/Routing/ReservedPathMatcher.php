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

namespace Coretsia\Foundation\Routing;

/**
 * Matches concrete encoded URI paths against canonical system-route declarations.
 *
 * Matching identifies reserved ownership only, not endpoint activation or access.
 */
final class ReservedPathMatcher
{
    private function __construct()
    {
    }

    /**
     * @return array{
     *     owner: string,
     *     pattern: string,
     *     status: 'active'|'reserved'|'dev_only',
     *     type: 'exact'|'prefix'
     * }|null
     */
    public static function match(string $path): ?array
    {
        $normalized = self::normalize($path);

        foreach (SystemRouteTable::entries() as $entry) {
            if (
                ($entry['type'] === 'exact' && $normalized === $entry['pattern'])
                || ($entry['type'] === 'prefix' && str_starts_with($normalized, $entry['pattern']))
            ) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Normalizes and validates a concrete encoded URI path.
     *
     * Encoded octets are never decoded or rewritten.
     *
     * @throws \InvalidArgumentException
     */
    public static function normalize(string $path): string
    {
        if ($path === '*' || preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*+:/', $path) !== 0) {
            throw new \InvalidArgumentException('http-reserved-path-invalid');
        }

        $length = strlen($path);
        $offset = 0;

        // Every byte is either a literal URI path character or part of a valid
        // percent-encoded octet. Encoded octets are never decoded or rewritten.
        while ($offset < $length) {
            $offset += strspn(
                $path,
                'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~!$&\'()*+,;=:@/',
                $offset,
            );

            if ($offset === $length) {
                break;
            }

            if (
                $path[$offset] !== '%'
                || $length - $offset < 3
                || strspn($path, '0123456789ABCDEFabcdef', $offset + 1, 2) !== 2
            ) {
                throw new \InvalidArgumentException('http-reserved-path-invalid');
            }

            $offset += 3;
        }

        $normalized = preg_replace('~/++~', '/', '/' . $path);

        if (
            $normalized === null
            || str_contains($normalized . '/', '/./')
            || str_contains($normalized . '/', '/../')
        ) {
            throw new \InvalidArgumentException('http-reserved-path-invalid');
        }

        return $normalized;
    }
}
