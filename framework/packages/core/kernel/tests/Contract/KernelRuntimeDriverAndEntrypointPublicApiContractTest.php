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

namespace Coretsia\Kernel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class KernelRuntimeDriverAndEntrypointPublicApiContractTest extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private const array RUNTIME_DRIVER_AND_ENTRYPOINT_PUBLIC_SYMBOLS = [
        'Coretsia\\Kernel\\Runtime\\Driver\\BackgroundDriver',
        'Coretsia\\Kernel\\Runtime\\Driver\\HttpDriver',
        'Coretsia\\Kernel\\Runtime\\Driver\\RuntimeDrivers',
        'Coretsia\\Kernel\\Runtime\\Entrypoint\\RuntimeEntrypointGuard',
        'Coretsia\\Kernel\\Runtime\\Exception\\RuntimeDriverConflictException',
        'Coretsia\\Kernel\\Runtime\\Exception\\RuntimeDriverInvalidConfigException',
    ];

    public function testRuntimeDriverAndEntrypointPublicApiSymbolsAreListedInPublicApiManifest(): void
    {
        $publicSymbols = self::publicApiSymbols();

        foreach (self::RUNTIME_DRIVER_AND_ENTRYPOINT_PUBLIC_SYMBOLS as $symbol) {
            self::assertContains(
                $symbol,
                $publicSymbols,
                \sprintf(
                    'Runtime driver/entrypoint public API symbol "%s" must be listed in PUBLIC_API.md.',
                    $symbol,
                ),
            );
        }
    }

    /**
     * @return list<non-empty-string>
     */
    private static function publicApiSymbols(): array
    {
        $contents = \file_get_contents(self::publicApiPath());

        self::assertIsString($contents);

        \preg_match_all('/^- `([^`]+)`$/m', $contents, $matches);

        $symbols = [];

        foreach ($matches[1] ?? [] as $symbol) {
            if (!\is_string($symbol) || $symbol === '') {
                continue;
            }

            $symbols[] = $symbol;
        }

        \usort(
            $symbols,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        self::assertNotSame([], $symbols, 'PUBLIC_API.md must list public symbols.');

        /** @var list<non-empty-string> $symbols */
        return $symbols;
    }

    private static function publicApiPath(): string
    {
        return \dirname(__DIR__, 2) . '/PUBLIC_API.md';
    }
}
