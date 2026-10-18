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
use PHPUnit\Framework\TestCase;

final class ConfigValidatorDiagnosticsAreSafeAndDeterministicTest extends TestCase
{
    public function testDiagnosticsContainOnlyStructuralMetadataAndAreDeterministic(): void
    {
        $ruleset = self::cliRuleset();

        $config = [
            'cli' => self::cliConfig(),
        ];
        $config['cli']['commands']['overrides'] = 'bad command with token SECRET_TOKEN';
        $config['cli']['output']['format_default'] = 'mysql://user:pass@example.test/database';
        $config['cli']['output']['color_default'] = false;
        $config['cli']['/var/www/secret'] = true;

        $first = new ConfigValidator()->validate($config, [$ruleset]);
        $second = new ConfigValidator()->validate($config, [$ruleset]);

        self::assertTrue($first->isFailure());
        self::assertTrue($second->isFailure());
        self::assertSame($first->toArray(), $second->toArray());

        self::assertSame(
            [
                [
                    'actualType' => 'string',
                    'expected' => 'declared-key',
                    'path' => '<key>',
                    'reason' => 'unknown-key',
                    'root' => 'cli',
                    'schemaVersion' => 1,
                ],
                [
                    'actualType' => 'string',
                    'expected' => 'map',
                    'path' => 'commands.overrides',
                    'reason' => 'type',
                    'root' => 'cli',
                    'schemaVersion' => 1,
                ],
                [
                    'actualType' => 'bool',
                    'expected' => 'string',
                    'path' => 'output.color_default',
                    'reason' => 'type',
                    'root' => 'cli',
                    'schemaVersion' => 1,
                ],
                [
                    'actualType' => 'string',
                    'expected' => 'allowedValues',
                    'path' => 'output.format_default',
                    'reason' => 'allowed-values',
                    'root' => 'cli',
                    'schemaVersion' => 1,
                ],
            ],
            $first->toArray()['violations'],
        );

        $diagnostics = \json_encode($first->toArray(), \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('SECRET_TOKEN', $diagnostics);
        self::assertStringNotContainsString('bad command', $diagnostics);
        self::assertStringNotContainsString('mysql://', $diagnostics);
        self::assertStringNotContainsString('user:pass', $diagnostics);
        self::assertStringNotContainsString('example.test', $diagnostics);
        self::assertStringNotContainsString('/var/www/secret', $diagnostics);
        self::assertStringNotContainsString('yes', $diagnostics);
    }

    public function testValidationResultSortsViolationsDeterministically(): void
    {
        $ruleset = self::cliRuleset();

        $config = [
            'cli' => self::cliConfig(),
        ];
        $config['cli']['commands']['overrides'] = [''];
        $config['cli']['output']['format_default'] = 'xml';
        $config['cli']['output']['table']['max_width'] = 39;

        $result = new ConfigValidator()->validate($config, [$ruleset]);

        self::assertTrue($result->isFailure());
        self::assertSame(
            [
                'commands.overrides',
                'output.format_default',
                'output.table.max_width',
            ],
            \array_map(
                static fn ($violation): string => $violation->path(),
                $result->violations(),
            ),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function cliConfig(): array
    {
        $config = require __DIR__ . '/../../../../../platform/cli/config/cli.php';

        self::assertIsArray($config);

        return $config;
    }

    private static function cliRuleset(): ConfigRuleset
    {
        $rules = require self::cliRulesPath();

        self::assertIsArray($rules);

        return ConfigRuleset::fromArray('cli', $rules);
    }

    private static function cliRulesPath(): string
    {
        return __DIR__ . '/../../../../../platform/cli/config/rules.php';
    }
}
