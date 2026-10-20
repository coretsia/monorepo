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

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Config\ConfigValidator;
use PHPUnit\Framework\TestCase;

final class CliConfigRulesCoverAllDefaultsContractTest extends TestCase
{
    public function testAllStaticKeysHaveRequiredRulesAndNoPhantomRuleKeys(): void
    {
        [$defaults, $rules] = self::configAndRules();
        self::assertSame(1, $rules['schemaVersion']);
        self::assertSame('cli', $rules['configRoot']);
        self::assertFalse($rules['additionalKeys']);
        self::assertExactKeys($defaults, $rules['keys']);
        self::assertTrue($rules['keys']['commands']['keys']['overrides']['additionalKeys']);
        self::assertSame('map', $rules['keys']['commands']['keys']['overrides']['type']);
        self::assertSame(['definitions'], \array_keys($rules['keys']['workflows']['keys']));
        self::assertSame('map', $rules['keys']['workflows']['type']);
        self::assertTrue($rules['keys']['workflows']['required']);
        self::assertFalse($rules['keys']['workflows']['additionalKeys']);
        self::assertSame('map', $rules['keys']['workflows']['keys']['definitions']['type']);
        self::assertTrue($rules['keys']['workflows']['keys']['definitions']['required']);
        self::assertTrue($rules['keys']['workflows']['keys']['definitions']['additionalKeys']);
        self::assertSame(
            ['adaptive', 'json', 'table', 'plain'],
            $rules['keys']['output']['keys']['format_default']['allowedValues'],
        );
        self::assertSame(
            ['json', 'table', 'plain'],
            $rules['keys']['output']['keys']['adaptive']['keys']['interactive']['allowedValues'],
        );
        self::assertSame(
            ['json', 'table', 'plain'],
            $rules['keys']['output']['keys']['adaptive']['keys']['non_interactive']['allowedValues'],
        );
        self::assertSame(
            ['auto', 'always', 'never'],
            $rules['keys']['output']['keys']['color_default']['allowedValues'],
        );
        self::assertSame(40, $rules['keys']['output']['keys']['table']['keys']['max_width']['min']);
        self::assertSame(240, $rules['keys']['output']['keys']['table']['keys']['max_width']['max']);
        $result = new ConfigValidator()->validate(['cli' => $defaults], [ConfigRuleset::fromArray('cli', $rules)]);
        self::assertTrue($result->isSuccess());
        self::assertSame([], $result->violations());
    }

    public function testUnknownKeysAtEveryStrictLevelFailWhileOverrideEntriesRemainDynamic(): void
    {
        [$defaults, $rules] = self::configAndRules();
        $ruleset = ConfigRuleset::fromArray('cli', $rules);
        foreach (
            [
                [],
                ['commands'],
                ['workflows'],
                ['output'],
                ['output', 'adaptive'],
                ['output', 'table'],
            ] as $path
        ) {
            $modified = $defaults;
            $node = &$modified;
            foreach ($path as $segment) {
                $node = &$node[$segment];
            }
            $node['forbidden_extra'] = 'PRIVATE_PAYLOAD';
            unset($node);
            $result = new ConfigValidator()->validate(['cli' => $modified], [$ruleset]);
            self::assertTrue($result->isFailure(), \implode('.', $path));
            self::assertCount(1, $result->violations());
            self::assertSame('unknown-key', $result->violations()[0]->reason());
            self::assertStringNotContainsString('PRIVATE_PAYLOAD', $result->violations()[0]->reason());
        }
        $defaults['commands']['overrides']['owner:command'] = ['summary' => 'Catalog view'];
        self::assertTrue(new ConfigValidator()->validate(['cli' => $defaults], [$ruleset])->isSuccess());

        $defaults['workflows']['definitions']['verify'] = [
            'unexpected' => 'PRIVATE_WORKFLOW_PAYLOAD',
        ];
        // ConfigValidator checks the dynamic map boundary, not WorkflowSchema deep structure.
        $dynamic = new ConfigValidator()->validate(['cli' => $defaults], [$ruleset]);
        self::assertTrue($dynamic->isSuccess());
        self::assertSame([], $dynamic->violations());

        foreach ([[], ['definitions' => [], 'unexpected' => 'PRIVATE_PAYLOAD']] as $invalidWorkflows) {
            $modified = $defaults;
            $modified['workflows'] = $invalidWorkflows;
            $result = new ConfigValidator()->validate(['cli' => $modified], [$ruleset]);
            self::assertTrue($result->isFailure());
            self::assertNotEmpty($result->violations());
            foreach ($result->violations() as $violation) {
                self::assertStringNotContainsString('PRIVATE_PAYLOAD', $violation->reason());
            }
        }

        $modified = $defaults;
        $modified['workflows']['definitions'] = 'PRIVATE_PAYLOAD';
        $result = new ConfigValidator()->validate(['cli' => $modified], [$ruleset]);
        self::assertTrue($result->isFailure());
        foreach ($result->violations() as $violation) {
            self::assertStringNotContainsString('PRIVATE_PAYLOAD', $violation->reason());
        }
    }

    /** @param array<string, mixed> $defaults @param array<string, mixed> $rules */
    private static function assertExactKeys(array $defaults, array $rules): void
    {
        self::assertSame(\array_keys($defaults), \array_keys($rules));
        foreach ($rules as $key => $rule) {
            self::assertTrue($rule['required'], $key);
            if (\array_key_exists('keys', $rule)) {
                self::assertFalse($rule['additionalKeys'], $key);
                self::assertIsArray($defaults[$key]);
                self::assertExactKeys($defaults[$key], $rule['keys']);
            }
        }
    }

    /** @return array{array<string,mixed>,array<string,mixed>} */
    private static function configAndRules(): array
    {
        $root = \dirname(__DIR__, 2) . '/config/';
        $defaults = require $root . 'cli.php';
        $rules = require $root . 'rules.php';
        self::assertIsArray($defaults);
        self::assertIsArray($rules);
        return [$defaults, $rules];
    }
}
