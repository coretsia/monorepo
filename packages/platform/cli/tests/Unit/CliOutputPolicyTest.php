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

use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Output\CliOutputPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CliOutputPolicyTest extends TestCase
{
    public function testAcceptsCanonicalDefaultsAndWidthBoundaries(): void
    {
        foreach ([40, 120, 240] as $width) {
            $policy = new CliOutputPolicy(
                'adaptive',
                'table',
                'plain',
                'auto',
                $width,
            );
            self::assertSame('adaptive', $policy->formatDefault());
            self::assertSame('table', $policy->interactiveFormat());
            self::assertSame('plain', $policy->nonInteractiveFormat());
            self::assertSame('auto', $policy->colorDefault());
            self::assertSame($width, $policy->tableMaxWidth());
        }
    }

    #[DataProvider('invalidPolicies')]
    public function testRejectsUnsupportedPolicy(
        string $default,
        string $interactive,
        string $nonInteractive,
        string $color,
        int $width,
    ): void {
        $this->expectException(CliConfigInvalidException::class);
        new CliOutputPolicy($default, $interactive, $nonInteractive, $color, $width);
    }

    public static function invalidPolicies(): iterable
    {
        yield 'invalid-default' => ['xml', 'table', 'plain', 'auto', 120];
        yield 'invalid-interactive' => ['adaptive', 'adaptive', 'plain', 'auto', 120];
        yield 'invalid-noninteractive' => ['adaptive', 'table', 'adaptive', 'auto', 120];
        yield 'invalid-color' => ['adaptive', 'table', 'plain', 'sometimes', 120];
        yield 'width-low' => ['adaptive', 'table', 'plain', 'auto', 39];
        yield 'width-high' => ['adaptive', 'table', 'plain', 'auto', 241];
    }
}
