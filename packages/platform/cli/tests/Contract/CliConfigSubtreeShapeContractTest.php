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

namespace Coretsia\Platform\Cli\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class CliConfigSubtreeShapeContractTest extends TestCase
{
    public function testDefaultsExposeOnlyTheCanonicalCliSubtree(): void
    {
        $defaults = require \dirname(__DIR__, 2) . '/config/cli.php';
        self::assertIsArray($defaults);
        self::assertSame([
            'commands' => [
                'overrides' => [],
            ],
            'workflows' => [
                'definitions' => [],
            ],
            'output' => [
                'format_default' => 'adaptive',
                'adaptive' => [
                    'interactive' => 'table',
                    'non_interactive' => 'table',
                ],
                'color_default' => 'auto',
                'table' => ['max_width' => 120],
            ],
        ], $defaults);
        self::assertArrayNotHasKey('cli', $defaults);
        self::assertArrayNotHasKey('redaction', $defaults);
        self::assertArrayNotHasKey('registry', $defaults['commands']);
    }

    public function testDefaultsAreStaticJsonLikeWithoutRuntimeInputsOrEnvironmentReads(): void
    {
        $path = \dirname(__DIR__, 2) . '/config/cli.php';
        $source = \file_get_contents($path);
        self::assertIsString($source);
        $forbiddenTokens = [
            T_VARIABLE,
            T_NEW,
            T_FUNCTION,
            T_FN,
            T_INCLUDE,
            T_INCLUDE_ONCE,
            T_REQUIRE,
            T_REQUIRE_ONCE,
            T_EVAL,
            T_YIELD,
            T_YIELD_FROM,
        ];
        foreach (\token_get_all($source) as $token) {
            if (!\is_array($token) || \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            self::assertNotContains($token[0], $forbiddenTokens, 'Defaults must not depend on runtime code.');
        }
        $code = \implode(
            '',
            \array_map(
                static fn (array|string $token): string => \is_array($token)
                && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : (\is_array(
                    $token,
                ) ? $token[1] : $token),
                \token_get_all($source),
            ),
        );
        self::assertDoesNotMatchRegularExpression('/\b(?:getenv|putenv|ini_get|constant)\s*\(/', $code);
        $defaults = require $path;
        self::assertIsArray($defaults);
        self::assertStaticValue($defaults);
    }

    private static function assertStaticValue(mixed $value): void
    {
        if (\is_array($value)) {
            foreach ($value as $key => $entry) {
                self::assertTrue(\is_string($key) || \is_int($key));
                self::assertStaticValue($entry);
            }
            return;
        }
        self::assertTrue(\is_string($value) || \is_int($value) || \is_bool($value) || $value === null);
    }
}
