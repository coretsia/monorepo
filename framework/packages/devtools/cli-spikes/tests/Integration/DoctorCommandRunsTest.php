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
use Coretsia\Devtools\CliSpikes\Command\DoctorCommand;
use PHPUnit\Framework\TestCase;

final class DoctorCommandRunsTest extends TestCase
{
    public function testDoctorCommandRunsAndEmitsSafeDeterministicJson(): void
    {
        $repoRoot = $this->repoRoot();
        self::assertFileExists($repoRoot . '/coretsia', 'Repo root coretsia launcher MUST exist.');
        self::assertFileExists(
            $repoRoot . '/framework/tools/spikes/_support/bootstrap.php',
            'Tools spikes bootstrap MUST exist.'
        );

        $payload = null;

        $this->withLauncherAtRepoRoot($repoRoot, function () use (&$payload): void {
            $cmd = new DoctorCommand();

            $input = $this->createStub(InputInterface::class);
            $input->method('tokens')->willReturn([]);

            $output = $this->createMock(OutputInterface::class);
            $output->expects(self::once())
                ->method('json')
                ->willReturnCallback(static function (array $data) use (&$payload): void {
                    $payload = $data;
                });

            $output->expects(self::never())->method('error');

            $exitCode = $cmd->run($input, $output);

            self::assertSame(0, $exitCode, 'doctor MUST succeed with exit code 0.');
        });

        self::assertIsArray($payload, 'doctor MUST emit json payload.');

        self::assertSame('doctor', $payload['command'] ?? null);
        self::assertSame(true, $payload['ok'] ?? null);

        $paths = $payload['paths'] ?? null;
        self::assertIsArray($paths, 'doctor payload MUST contain paths.');

        self::assertSame('.', $paths['repo_root'] ?? null, 'doctor MUST report repo_root as ".".');

        $this->assertPayloadDoesNotContainAbsolutePaths($payload);
    }

    private function repoRoot(): string
    {
        // tests/Integration -> tests -> cli-spikes -> devtools -> packages -> framework -> repo-root
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

    private function assertPayloadDoesNotContainAbsolutePaths(mixed $value): void
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                if (\is_string($k)) {
                    $this->assertStringIsSafe($k);
                }
                $this->assertPayloadDoesNotContainAbsolutePaths($v);
            }
            return;
        }

        if (\is_string($value)) {
            $this->assertStringIsSafe($value);
        }
    }

    private function assertStringIsSafe(string $s): void
    {
        self::assertFalse(\str_contains($s, '\\'), 'Payload MUST NOT contain backslashes: ' . $s);
        self::assertFalse(\str_starts_with($s, '/'), 'Payload MUST NOT contain absolute POSIX paths: ' . $s);

        self::assertSame(
            0,
            \preg_match('/(?i)\b[A-Z]:[\\\\\/]/', $s),
            'Payload MUST NOT contain Windows drive-letter rooted paths: ' . $s
        );

        self::assertFalse(\str_starts_with($s, '\\\\'), 'Payload MUST NOT contain UNC paths: ' . $s);
    }
}
