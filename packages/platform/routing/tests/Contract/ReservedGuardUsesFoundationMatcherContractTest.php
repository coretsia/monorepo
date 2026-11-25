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

namespace Coretsia\Platform\Routing\Tests\Contract;

use Coretsia\Foundation\Routing\ReservedPathMatcher;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use PHPUnit\Framework\TestCase;

final class ReservedGuardUsesFoundationMatcherContractTest extends TestCase
{
    public function testGuardUsesCanonicalRegistryAndConcreteWitnessMatcherDirectly(): void
    {
        $reflection = new \ReflectionClass(ReservedRoutesGuard::class);
        $code = self::code($reflection);
        self::assertStringContainsString('use ' . SystemRouteTable::class . ';', $code);
        self::assertStringContainsString('use ' . ReservedPathMatcher::class . ';', $code);
        self::assertMatchesRegularExpression('/foreach\s*\(\s*SystemRouteTable::entries\(\)/', $code);
        self::assertMatchesRegularExpression(
            '/if\s*\(\s*ReservedPathMatcher::match\(\s*\$path\s*\)\s*!==\s*null\s*\)\s*\{'
            . '\s*throw\s+ReservedRouteViolationException::forbidden\(\s*\)\s*;\s*}/',
            $code,
        );
        self::assertMatchesRegularExpression('/\$template->segmentMatches\(/', $code);
        self::assertMatchesRegularExpression('/\$template->representativeSegment\(/', $code);
        self::assertMatchesRegularExpression('/\$path\s*=\s*\$witness\s*===\s*\[]\s*\?/', $code);
        self::assertSame([], $reflection->getProperties());
        self::assertSame([], $reflection->getReflectionConstants());
    }

    public function testGuardHasNoLocalReservationTableOrConcretePathNormalization(): void
    {
        $reflection = new \ReflectionClass(ReservedRoutesGuard::class);
        $code = self::code($reflection);
        // Only structural delimiters and canonical declaration member names belong here.
        $literals = [];
        // Only structural witness assembly may call native functions.
        $functions = [];
        $tokens = \array_values(
            \array_filter(
                \token_get_all('<?php ' . $code),
                static fn (array|string $token): bool => !\is_array($token) || $token[0] !== \T_WHITESPACE,
            ),
        );
        foreach ($tokens as $index => $token) {
            if (\is_array($token) && $token[0] === \T_CONSTANT_ENCAPSED_STRING) {
                $literals[] = $token[1];
            }
            if (
                !\is_array($token)
                || !\in_array(
                    $token[0],
                    [\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED, \T_NAME_RELATIVE],
                    true,
                )
                || ($tokens[$index + 1] ?? null) !== '('
            ) {
                continue;
            }
            $previous = $tokens[$index - 1] ?? null;
            if (\is_array($previous) && \in_array(
                $previous[0],
                [\T_FUNCTION, \T_OBJECT_OPERATOR, \T_DOUBLE_COLON],
                true,
            )) {
                continue;
            }
            $functions[] = \strtolower(\ltrim($token[1], '\\'));
        }
        \sort($functions, \SORT_STRING);
        self::assertSame(['count', 'explode', 'implode', 'trim'], $functions);
        foreach ($literals as $literal) {
            self::assertContains($literal, ["'/'", "'pattern'", "'type'", "'prefix'"]);
        }
        self::assertDoesNotMatchRegularExpression(
            '/\b(?:preg_\w+|str_replace|strspn|strtolower|strtoupper|urldecode|rawurldecode|parse_url|normalize)\s*\(/i',
            $code,
        );
        self::assertSame(
            ['assertAllowed'],
            \array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ),
        );

        $runtime = self::code(new \ReflectionClass(CompiledRouter::class));
        self::assertStringContainsString('use ' . ReservedPathMatcher::class . ';', $runtime);
        self::assertMatchesRegularExpression(
            '/if\s*\(\s*ReservedPathMatcher::normalize\(\s*\$path\s*\)\s*!==\s*\$path\s*\)\s*\{'
            . '\s*return\s+null\s*;\s*}/',
            $runtime,
        );
        self::assertMatchesRegularExpression(
            '/if\s*\(\s*ReservedPathMatcher::match\(\s*\$path\s*\)\s*!==\s*null\s*\)\s*\{'
            . '\s*return\s+null\s*;\s*}/',
            $runtime,
        );
        self::assertDoesNotMatchRegularExpression(
            '/\b(?:preg_replace|str_replace|urldecode|rawurldecode|parse_url)\s*\(/i',
            $runtime,
        );
    }

    /** @param \ReflectionClass<object> $reflection */
    private static function code(\ReflectionClass $reflection): string
    {
        $file = $reflection->getFileName();
        self::assertIsString($file);
        $source = \file_get_contents($file);
        self::assertIsString($source);
        $code = '';
        foreach (\token_get_all($source) as $token) {
            if (\is_array($token)) {
                if (\in_array($token[0], [\T_OPEN_TAG, \T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }
}
