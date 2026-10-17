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

final class ConfigValidatorRejectsInvalidCliOutputFormatTest extends TestCase
{
    public function testRejectsOutputFormatOutsideAllowedValues(): void
    {
        $config = [
            'cli' => self::cliConfig(),
        ];

        $config['cli']['output']['format_default'] = 'xml';

        $result = new ConfigValidator()->validate($config, [self::cliRuleset()]);

        self::assertTrue($result->isFailure());
        self::assertCount(1, $result->violations());

        $violation = $result->violations()[0];

        self::assertSame('cli', $violation->root());
        self::assertSame('output.format_default', $violation->path());
        self::assertSame('allowed-values', $violation->reason());
        self::assertSame('allowedValues', $violation->expected());
        self::assertSame('string', $violation->actualType());
    }

    public function testRejectsOutputFormatWithWrongTypeBeforeAllowedValuesMatter(): void
    {
        $config = [
            'cli' => self::cliConfig(),
        ];

        $config['cli']['output']['format_default'] = 1;

        $result = new ConfigValidator()->validate($config, [self::cliRuleset()]);

        self::assertTrue($result->isFailure());
        self::assertCount(1, $result->violations());

        $violation = $result->violations()[0];

        self::assertSame('cli', $violation->root());
        self::assertSame('output.format_default', $violation->path());
        self::assertSame('type', $violation->reason());
        self::assertSame('string', $violation->expected());
        self::assertSame('int', $violation->actualType());
    }

    public function testAcceptsEveryCanonicalOutputFormat(): void
    {
        $ruleset = self::cliRuleset();
        self::assertSame(
            ['adaptive', 'json', 'table', 'plain'],
            $ruleset->rules()['keys']['output']['keys']['format_default']['allowedValues'],
        );

        foreach (['adaptive', 'json', 'table', 'plain'] as $format) {
            $config = ['cli' => self::cliConfig()];
            $config['cli']['output']['format_default'] = $format;

            $result = new ConfigValidator()->validate($config, [$ruleset]);

            self::assertTrue($result->isSuccess(), 'Expected valid CLI format: ' . $format);
            self::assertSame([], $result->violations());
        }
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
