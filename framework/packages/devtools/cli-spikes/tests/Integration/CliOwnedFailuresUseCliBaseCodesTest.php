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

namespace Coretsia\Devtools\CliSpikes\Tests\Integration;

use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Devtools\CliSpikes\Command\DeptracGraphCommand;
use Coretsia\Devtools\CliSpikes\Command\DoctorCommand;
use Coretsia\Devtools\CliSpikes\Command\SpikeConfigDebugCommand;
use Coretsia\Devtools\CliSpikes\Command\SpikeFingerprintCommand;
use Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncApplyCommand;
use Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncDryRunCommand;
use Coretsia\Platform\Cli\Error\ErrorCodes as CliErrorCodes;
use PHPUnit\Framework\TestCase;

final class CliOwnedFailuresUseCliBaseCodesTest extends TestCase
{
    public function testDoctorBootstrapFailureUsesCliCommandFailed(): void
    {
        $cmd = new DoctorCommand();
        $input = $this->stubInput([]);
        $output = new CliOwnedFailuresRecordingOutput();

        $this->withNoLauncherGlobals(function () use ($cmd, $input, $output): void {
            $exit = $cmd->run($input, $output);

            self::assertSame(1, $exit, 'doctor MUST fail when launcher path cannot be resolved.');
        });

        $this->assertSingleError(
            $output,
            CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED,
            'launcher-path-unresolvable'
        );
    }

    public function testSpikeFingerprintBootstrapFailureUsesCliCommandFailed(): void
    {
        $cmd = new SpikeFingerprintCommand();
        $input = $this->stubInput([]);
        $output = new CliOwnedFailuresRecordingOutput();

        $this->withNoLauncherGlobals(function () use ($cmd, $input, $output): void {
            $exit = $cmd->run($input, $output);

            self::assertSame(1, $exit, 'spike:fingerprint MUST fail when launcher path cannot be resolved.');
        });

        $this->assertSingleError(
            $output,
            CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED,
            'launcher-path-unresolvable'
        );
    }

    public function testDeptracGraphInvalidFixtureUsesCliCommandInvalid(): void
    {
        $repoRoot = $this->repoRoot();

        self::assertFileExists($repoRoot . '/coretsia', 'Repo root coretsia launcher MUST exist.');
        self::assertFileExists(
            $repoRoot . '/framework/tools/spikes/_support/bootstrap.php',
            'Tools spikes bootstrap MUST exist.'
        );

        $cmd = new DeptracGraphCommand();
        $input = $this->stubInput([
            '--json',
            '--fixture=not_allowed/bad.php',
            '--out=framework/tools/spikes/_artifacts/_test_deptrac_invalid_fixture',
        ]);
        $output = new CliOwnedFailuresRecordingOutput();

        $this->withLauncherAtRepoRoot($repoRoot, function () use ($cmd, $input, $output): void {
            $exit = $cmd->run($input, $output);

            self::assertSame(1, $exit, 'deptrac:graph MUST fail for invalid fixture path.');
        });

        $this->assertSingleError(
            $output,
            CliErrorCodes::CORETSIA_CLI_COMMAND_INVALID,
            'fixture-must-be-under-deptrac-min'
        );
    }

    public function testSpikeConfigDebugMissingKeyUsesCliCommandInvalid(): void
    {
        $cmd = new SpikeConfigDebugCommand();
        $input = $this->stubInput([]);
        $output = new CliOwnedFailuresRecordingOutput();

        $exit = $cmd->run($input, $output);

        self::assertSame(1, $exit, 'spike:config:debug MUST fail when --key is missing.');

        $this->assertSingleError(
            $output,
            CliErrorCodes::CORETSIA_CLI_COMMAND_INVALID,
            'missing-required-flag-key'
        );
    }

    public function testSpikeConfigDebugMissingScenarioFixtureUsesCliConfigInvalid(): void
    {
        $repoRoot = $this->repoRoot();

        self::assertFileExists($repoRoot . '/coretsia', 'Repo root coretsia launcher MUST exist.');
        self::assertFileExists(
            $repoRoot . '/framework/tools/spikes/_support/bootstrap.php',
            'Tools spikes bootstrap MUST exist.'
        );

        $fixtureAbs = $repoRoot . '/framework/tools/spikes/config_merge/tests/fixtures/scenarios.php';
        $fixtureBak = $fixtureAbs . '.bak_cli_owned_failures_test';

        self::assertFileExists($fixtureAbs, 'Config merge scenarios fixture MUST exist before test.');
        self::assertFalse(\is_file($fixtureBak), 'Temporary backup fixture path MUST NOT already exist.');

        self::assertTrue(
            @\rename($fixtureAbs, $fixtureBak),
            'Test setup failed: cannot rename scenarios fixture.'
        );

        \clearstatcache(true, $fixtureAbs);
        \clearstatcache(true, $fixtureBak);

        try {
            $cmd = new SpikeConfigDebugCommand();
            $input = $this->stubInput(['--key=cli.commands']);
            $output = new CliOwnedFailuresRecordingOutput();

            $this->withLauncherAtRepoRoot($repoRoot, function () use ($cmd, $input, $output): void {
                $exit = $cmd->run($input, $output);

                self::assertSame(1, $exit, 'spike:config:debug MUST fail when scenarios fixture is missing.');
            });

            $this->assertSingleError(
                $output,
                CliErrorCodes::CORETSIA_CLI_CONFIG_INVALID,
                'config-merge-scenarios-missing'
            );
        } finally {
            if (\is_file($fixtureBak)) {
                @\rename($fixtureBak, $fixtureAbs);
                \clearstatcache(true, $fixtureAbs);
                \clearstatcache(true, $fixtureBak);
            }
        }
    }

