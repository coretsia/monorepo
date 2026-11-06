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

namespace Coretsia\Platform\ProblemDetails\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\ProblemDetails\Module\ProblemDetailsModule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsConfigRulesContractTest extends TestCase
{
    public function testDefaultsAndRulesHaveTheExactStrictEmptyBaseline(): void
    {
        $root = \dirname(__DIR__, 2);
        self::assertFileExists($root . '/config/problem_details.php');
        self::assertFileExists($root . '/config/rules.php');
        self::assertSame([], require $root . '/config/problem_details.php');
        self::assertSame([
            'schemaVersion' => 1,
            'configRoot' => 'problem_details',
            'additionalKeys' => false,
            'keys' => [],
        ], require $root . '/config/rules.php');
        self::assertFileDoesNotExist($root . '/config/problem-details.php');

        $ruleset = self::ruleset();
        self::assertSame('problem_details', $ruleset->root());
        self::assertSame(1, $ruleset->schemaVersion());
        $result = new ConfigValidator()->validate(['problem_details' => []], [$ruleset]);
        self::assertTrue($result->isSuccess());
        self::assertSame([], $result->violations());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenKeys(): iterable
    {
        yield 'debug' => ['debug'];
        yield 'show detail' => ['show_detail'];
        yield 'local detail' => ['show_detail_in_local'];
        yield 'detail' => ['detail'];
        yield 'instance' => ['instance'];
        yield 'extensions' => ['extensions'];
        yield 'trace' => ['stacktrace'];
        yield 'renderer' => ['renderer'];
        yield 'renderer selection' => ['renderer_type'];
        yield 'exception mapping' => ['exception_mapper'];
        yield 'redaction override' => ['redaction'];
    }

    #[DataProvider('forbiddenKeys')]
    public function testCanonicalKernelValidatorRejectsAnyUndeclaredDisclosureOrRendererKey(string $key): void
    {
        $config = ['problem_details' => [$key => 'SYNTHETIC_PRIVATE']];
        $result = new ConfigValidator()->validate($config, [self::ruleset()]);

        self::assertTrue($result->isFailure());
        self::assertCount(1, $result->violations());
        $violation = $result->violations()[0];
        self::assertSame('problem_details', $violation->root());
        self::assertSame($key, $violation->path());
        self::assertSame('unknown-key', $violation->reason());
        self::assertSame('declared-key', $violation->expected());
        self::assertStringNotContainsString(
            'SYNTHETIC_PRIVATE',
            \json_encode($result->toArray(), \JSON_THROW_ON_ERROR),
        );
    }

    public function testCanonicalConfigPathRootAndOwnershipRegistryAgree(): void
    {
        $root = \dirname(__DIR__, 2);
        $repoRoot = \dirname(__DIR__, 5);
        $composer = \json_decode(
            (string) \file_get_contents($root . '/composer.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
        $module = new ProblemDetailsModule();
        $catalog = \file_get_contents($repoRoot . '/docs/ssot/config-roots.md');

        self::assertIsString($catalog);
        self::assertSame('problem_details', $module->configRoot());
        self::assertSame('problem_details', self::ruleset()->root());
        self::assertSame('config/problem_details.php', $composer['extra']['coretsia']['defaultsConfigPath']);
        self::assertSame('platform.problem-details', $composer['extra']['coretsia']['moduleId']);
        self::assertSame('platform/problem-details', $module->packageId());
        self::assertSame(
            1,
            \substr_count(
                $catalog,
                '| `problem_details` | `platform/problem-details` | '
                . '`packages/platform/problem-details/config/problem_details.php` | '
                . '`packages/platform/problem-details/config/rules.php` |',
            ),
        );
    }

    private static function ruleset(): ConfigRuleset
    {
        $rules = require \dirname(__DIR__, 2) . '/config/rules.php';
        self::assertIsArray($rules);

        return ConfigRuleset::fromArray('problem_details', $rules);
    }
}
