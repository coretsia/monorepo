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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Psr\Log\LoggerInterface;

final class CliDefaultMicroCreateProjectBootTest extends CliIntegrationTestCase
{
    public function testShippedSkeletonBaselineSelectsMicroAndBootsWithoutGeneratedArtifacts(): void
    {
        $skeleton = \json_decode(
            (string) \file_get_contents(self::repositoryRoot() . '/packages/applications/skeleton/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $framework = \json_decode(
            (string) \file_get_contents(self::repositoryRoot() . '/packages/framework/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $cli = \json_decode(
            (string) \file_get_contents(self::packageRoot() . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertArrayHasKey('coretsia/framework', $skeleton['require']);
        self::assertArrayHasKey('coretsia/platform-cli', $framework['require']);
        self::assertArrayHasKey('coretsia/core-kernel', $framework['require']);
        self::assertArrayHasKey('coretsia/platform-logging', $framework['require']);
        self::assertArrayHasKey('coretsia/platform-redaction', $cli['require']);
        self::assertArrayHasKey('coretsia/platform-errors', $cli['require']);
        $errors = \json_decode(
            (string) \file_get_contents(self::repositoryRoot() . '/packages/platform/errors/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertArrayHasKey('coretsia/platform-redaction', $errors['require']);
        $logging = \json_decode(
            (string) \file_get_contents(self::repositoryRoot() . '/packages/platform/logging/composer.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
        self::assertArrayHasKey('coretsia/platform-redaction', $logging['require']);
        self::assertSame(
            'micro',
            (require self::repositoryRoot() . '/packages/core/kernel/config/kernel.php')['boot']['default_preset'],
        );
        $micro = require self::repositoryRoot() . '/packages/core/kernel/resources/modes/micro.php';
        self::assertContains('platform.cli', $micro['modules']);
        self::assertContains('platform.logging', $micro['modules']);
        self::assertNotContains('platform.logging', $micro['required']);
        self::assertNotContains('platform.errors', $micro['modules']);
        self::assertNotContains('platform.errors', $micro['required']);

        $root = self::consumer();
        try {
            self::assertFileDoesNotExist($root . '/config/app.php');
            self::assertNoArtifacts($root);
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $plan = $host->get(ModulePlan::class);
            self::assertSame('console', $plan->app());
            foreach (
                [
                    'core.kernel',
                    'platform.cli',
                    'platform.errors',
                    'platform.logging',
                    'platform.redaction',
                ] as $required
            ) {
                self::assertTrue($plan->hasEnabledModule($required), $required);
            }
            self::assertInstanceOf(StreamLogger::class, $host->get(LoggerInterface::class));
            self::assertSame('jsonl', $host->get(ConfigRepositoryInterface::class)->get('logging.format'));

            $result = self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']);
            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame('coretsia.cli-output@1', self::jsonRecord($result['stdout'])['schema']);

            $records = self::jsonlDiagnostics($result['stderr']);
            self::assertNotEmpty($records);

            $commands = \array_values(
                \array_filter(
                    $records,
                    static fn (array $record): bool => $record['message'] === 'cli.command',
                ),
            );

            self::assertCount(1, $commands);
            self::assertSame('info', $commands[0]['level']);
            self::assertSame('list', $commands[0]['context']['operation']);
            self::assertSame('success', $commands[0]['context']['outcome']);
            self::assertSame(0, $commands[0]['context']['exit_code']);

            foreach ([$root, 'Warning', 'Fatal error', 'Stack trace', 'PRIVATE_', 'password'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $result['stderr']);
            }

            $doctor = self::execute($root . '/vendor/bin/coretsia', ['doctor']);
            self::assertSame(0, $doctor['exit'], $doctor['stderr']);
            self::assertSame('', $doctor['stderr']);
            self::assertStringContainsString('Status: READY', $doctor['stdout']);
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testConsoleLoggingExclusionPreservesComposerAndLogfmtChangesOnlyDiagnostics(): void
    {
        $root = self::consumer();

        try {
            $composer = (string) \file_get_contents($root . '/composer.json');
            $jsonl = self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']);
            self::assertSame(0, $jsonl['exit'], $jsonl['stderr']);
            $jsonlRecords = self::jsonlDiagnostics($jsonl['stderr']);

            self::write($root . '/config/logging.php', "<?php return ['format' => 'logfmt'];\n");

            $logfmt = self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']);
            self::assertSame($jsonl['exit'], $logfmt['exit']);
            self::assertSame($jsonl['stdout'], $logfmt['stdout']);
            $logfmtRecords = self::logfmtDiagnostics($logfmt['stderr']);
            self::assertEquivalentDiagnosticRecords($jsonlRecords, $logfmtRecords);

            self::write(
                $root . '/config/app.php',
                <<<'PHP'
<?php
return ['moduleOverrides' => ['console' => ['include' => [], 'exclude' => ['platform.logging']]]];
PHP,
            );

            $excludedHost = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            self::assertFalse($excludedHost->get(ModulePlan::class)->hasEnabledModule('platform.logging'));
            self::assertInstanceOf(NoopLogger::class, $excludedHost->get(LoggerInterface::class));

            $excluded = self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']);
            self::assertSame(0, $excluded['exit']);
            self::assertSame($jsonl['stdout'], $excluded['stdout']);
            self::assertSame('', $excluded['stderr']);
            self::assertSame($composer, \file_get_contents($root . '/composer.json'));
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testHostImplementationDoesNotInvokeProjectDependencySyncOrArtifactRuntimeBoot(): void
    {
        $source = (string) \file_get_contents(
            self::repositoryRoot() . '/packages/core/kernel/src/Ops/KernelOpsHostBooter.php',
        );
        foreach (
            [
                'ProjectDependencySync',
                'ArtifactRuntimeBooter',
                'ModulePlanResolver()->resolve(',
                'compileArtifacts(',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        self::assertStringContainsString('$moduleResolutionOrchestrator->resolve($consoleBootstrapConfig)', $source);
    }
}