    public function testWorkspaceSyncDryRunInvalidFormatUsesCliCommandInvalid(): void
    {
        $cmd = new WorkspaceSyncDryRunCommand();
        $input = $this->stubInput(['--format=xml']);
        $output = new CliOwnedFailuresRecordingOutput();

        $exit = $cmd->run($input, $output);

        self::assertSame(1, $exit, 'workspace:sync --dry-run MUST fail for invalid format.');

        $this->assertSingleError(
            $output,
            CliErrorCodes::CORETSIA_CLI_COMMAND_INVALID,
            'invalid-arguments'
        );
    }

    public function testWorkspaceSyncApplyBootstrapFailureUsesCliCommandFailed(): void
    {
        $cmd = new WorkspaceSyncApplyCommand();
        $input = $this->stubInput(['--apply']);
        $output = new CliOwnedFailuresRecordingOutput();

        $this->withNoLauncherGlobals(function () use ($cmd, $input, $output): void {
            $exit = $cmd->run($input, $output);

            self::assertSame(1, $exit, 'workspace:sync apply MUST fail when launcher path cannot be resolved.');
        });

        $this->assertSingleError(
            $output,
            CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED,
            'launcher-path-unresolvable'
        );
    }

    /**
     * @param list<string> $tokens
     */
    private function stubInput(array $tokens): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('tokens')->willReturn($tokens);

        return $input;
    }

    private function assertSingleError(
        CliOwnedFailuresRecordingOutput $output,
        string $expectedCode,
        string $expectedMessage
    ): void {
        $errors = $output->errors();

        self::assertCount(1, $errors, 'Exactly one error() call is expected.');
        self::assertSame($expectedCode, $errors[0]['code']);
        self::assertSame($expectedMessage, $errors[0]['message']);

        self::assertSame([], $output->jsonPayloads(), 'Failure path MUST NOT emit json payload.');
    }

    private function repoRoot(): string
    {
        $repoRoot = \dirname(__DIR__, 6);

        return \rtrim(\str_replace('\\', '/', $repoRoot), '/');
    }

    /**
     * @param callable():void $fn
     */
    private function withLauncherAtRepoRoot(string $repoRoot, callable $fn): void
    {
        $launcher = $repoRoot . '/coretsia';
        $launcherReal = \realpath($launcher);

        self::assertNotFalse($launcherReal, 'coretsia launcher MUST be realpath-resolvable.');

        $prevScriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
        $prevServerArgv = $_SERVER['argv'] ?? null;
        $prevGlobalArgv = $GLOBALS['argv'] ?? null;

        $_SERVER['SCRIPT_FILENAME'] = (string)$launcherReal;

        if (!\is_array($_SERVER['argv'] ?? null)) {
            $_SERVER['argv'] = [];
        }
        $_SERVER['argv'][0] = (string)$launcherReal;

        if (!\is_array($GLOBALS['argv'] ?? null)) {
            $GLOBALS['argv'] = [];
        }
        $GLOBALS['argv'][0] = (string)$launcherReal;

        try {
            $fn();
        } finally {
            if ($prevScriptFilename === null) {
                unset($_SERVER['SCRIPT_FILENAME']);
            } else {
                $_SERVER['SCRIPT_FILENAME'] = $prevScriptFilename;
            }

            if ($prevServerArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $prevServerArgv;
            }

            if ($prevGlobalArgv === null) {
                unset($GLOBALS['argv']);
            } else {
                $GLOBALS['argv'] = $prevGlobalArgv;
            }
        }
    }

    /**
     * @param callable():void $fn
     */
    private function withNoLauncherGlobals(callable $fn): void
    {
        $prevScriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
        $prevServerArgv = $_SERVER['argv'] ?? null;
        $prevGlobalArgv = $GLOBALS['argv'] ?? null;

        unset($_SERVER['SCRIPT_FILENAME']);
        unset($_SERVER['argv']);
        unset($GLOBALS['argv']);

        try {
            $fn();
        } finally {
            if ($prevScriptFilename === null) {
                unset($_SERVER['SCRIPT_FILENAME']);
            } else {
                $_SERVER['SCRIPT_FILENAME'] = $prevScriptFilename;
            }

            if ($prevServerArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $prevServerArgv;
            }

            if ($prevGlobalArgv === null) {
                unset($GLOBALS['argv']);
            } else {
                $GLOBALS['argv'] = $prevGlobalArgv;
            }
        }
    }
}

/**
 * Minimal recording OutputInterface for failure-code assertions.
 */
final class CliOwnedFailuresRecordingOutput implements OutputInterface
{
    /** @var list<array<string, mixed>> */
    private array $json = [];

    /** @var list<string> */
    private array $text = [];

    /** @var list<array{code:string,message:string}> */
    private array $errors = [];

    public function json(array $data): void
    {
        $this->json[] = $data;
    }

    public function text(string $line): void
    {
        $this->text[] = $line;
    }

    public function error(string $code, string $message): void
    {
        $this->errors[] = [
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jsonPayloads(): array
    {
        return $this->json;
    }

    /**
     * @return list<string>
     */
    public function textLines(): array
    {
        return $this->text;
    }

    /**
     * @return list<array{code:string,message:string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
