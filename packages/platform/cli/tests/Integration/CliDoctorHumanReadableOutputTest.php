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

use Coretsia\Platform\Cli\Bootstrap\UltraEarlyDoctorRunner;
use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CliDoctorHumanReadableOutputTest extends CliIntegrationTestCase
{
    public function testExactDoctorInvocationUsesFixedHumanReadablePresentationAndPreservesCommandExitCode(): void
    {
        $root = self::consumer();

        try {
            $output = new CommandOutputBuffer();
            $expectedCode = new DoctorCommand()->run(new ArgvInput(['doctor'], 'doctor', [], []), $output);
            $record = $output->finalize()->records();
            self::assertCount(1, $record);
            self::assertSame('json', $record[0]['type']);

            $result = self::execute($root . '/vendor/bin/coretsia', ['doctor']);

            self::assertSame($expectedCode, $result['exit']);
            self::assertSame('', $result['stderr']);
            self::assertSame(
                \implode("\n", [
                    'Coretsia CLI — Runtime diagnostics',
                    '',
                    'PHP >= 8.4:     OK',
                    'CLI SAPI:       OK',
                    'JSON extension: OK',
                    'PCRE extension: OK',
                    '',
                    'Status: READY',
                ]) . "\n",
                $result['stdout'],
            );
            self::assertStringNotContainsString("\x1B", $result['stdout']);
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testFalseCapabilitiesAndFalseReadinessAreProjectedWithoutRuntimeMutation(): void
    {
        $projection = new \ReflectionMethod(UltraEarlyDoctorRunner::class, 'projectDiagnostics');
        $payload = [
            'cli_sapi' => false,
            'json_available' => false,
            'pcre_available' => false,
            'php_supported' => false,
            'ready' => false,
        ];

        self::assertSame(\implode("\n", [
            'Coretsia CLI — Runtime diagnostics',
            '',
            'PHP >= 8.4:     FAIL',
            'CLI SAPI:       FAIL',
            'JSON extension: FAIL',
            'PCRE extension: FAIL',
            '',
            'Status: NOT READY',
        ]), $projection->invoke(null, $payload));

        $payload['cli_sapi'] = true;
        $payload['json_available'] = true;
        $payload['pcre_available'] = true;
        $payload['php_supported'] = true;
        $readiness = $projection->invoke(null, $payload);
        self::assertIsString($readiness);
        self::assertStringContainsString('Status: NOT READY', $readiness);
        self::assertStringNotContainsString('FAIL', $readiness);
    }

    public function testDoctorNeedsNoKernelHostCatalogArtifactsOrSharedRedactor(): void
    {
        $root = self::consumer();

        try {
            self::write($root . '/config/app.php', '<?php throw new \\RuntimeException("PRIVATE_HOST_SENTINEL");');
            $result = self::execute($root . '/vendor/bin/coretsia', ['doctor']);
            self::assertSame(0, $result['exit']);
            self::assertSame('', $result['stderr']);
            self::assertStringContainsString('Status: READY', $result['stdout']);
            self::assertStringNotContainsString('PRIVATE_HOST_SENTINEL', $result['stdout']);
            self::assertNoArtifacts($root);

            $sourcePath = new \ReflectionClass(UltraEarlyDoctorRunner::class)->getFileName();
            self::assertIsString($sourcePath);
            $source = \file_get_contents($sourcePath);
            self::assertIsString($source);
            foreach (
                [
                    'CommandCatalog',
                    'KernelOpsHostBooter',
                    'SensitiveDataRedactorInterface',
                    'OutputFormatter',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        } finally {
            self::removeTree($root);
        }
    }

    public function testDoctorRejectsAdditionalArgumentsAndOptionsThroughFixedBoundary(): void
    {
        $root = self::consumer();

        try {
            foreach (
                [
                    ['doctor', 'PRIVATE_ARGUMENT'],
                    ['doctor', '--format=json'],
                    ['doctor', '--target=web'],
                ] as $tokens
            ) {
                $result = self::execute($root . '/vendor/bin/coretsia', $tokens);
                self::assertSafeFailure($result, 'CORETSIA_CLI_INPUT_INVALID: cli-argument-count-invalid');
                self::assertStringNotContainsString('PRIVATE_ARGUMENT', $result['stderr']);
                self::assertNoArtifacts($root);
            }
        } finally {
            self::removeTree($root);
        }
    }
}
