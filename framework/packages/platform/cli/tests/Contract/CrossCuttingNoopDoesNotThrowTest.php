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

use Coretsia\Platform\Cli\Module\CliModule;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use PHPUnit\Framework\TestCase;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testModuleAndProviderAreLoadableAndDoNotThrow(): void
    {
        // Static API in this package (Phase 0).
        $providers = CliModule::providers();

        self::assertIsArray($providers, 'CliModule::providers() MUST return an array.');
        self::assertNotEmpty($providers, 'CliModule::providers() MUST NOT be empty for a runtime package.');

        foreach ($providers as $providerFqcn) {
            self::assertIsString($providerFqcn);
            self::assertNotSame('', $providerFqcn);
        }

        $provider = new CliServiceProvider();

        $id = $provider->id();
        self::assertIsString($id);
        self::assertNotSame('', $id);

        $factories = CliServiceProvider::factories();
        self::assertIsArray($factories, 'CliServiceProvider::factories() MUST return an array.');
    }

    public function testConfigFilesAreLoadableAndHaveNoSideEffects(): void
    {
        // tests/Contract -> tests -> <package-root>
        $packageRoot = \dirname(__DIR__, 2);

        $cliFile = $packageRoot . '/config/cli.php';
        $rulesFile = $packageRoot . '/config/rules.php';

        self::assertFileExists($cliFile);
        self::assertFileExists($rulesFile);

        // cli.php: MUST be output-free and MUST return subtree array (no root wrapper).
        \ob_start();
        $cliSubtree = require $cliFile;
        $out = (string)\ob_get_clean();

        self::assertSame('', $out, 'config/cli.php MUST NOT emit output.');
        self::assertIsArray($cliSubtree, 'config/cli.php MUST return an array subtree.');
        self::assertArrayNotHasKey('cli', $cliSubtree, 'config/cli.php MUST NOT repeat the root key ("cli").');

        // rules.php: MUST be output-free and MUST return callable(array $cfg): void
        \ob_start();
        $rules = require $rulesFile;
        $out = (string)\ob_get_clean();

        self::assertSame('', $out, 'config/rules.php MUST NOT emit output.');
        self::assertIsCallable($rules, 'config/rules.php MUST return a callable.');

        // Minimal sanity: rules callable should accept the global config map that contains "cli" root.
        $rules(['cli' => $cliSubtree]);

        self::addToAssertionCount(1);
    }
}
