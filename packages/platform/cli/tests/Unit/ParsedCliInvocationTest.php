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

use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\ParsedCliInvocation;
use PHPUnit\Framework\TestCase;

final class ParsedCliInvocationTest extends TestCase
{
    public function testKeepsExactlyOneCommandInputAndSeparateNullableGlobalOverrides(): void
    {
        $parsed = new ArgvInputParser()->parse([
            'coretsia',
            'external:mode',
            '--format=json',
            '--mode=fast',
            '--color=never',
            'source',
        ]);
        $input = $parsed->commandInput;
        self::assertInstanceOf(ArgvInput::class, $input);
        self::assertSame($input, $parsed->commandInput);
        self::assertSame('json', $parsed->formatOverride);
        self::assertSame('never', $parsed->colorOverride);
        self::assertSame('external:mode', $input->commandName());
        self::assertSame(['source'], $input->arguments());
        self::assertSame(['mode' => 'fast'], $input->options());
        self::assertSame(['external:mode', '--mode=fast', 'source'], $input->tokens());
        self::assertFalse($input->hasOption('format'));
        self::assertFalse($input->hasOption('color'));
        self::assertNotContains('coretsia', $input->tokens());

        $withoutOverrides = new ArgvInputParser()->parse(['coretsia', 'list']);
        self::assertNull($withoutOverrides->formatOverride);
        self::assertNull($withoutOverrides->colorOverride);
        self::assertSame(['list'], $withoutOverrides->commandInput->tokens());
    }

    public function testValueObjectIsReadonlyAndHasOnlyCanonicalProperties(): void
    {
        $reflection = new \ReflectionClass(ParsedCliInvocation::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        $names = \array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            $reflection->getProperties()
        );
        self::assertSame(['commandInput', 'formatOverride', 'colorOverride'], $names);
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly());
            self::assertTrue($property->isPublic());
        }

        $input = new ArgvInput(['list'], 'list', [], []);
        self::assertSame($input, new ParsedCliInvocation($input)->commandInput);
    }
}
