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

namespace Coretsia\Kernel\Tests\Unit\Config;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Config\ConfigValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigValidatorRejectsInvalidCliCommandsTest extends TestCase
{
    /**
     * @param mixed $commands
     */
    #[DataProvider('invalidCommandsProvider')]
    public function testRejectsInvalidCliCommands(
        mixed $commands,
        string $expectedPath,
        string $expectedReason,
        string $expectedType,
        string $actualType,
    ): void {
        $config = [
            'cli' => self::cliConfig(),
        ];

        $config['cli']['commands'] = $commands;

        $result = new ConfigValidator()->validate($config, [self::cliRuleset()]);

        self::assertTrue($result->isFailure());
        self::assertCount(1, $result->violations());

        $violation = $result->violations()[0];

        self::assertSame('cli', $violation->root());
        self::assertSame($expectedPath, $violation->path());
        self::assertSame($expectedReason, $violation->reason());
        self::assertSame($expectedType, $violation->expected());
        self::assertSame($actualType, $violation->actualType());
    }

    /**
     * @return iterable<string, array{0:mixed,1:string,2:string,3:string,4:string}>
     */
    public static function invalidCommandsProvider(): iterable
    {
        yield 'commands-must-be-map' => [
            'config:list',
            'commands',
            'type',
            'map',
            'string',
        ];

        yield 'registry-list-is-forbidden' => [
            ['config:list'],
            'commands',
            'type',
            'map',
            'list',
        ];

        yield 'overrides-is-required' => [
            [],
            'commands.overrides',
            'required',
            'present',
            'null',
        ];

        yield 'unknown-static-command-key' => [
            [
                'overrides' => [],
                'registry' => 'config:list',
            ],
            'commands.registry',
            'unknown-key',
            'declared-key',
            'string',
        ];

        yield 'overrides-must-be-map' => [
            [
                'overrides' => 'config:list',
            ],
            'commands.overrides',
            'type',
            'map',
            'string',
        ];

        yield 'overrides-must-not-be-list' => [
            [
                'overrides' => ['config:list'],
            ],
            'commands.overrides',
            'type',
            'map',
            'list',
        ];
    }

    public function testCommandOverrideEntriesAreNotValidatedByStructuralRules(): void
    {
        $config = [
            'cli' => self::cliConfig(),
        ];

        $config['cli']['commands']['overrides'] = [
            'custom:command' => ['summary' => 'Example'],
        ];

        $result = new ConfigValidator()->validate($config, [self::cliRuleset()]);

        self::assertTrue($result->isSuccess());
    }

    private static function cliRuleset(): ConfigRuleset
    {
        $rules = require self::cliRulesPath();

        self::assertIsArray($rules);

        return ConfigRuleset::fromArray('cli', $rules);
    }

    /**
     * @return array<string,mixed>
     */
    private static function cliConfig(): array
    {
        $config = require self::cliConfigPath();

        self::assertIsArray($config);

        /** @var array<string,mixed> $config */
        return $config;
    }

    private static function cliRulesPath(): string
    {
        return __DIR__ . '/../../../../../platform/cli/config/rules.php';
    }

    private static function cliConfigPath(): string
    {
        return __DIR__ . '/../../../../../platform/cli/config/cli.php';
    }
}
