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

final class ConfigValidatorAcceptsCliRulesFixtureTest extends TestCase
{
    public function testAcceptsCliDefaultConfigAgainstCliRulesFixture(): void
    {
        $ruleset = self::cliRuleset();
        $config = [
            'cli' => self::cliConfig(),
        ];

        $validator = new ConfigValidator();
        $result = $validator->validate($config, [$ruleset]);

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame([], $result->violations());
        self::assertSame(
            [
                'schemaVersion' => 1,
                'success' => true,
                'violations' => [],
            ],
            $result->toArray(),
        );
    }

    public function testCliRootIsReportedAsValidatedSubject(): void
    {
        $ruleset = self::cliRuleset();
        $config = [
            'cli' => self::cliConfig(),
        ];

        $subjects = new ConfigValidator()->validationSubjects($config, [$ruleset]);

        self::assertSame(
            [
                'unvalidated' => [],
                'validated' => [
                    [
                        'ownership' => 'ruleset_owned',
                        'root' => 'cli',
                        'validation' => 'validated',
                    ],
                ],
            ],
            $subjects,
        );
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
