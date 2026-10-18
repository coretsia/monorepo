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
use Coretsia\Platform\Cli\Output\FormatResolver;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use PHPUnit\Framework\TestCase;

final class FormatResolverTest extends TestCase
{
    public function testExplicitFormatOverridesDefaultAndAdaptiveSelectsByProvidedCapabilities(): void
    {
        $resolver = new FormatResolver(
            new CliOutputPolicy(
                'adaptive',
                'table',
                'plain',
                'auto',
                120,
            ),
        );
        foreach ([true, false] as $interactive) {
            $capabilities = new TerminalCapabilities($interactive, false);
            self::assertSame('json', $resolver->resolve('json', $capabilities));
            self::assertSame('table', $resolver->resolve('table', $capabilities));
            self::assertSame('plain', $resolver->resolve('plain', $capabilities));
            self::assertSame($interactive ? 'table' : 'plain', $resolver->resolve(null, $capabilities));
            self::assertSame($interactive ? 'table' : 'plain', $resolver->resolve('adaptive', $capabilities));
        }
    }

    public function testConfiguredNonAdaptiveDefaultAndNoEnvironmentDependency(): void
    {
        $resolver = new FormatResolver(
            new CliOutputPolicy(
                'json',
                'table',
                'plain',
                'auto',
                120,
            ),
        );
        foreach ([true, false] as $interactive) {
            self::assertSame(
                'json',
                $resolver->resolve(null, new TerminalCapabilities($interactive, true)),
            );
        }
        $method = new \ReflectionMethod(FormatResolver::class, 'resolve');
        $lines = \file($method->getFileName());
        self::assertIsArray($lines);
        $source = \implode(
            '',
            \array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1),
        );
        self::assertDoesNotMatchRegularExpression('/\b(?:getenv|_ENV|_SERVER)\b/', $source);
    }

    public function testUnsupportedExplicitFormatFails(): void
    {
        $resolver = new FormatResolver(
            new CliOutputPolicy(
                'adaptive',
                'table',
                'plain',
                'auto',
                120,
            ),
        );
        $this->expectException(CliInputInvalidException::class);
        $resolver->resolve('xml', new TerminalCapabilities(true, true));
    }
}
