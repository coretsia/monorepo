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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Output\CliOutputPolicy;
use Coretsia\Platform\Cli\Output\ColorResolver;
use Coretsia\Platform\Cli\Output\FormatResolver;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CliConfigChangesAffectOutputPolicyTest extends CliIntegrationTestCase
{
    public function testEveryPolicySettingComesFromMergedValidatedCliSubtree(): void
    {
        $root = self::consumer();
        try {
            self::write(
                $root . '/config/cli.php',
                '<?php return ' . \var_export([
                    'output' => [
                        'format_default' => 'adaptive',
                        'adaptive' => ['interactive' => 'json', 'non_interactive' => 'table'],
                        'color_default' => 'always',
                        'table' => ['max_width' => 48],
                    ],
                ], true) . ';',
            );
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $config = $host->get(ConfigRepositoryInterface::class);
            $raw = $config->get('cli')['output'];
            $policy = $host->get(CliOutputPolicy::class);
            self::assertSame($raw['format_default'], $policy->formatDefault());
            self::assertSame($raw['adaptive']['interactive'], $policy->interactiveFormat());
            self::assertSame($raw['adaptive']['non_interactive'], $policy->nonInteractiveFormat());
            self::assertSame($raw['color_default'], $policy->colorDefault());
            self::assertSame($raw['table']['max_width'], $policy->tableMaxWidth());

            $formats = $host->get(FormatResolver::class);
            $colors = $host->get(ColorResolver::class);
            $interactive = new TerminalCapabilities(true, true);
            $nonInteractive = new TerminalCapabilities(false, false);
            self::assertSame('json', $formats->resolve(null, $interactive));
            self::assertSame('table', $formats->resolve(null, $nonInteractive));
            self::assertSame('plain', $formats->resolve('plain', $interactive));
            self::assertSame('plain', $formats->resolve('plain', $nonInteractive));
            self::assertTrue($colors->resolve(null, $nonInteractive, 'plain'));
            self::assertFalse($colors->resolve('never', $interactive, 'plain'));
            self::assertFalse($colors->resolve(null, $interactive, 'json'));
            self::assertFalse($colors->resolve('always', $interactive, 'json'));

            $normal = self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']);
            self::assertSame(0, $normal['exit'], $normal['stderr']);
            self::jsonRecord(\rtrim($normal['stdout'], "\n"));
            $default = self::execute($root . '/vendor/bin/coretsia', ['list', '--color=never']);
            self::assertSame(0, $default['exit'], $default['stderr']);
            foreach (\explode("\n", \rtrim($default['stdout'], "\n")) as $line) {
                self::assertLessThanOrEqual(48, \strlen($line), $line);
            }
        } finally {
            self::removeTree($root);
        }
    }

    public function testTableWidthDoesNotChangePlainOrJsonOutput(): void
    {
        $results = [];
        foreach ([48, 100] as $width) {
            $root = self::consumer();
            try {
                self::write(
                    $root . '/config/cli.php',
                    '<?php return ' . \var_export([
                        'output' => ['table' => ['max_width' => $width]],
                    ], true) . ';',
                );
                foreach (['plain', 'json', 'table'] as $format) {
                    $result = self::execute($root . '/vendor/bin/coretsia', [
                        'list',
                        '--format=' . $format,
                        '--color=never',
                    ]);
                    self::assertSame(0, $result['exit'], $result['stderr']);
                    $results[$width][$format] = $result['stdout'];
                }
                foreach (\explode("\n", \rtrim($results[$width]['table'], "\n")) as $line) {
                    self::assertLessThanOrEqual($width, \strlen($line), $line);
                }
            } finally {
                self::removeTree($root);
            }
        }
        self::assertSame($results[48]['plain'], $results[100]['plain']);
        self::assertSame($results[48]['json'], $results[100]['json']);
        self::assertNotSame($results[48]['table'], $results[100]['table']);
    }

    public function testInteractiveMappingAndExplicitColorReachTheFinalRenderer(): void
    {
        $root = self::consumer();
        try {
            self::write(
                $root . '/config/cli.php',
                '<?php return ' . \var_export([
                    'output' => [
                        'format_default' => 'adaptive',
                        'adaptive' => ['interactive' => 'plain', 'non_interactive' => 'json'],
                        'color_default' => 'never',
                    ],
                ], true) . ';',
            );

            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $policy = $host->get(CliOutputPolicy::class);

            self::assertSame('plain', $policy->interactiveFormat());
            self::assertSame(
                'plain',
                new FormatResolver($policy)->resolve(null, new TerminalCapabilities(true, true)),
            );
            self::assertFalse(
                new ColorResolver($policy)->resolve(null, new TerminalCapabilities(true, true), 'table'),
            );

            $disabled = self::execute($root . '/vendor/bin/coretsia', ['list', '--format=table']);
            self::assertSame(0, $disabled['exit'], $disabled['stderr']);
            self::assertStringNotContainsString("\x1B", $disabled['stdout']);

            $enabled = self::execute($root . '/vendor/bin/coretsia', [
                'list',
                '--format=table',
                '--color=always',
            ]);
            self::assertSame(0, $enabled['exit'], $enabled['stderr']);
            self::assertStringContainsString("\x1B[", $enabled['stdout']);
        } finally {
            self::removeTree($root);
        }
    }
}
