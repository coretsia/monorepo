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
use Coretsia\Platform\Cli\Output\FormatResolver;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliDispatchIntegrationFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingOwnerCommand;

final class NonInteractiveAdaptiveOutputUsesPlainFormatTest extends CliIntegrationTestCase
{
    public function testBaselineNoninteractiveAdaptiveUsesPlainAndNeverPassesResolvedFormatToOwner(): void
    {
        $root = self::consumer();
        try {
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $config = $host->get(ConfigRepositoryInterface::class);
            $policy = $host->get(CliOutputPolicy::class);
            self::assertSame('adaptive', $config->get('cli')['output']['format_default']);
            self::assertSame('plain', $config->get('cli')['output']['adaptive']['non_interactive']);
            self::assertSame(
                'plain',
                new FormatResolver($policy)->resolve(null, new TerminalCapabilities(false, false)),
            );
            $plain = self::execute($root . '/vendor/bin/coretsia', ['list']);
            self::assertSame(0, $plain['exit'], $plain['stderr']);
            self::assertStringNotContainsString('coretsia.cli-output@1', $plain['stdout']);
            self::assertStringNotContainsString("\x1B", $plain['stdout']);
            $json = self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']);
            self::assertSame(0, $json['exit'], $json['stderr']);
            self::jsonRecord(\rtrim($json['stdout'], "\n"));
        } finally {
            self::removeTree($root);
        }
    }

    public function testInjectedCapabilitiesAloneSelectAdaptiveMappingRegardlessOfEnvironment(): void
    {
        $policy = new CliOutputPolicy('adaptive', 'table', 'plain', 'auto', 120);
        foreach ([false, true] as $interactive) {
            RecordingOwnerCommand::reset();
            $fixture = new CliDispatchIntegrationFixture($policy);
            $result = $fixture->run(['owner:probe', '--mode=domain'], new TerminalCapabilities($interactive, false));
            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame(['mode' => 'domain'], RecordingOwnerCommand::$received['options']);
            self::assertSame(1, $fixture->runtime->calls);
            self::assertSame(1, RecordingOwnerCommand::$executed);
            self::assertStringNotContainsString('coretsia.cli-output@1', $result['stdout']);
            if ($interactive) {
                self::assertStringContainsString('handled', $result['stdout']);
            } else {
                self::assertStringContainsString('{"handled":true,"items":[]}', $result['stdout']);
            }
        }
        $source = (string) \file_get_contents(new \ReflectionClass(FormatResolver::class)->getFileName());
        foreach (['getenv(', 'CI', 'stream_isatty(', 'COLUMNS', 'TERM'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }
}
