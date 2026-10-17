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

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Cli\Bootstrap\UltraEarlyDoctorRunner;
use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class UltraEarlyDoctorUsesSafeFixedOutputPipelineTest extends CliIntegrationTestCase
{
    public function testDoctorUsesFixedPlainPipelineAndNeverLeaksEnvironmentSentinels(): void
    {
        $root = self::consumer();
        try {
            $secret = 'CORETSIA_PRIVATE_' . \bin2hex(\random_bytes(8));
            $result = self::execute($root . '/vendor/bin/coretsia', ['doctor'], null, [
                'CORETSIA_INJECTED_SECRET' => $secret,
            ]);
            self::assertSame(0, $result['exit']);
            self::assertSame('', $result['stderr']);
            $record = \json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR);
            self::assertSame([
                'cli_sapi' => true,
                'json_available' => true,
                'pcre_available' => true,
                'php_supported' => true,
                'ready' => true,
            ], $record);
            foreach (
                [
                    $secret,
                    $root,
                    'Stack trace',
                    'phpinfo()',
                    'Loaded Extensions',
                    "\x1B",
                    'argv',
                    'Throwable',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $result['stdout']);
            }
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testUnsupportedDoctorTokensUseFixedSafeErrorPath(): void
    {
        $root = self::consumer();
        try {
            foreach ([['doctor', '--format=json'], ['doctor', 'PRIVATE_ARG'], ['doctor', '--target=web']] as $args) {
                $result = self::execute($root . '/vendor/bin/coretsia', $args);
                self::assertSafeFailure($result, 'CORETSIA_CLI_INPUT_INVALID: cli-argument-count-invalid');
                self::assertStringNotContainsString('PRIVATE_ARG', $result['stderr']);
            }
        } finally {
            self::removeTree($root);
        }
    }

    public function testUltraEarlyRunnerUsesTheIdenticalDependencyFreeDoctorCommand(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(new CliServiceProvider());
        self::assertContains(
            DoctorCommand::class,
            \array_map(static fn ($tag): string => $tag->id(), $builder->tagRegistry()->all(ReservedTags::CLI_COMMAND)),
        );
        $output = new CommandOutputBuffer();
        $code = new DoctorCommand()->run(
            new \Coretsia\Platform\Cli\Input\ArgvInput(['doctor'], 'doctor', [], []),
            $output,
        );
        self::assertSame(0, $code);
        self::assertSame(
            ['cli_sapi', 'json_available', 'pcre_available', 'php_supported', 'ready'],
            \array_keys($output->finalize()->records()[0]['payload'])
        );
        $source = (string) \file_get_contents(new \ReflectionClass(UltraEarlyDoctorRunner::class)->getFileName());
        self::assertStringContainsString('new DoctorCommand()->run($input, $buffer)', $source);
        self::assertStringContainsString('new PlainFormatter(new AnsiDecorator())->format($batch, false)', $source);
        self::assertStringContainsString('$writer->write(', $source);
        self::assertStringNotContainsString('KernelOpsHostBooter', $source);
        self::assertStringNotContainsString('SensitiveDataRedactorInterface', $source);
        self::assertSame(
            ConsoleOutputWriter::class,
            new \ReflectionMethod(UltraEarlyDoctorRunner::class, 'run')->getParameters()[1]->getType()?->getName(),
        );
    }
}
