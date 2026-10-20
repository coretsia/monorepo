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

use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Cli\Module\CliModule;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use PHPUnit\Framework\TestCase;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testModuleMetadataAndDeclarativeProviderContract(): void
    {
        $module = new CliModule();

        self::assertSame('platform.cli', $module->id());
        self::assertSame('platform/cli', $module->packageId());
        self::assertSame('coretsia/platform-cli', $module->composerPackage());
        self::assertSame('runtime', $module->kind());
        self::assertSame('cli', $module->configRoot());
        self::assertSame([CliServiceProvider::class], $module->providers());
        self::assertInstanceOf(ServiceProviderInterface::class, new CliServiceProvider());
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, new CliServiceProvider());
    }

    public function testCanonicalConfigAndRulesLoadWithoutOutput(): void
    {
        $packageRoot = \dirname(__DIR__, 2);

        \ob_start();
        try {
            $config = require $packageRoot . '/config/cli.php';
            $rules = require $packageRoot . '/config/rules.php';
        } finally {
            $output = \ob_get_clean();
        }

        self::assertSame('', $output);
        self::assertIsArray($config);
        self::assertIsArray($rules);
        self::assertSame(['commands', 'workflows', 'output'], \array_keys($config));
        self::assertSame(['definitions' => []], $config['workflows']);
        self::assertSame(['overrides' => []], $config['commands']);
        self::assertSame(
            ['format_default', 'adaptive', 'color_default', 'table'],
            \array_keys($config['output']),
        );
        self::assertSame('adaptive', $config['output']['format_default']);
        self::assertSame(
            ['interactive' => 'table', 'non_interactive' => 'table'],
            $config['output']['adaptive'],
        );
        self::assertSame('auto', $config['output']['color_default']);
        self::assertSame(['max_width' => 120], $config['output']['table']);

        self::assertSame(1, $rules['schemaVersion']);
        self::assertSame('cli', $rules['configRoot']);
        self::assertFalse($rules['additionalKeys']);
        self::assertSame(['commands', 'workflows', 'output'], \array_keys($rules['keys']));

        $commandsRules = $rules['keys']['commands'];
        self::assertTrue($commandsRules['required']);
        self::assertSame('map', $commandsRules['type']);
        self::assertFalse($commandsRules['additionalKeys']);
        self::assertSame(['overrides'], \array_keys($commandsRules['keys']));
        self::assertSame('map', $commandsRules['keys']['overrides']['type']);
        self::assertTrue($commandsRules['keys']['overrides']['required']);
        self::assertTrue($commandsRules['keys']['overrides']['additionalKeys']);

        $workflowRules = $rules['keys']['workflows'];
        self::assertTrue($workflowRules['required']);
        self::assertSame('map', $workflowRules['type']);
        self::assertFalse($workflowRules['additionalKeys']);
        self::assertSame(['definitions'], \array_keys($workflowRules['keys']));
        self::assertSame('map', $workflowRules['keys']['definitions']['type']);
        self::assertTrue($workflowRules['keys']['definitions']['required']);
        self::assertTrue($workflowRules['keys']['definitions']['additionalKeys']);

        $outputRules = $rules['keys']['output'];
        self::assertSame(
            ['format_default', 'adaptive', 'color_default', 'table'],
            \array_keys($outputRules['keys']),
        );
        self::assertSame(
            ['adaptive', 'json', 'table', 'plain'],
            $outputRules['keys']['format_default']['allowedValues'],
        );
        self::assertSame(
            ['json', 'table', 'plain'],
            $outputRules['keys']['adaptive']['keys']['interactive']['allowedValues'],
        );
        self::assertSame(
            ['json', 'table', 'plain'],
            $outputRules['keys']['adaptive']['keys']['non_interactive']['allowedValues'],
        );
        self::assertSame(
            ['auto', 'always', 'never'],
            $outputRules['keys']['color_default']['allowedValues'],
        );
        self::assertSame('int', $outputRules['keys']['table']['keys']['max_width']['type']);
        self::assertSame(40, $outputRules['keys']['table']['keys']['max_width']['min']);
        self::assertSame(240, $outputRules['keys']['table']['keys']['max_width']['max']);
    }
}
