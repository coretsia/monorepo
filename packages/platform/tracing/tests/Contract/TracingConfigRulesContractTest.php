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

namespace Coretsia\Platform\Tracing\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\Tracing\Module\TracingModule;
use PHPUnit\Framework\TestCase;

final class TracingConfigRulesContractTest extends TestCase
{
    public function testDefaultsAndRulesAreExactlyEmptyAndDeclarative(): void
    {
        $root = \dirname(__DIR__, 2);
        $defaults = require $root . '/config/tracing.php';
        $rules = require $root . '/config/rules.php';

        self::assertSame([], $defaults);
        self::assertSame([
            'schemaVersion' => 1,
            'configRoot' => 'tracing',
            'additionalKeys' => false,
            'keys' => [],
        ], $rules);
        self::assertSame('tracing', TracingModule::CONFIG_ROOT);
        self::assertSame('tracing', new TracingModule()->configRoot());
    }

    public function testKernelValidatorRejectsAllUnknownTracingKeys(): void
    {
        $rules = require \dirname(__DIR__, 2) . '/config/rules.php';
        $ruleset = ConfigRuleset::fromArray('tracing', $rules);
        $validator = new ConfigValidator();

        self::assertTrue($validator->validate(['tracing' => []], [$ruleset])->isSuccess());

        foreach (
            [
                ['propagation' => ['mode' => 'w3c']],
                ['propagation.mode' => 'noop'],
                ['enabled' => false],
                ['sampler' => 'always'],
                ['exporter' => 'none'],
                ['context_store' => true],
            ] as $invalid
        ) {
            $result = $validator->validate(['tracing' => $invalid], [$ruleset]);

            self::assertTrue($result->isFailure());
            self::assertCount(1, $result->violations());
            self::assertSame('unknown-key', $result->violations()[0]->reason());
        }

        self::assertTrue($validator->validate([], [$ruleset])->isFailure());
    }

    public function testPackageManifestAndCanonicalRegistryAgreeOnConfigRootOwner(): void
    {
        $root = \dirname(__DIR__, 2);
        $repository = \dirname(__DIR__, 5);
        $manifest = \json_decode(
            (string) \file_get_contents($root . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $registration = (string) \file_get_contents($repository . '/docs/ssot/config-roots.md');

        self::assertSame('config/tracing.php', $manifest['extra']['coretsia']['defaultsConfigPath']);
        self::assertSame('platform.tracing', $manifest['extra']['coretsia']['moduleId']);
        self::assertSame(['core.foundation'], $manifest['extra']['coretsia']['requires']);
        self::assertFileExists($root . '/' . $manifest['extra']['coretsia']['defaultsConfigPath']);
        self::assertSame(
            1,
            \preg_match_all(
                '/^\|\s*`tracing`\s*\|\s*`platform\/tracing`\s*\|'
                . '\s*`packages\/platform\/tracing\/config\/tracing\.php`\s*\|'
                . '\s*`packages\/platform\/tracing\/config\/rules\.php`\s*\|/m',
                $registration,
            ),
        );
    }

    public function testNoRuntimeSelectorOrPackageLocalConfigFactoryExists(): void
    {
        $root = \dirname(__DIR__, 2);
        $rules = require $root . '/config/rules.php';
        $defaults = require $root . '/config/tracing.php';

        self::assertSame([], $rules['keys']);
        self::assertFalse($rules['additionalKeys']);
        self::assertSame([], $defaults);
        $sourceFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($sourceFiles as $file) {
            if ($file->isFile() && \str_ends_with($file->getFilename(), 'Factory.php')) {
                self::fail('Tracing must not introduce a runtime or configuration factory.');
            }
        }
    }
}
