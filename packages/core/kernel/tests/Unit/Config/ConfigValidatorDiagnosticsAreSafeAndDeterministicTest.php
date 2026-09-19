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
            'cli' => [
                'commands' => [
                    'bad command with token SECRET_TOKEN',
                ],
                'output' => [
                    'format' => 'mysql://user:pass@example.test/database',
                    'redaction' => [
                        'enabled' => 'yes',
                    ],
                ],
                '/var/www/secret' => true,
            ],
        ];

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
                    'expected' => 'non-empty-string-no-ws',
                    'path' => 'commands[0]',
                    'reason' => 'type',
                    'root' => 'cli',
                    'schemaVersion' => 1,
                ],
                [
                    'actualType' => 'string',
                    'expected' => 'allowedValues',
                    'path' => 'output.format',
                    'reason' => 'allowed-values',
                    'root' => 'cli',
                    'schemaVersion' => 1,
                ],
                [
                    'actualType' => 'string',
                    'expected' => 'bool',
                    'path' => 'output.redaction.enabled',
                    'reason' => 'type',
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
            'cli' => [
                'output' => [
                    'redaction' => [
                        'enabled' => 'yes',
                    ],
                    'format' => 'xml',
                ],
                'commands' => [
                    '',
                ],
            ],
        ];

        $result = new ConfigValidator()->validate($config, [$ruleset]);

        self::assertTrue($result->isFailure());
        self::assertSame(
            [
                'commands[0]',
                'output.format',
                'output.redaction.enabled',
            ],
            \array_map(
                static fn ($violation): string => $violation->path(),
                $result->violations(),
            ),
        );
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
