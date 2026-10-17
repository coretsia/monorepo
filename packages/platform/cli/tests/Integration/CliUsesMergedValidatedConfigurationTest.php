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
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CliUsesMergedValidatedConfigurationTest extends CliIntegrationTestCase
{
    public function testApplicationCliSubtreeIsMergedAndValidatedBeforeDispatch(): void
    {
        $root = self::consumer();
        try {
            self::write(
                $root . '/config/cli.php',
                '<?php return ' . \var_export([
                    'output' => ['format_default' => 'json', 'table' => ['max_width' => 72]],
                    'commands' => ['overrides' => ['list' => ['summary' => 'Merged catalog description.']]],
                ], true) . ';',
            );
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $config = $host->get(ConfigRepositoryInterface::class);
            self::assertSame('json', $config->get('cli')['output']['format_default']);
            self::assertSame(72, $config->get('cli')['output']['table']['max_width']);
            self::assertSame(
                'Merged catalog description.',
                $config->get('cli')['commands']['overrides']['list']['summary'],
            );
            $result = self::execute($root . '/vendor/bin/coretsia', ['list']);
            self::assertSame(0, $result['exit'], $result['stderr']);
            $records = self::jsonRecord(\rtrim($result['stdout'], "\n"))['data']['records'];
            $descriptors = $records[0]['payload']['groups'];
            $list = null;
            foreach ($descriptors as $group) {
                foreach ($group as $descriptor) {
                    if ($descriptor['name'] === 'list') {
                        $list = $descriptor;
                    }
                }
            }
            self::assertSame('Merged catalog description.', $list['summary'] ?? null);
        } finally {
            self::removeTree($root);
        }
    }

    public function testCanonicalConsoleConfigGateRejectsUnknownKeysAndLegacyRegistryWithoutLeakage(): void
    {
        foreach (
            [
                ['mode' => ['preset' => 'PRIVATE_MODE']],
                ['commands' => ['PRIVATE_LEGACY_REGISTRY' => ['Untrusted\\Command']]],
                ['output' => ['format_default' => 'PRIVATE_INVALID_FORMAT']],
            ] as $invalid
        ) {
            $root = self::consumer();
            try {
                self::write($root . '/config/cli.php', '<?php return ' . \var_export($invalid, true) . ';');
                $result = self::execute($root . '/vendor/bin/coretsia', ['config:validate', '--target=web']);
                self::assertSafeFailure($result, 'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed');
                self::assertStringNotContainsString('PRIVATE_', $result['stderr']);
                self::assertStringNotContainsString($root, $result['stderr']);
            } finally {
                self::removeTree($root);
            }
        }
        $source = (string) \file_get_contents(self::packageRoot() . '/src/Provider/CliServiceFactory.php');
        self::assertStringNotContainsString('ConfigValidator', $source);
        self::assertStringNotContainsString('ConfigKernel', $source);
    }
}
