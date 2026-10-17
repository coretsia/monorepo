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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Output\CliOutputPolicy;
use Coretsia\Platform\Cli\Output\ColorResolver;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use PHPUnit\Framework\TestCase;

final class ColorResolverTest extends TestCase
{
    public function testAutoRequiresBothInteractiveAndAnsiSupported(): void
    {
        $resolver = self::resolver('auto');
        foreach (['table', 'plain'] as $format) {
            self::assertTrue($resolver->resolve(null, new TerminalCapabilities(true, true), $format));
            self::assertFalse($resolver->resolve(null, new TerminalCapabilities(true, false), $format));
            self::assertFalse($resolver->resolve(null, new TerminalCapabilities(false, true), $format));
            self::assertFalse($resolver->resolve(null, new TerminalCapabilities(false, false), $format));
        }
    }

    public function testExplicitPolicyAlwaysAndNeverForTextButNeverJson(): void
    {
        foreach (['table', 'plain'] as $format) {
            self::assertTrue(
                self::resolver('never')->resolve('always', new TerminalCapabilities(false, false), $format),
            );
            self::assertFalse(
                self::resolver('always')->resolve('never', new TerminalCapabilities(true, true), $format),
            );
            self::assertTrue(self::resolver('always')->resolve(null, new TerminalCapabilities(false, false), $format));
            self::assertFalse(self::resolver('never')->resolve(null, new TerminalCapabilities(true, true), $format));
        }
        foreach (['auto', 'always', 'never'] as $setting) {
            foreach (['auto', 'always', 'never', null] as $override) {
                self::assertFalse(
                    self::resolver($setting)->resolve($override, new TerminalCapabilities(true, true), 'json'),
                );
            }
        }
    }

    public function testInvalidColorFails(): void
    {
        $this->expectException(CliInputInvalidException::class);
        self::resolver('auto')->resolve('invalid', new TerminalCapabilities(true, true), 'plain');
    }

    private static function resolver(string $default): ColorResolver
    {
        return new ColorResolver(new CliOutputPolicy('adaptive', 'table', 'plain', $default, 120));
    }
}
