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

use Coretsia\Platform\Cli\Application;
use PHPUnit\Framework\TestCase;

final class CliConfigSubtreeShapeAndMergeSemanticsTest extends TestCase
{
    public function testConfigCliPhpReturnsSubtreeAndDoesNotRepeatRootKey(): void
    {
        $packageRoot = \dirname(__DIR__, 2);
        $repoRoot = \dirname($packageRoot, 4);

        $defaultsFile = $packageRoot . '/config/cli.php';
        self::assertFileExists($defaultsFile);

        $defaults = require $defaultsFile;
        self::assertIsArray($defaults, 'platform/cli config/cli.php MUST return the cli subtree array.');
        self::assertArrayNotHasKey('cli', $defaults, 'config/cli.php MUST NOT repeat root key "cli".');

        // Optional repo template check (MUST NOT be required for runtime).
        $templateFile = $repoRoot . '/skeleton/config/cli.php';
        if (\is_file($templateFile)) {
            $tpl = require $templateFile;
            self::assertIsArray($tpl, 'skeleton/config/cli.php (if present) MUST return the cli subtree array.');
            self::assertArrayNotHasKey('cli', $tpl, 'skeleton/config/cli.php MUST NOT repeat root key "cli".');
        }

        self::addToAssertionCount(1);
    }

    public function testCliCommandsMergeIsAppendUniqueWithStableFirstOccurrenceOrder(): void
    {
        $app = new Application('launcher-file-placeholder');

        $ref = new \ReflectionObject($app);
        $merge = $ref->getMethod('mergeCliSubtrees');

        // Defaults → preset → app (cemented order)
        $defaults = [
            'commands' => ['A\\Cmd', 'B\\Cmd', 'A\\Cmd'],
            'output' => [
                'format' => 'text',
                'redaction' => ['enabled' => true],
            ],
        ];

        $preset = [
            'commands' => ['B\\Cmd', 'C\\Cmd'],
        ];

        $appOverride = [
            'commands' => ['C\\Cmd', 'D\\Cmd', 'B\\Cmd'],
        ];

        /** @var array<string,mixed> $cli */
        $cli = $defaults;

        /** @var array<string,mixed> $cli */
        $cli = $merge->invoke($app, $cli, $preset);

        /** @var array<string,mixed> $cli */
        $cli = $merge->invoke($app, $cli, $appOverride);

        self::assertArrayHasKey('commands', $cli);
        self::assertIsArray($cli['commands']);
        self::assertTrue(\array_is_list($cli['commands']), 'cli.commands MUST remain a list.');

        // append-unique with stable first-occurrence order across sources:
        // [A, B, A] + [B, C] + [C, D, B] => [A, B, C, D]
        self::assertSame(['A\\Cmd', 'B\\Cmd', 'C\\Cmd', 'D\\Cmd'], $cli['commands']);
    }
}
