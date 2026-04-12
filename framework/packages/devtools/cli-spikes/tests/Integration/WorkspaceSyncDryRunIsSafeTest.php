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
use Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncDryRunCommand;
use PHPUnit\Framework\TestCase;

final class WorkspaceSyncDryRunIsSafeTest extends TestCase
{
    public function testWorkspaceSyncDryRunIsDeterministicAndDoesNotLeakAbsolutePaths(): void
    {
        $repoRoot = $this->repoRoot();

        self::assertFileExists($repoRoot . '/coretsia', 'Repo root coretsia launcher MUST exist.');
        self::assertFileExists(
            $repoRoot . '/framework/tools/spikes/_support/bootstrap.php',
            'Tools spikes bootstrap MUST exist.'
        );

        self::assertTrue(
            \class_exists('Coretsia\\Tools\\Spikes\\workspace\\WorkspaceSyncWorkflow'),
            'Workspace sync spike MUST exist (prerequisite 0.100.0).'
        );

        $payload1 = null;
        $payload2 = null;

        $this->withLauncherAtRepoRoot($repoRoot, function () use (&$payload1, &$payload2): void {
            $cmd = new WorkspaceSyncDryRunCommand();

            $input = $this->createStub(InputInterface::class);
            $input->method('tokens')->willReturn(['--format=json']);

            $output1 = $this->createMock(OutputInterface::class);
            $output1->expects(self::once())
                ->method('json')
                ->willReturnCallback(static function (array $data) use (&$payload1): void {
                    $payload1 = $data;
                });
            $output1->expects(self::never())->method('error');

            $exit1 = $cmd->run($input, $output1);
            self::assertSame(0, $exit1, 'workspace:sync --dry-run MUST succeed.');

            $output2 = $this->createMock(OutputInterface::class);
            $output2->expects(self::once())
                ->method('json')
                ->willReturnCallback(static function (array $data) use (&$payload2): void {
                    $payload2 = $data;
                });
            $output2->expects(self::never())->method('error');

            $exit2 = $cmd->run($input, $output2);
            self::assertSame(0, $exit2, 'workspace:sync --dry-run MUST be rerunnable and succeed.');
        });

        self::assertIsArray($payload1);
        self::assertIsArray($payload2);

        self::assertSame($payload1, $payload2, 'workspace:sync dry-run payload MUST be deterministic across two runs.');

        self::assertSame('workspace:sync', $payload1['command'] ?? null);
        self::assertSame('dry-run', $payload1['mode'] ?? null);

        $target = $payload1['target'] ?? null;
        self::assertIsArray($target);
        self::assertSame('repo', $target['type'] ?? null);

        self::assertIsArray($payload1['changedPaths'] ?? null, 'changedPaths MUST be present (list).');
        self::assertIsArray($payload1['files'] ?? null, 'files MUST be present (list).');

        $this->assertPayloadDoesNotContainAbsolutePaths($payload1);

        $json = \json_encode($payload1, \JSON_UNESCAPED_SLASHES);
        self::assertIsString($json);

        self::assertFalse(\str_contains($json, 'Authorization'), 'Payload MUST NOT leak Authorization.');
        self::assertFalse(\str_contains($json, 'password'), 'Payload MUST NOT leak password.');
        self::assertFalse(\str_contains($json, 'dotenv'), 'Payload MUST NOT leak dotenv.');
        self::assertFalse(\str_contains($json, 'php://stdout'), 'Payload MUST NOT reference stdout sink.');
        self::assertFalse(\str_contains($json, 'php://stderr'), 'Payload MUST NOT reference stderr sink.');
        self::assertFalse(\str_contains($json, 'php://output'), 'Payload MUST NOT reference output sink.');
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
        self::assertSame(0, \preg_match('/(?i)\b[A-Z]:[\\\\\/]/', $s), 'Payload MUST NOT contain drive-letter paths: ' . $s);
        self::assertFalse(\str_starts_with($s, '\\\\'), 'Payload MUST NOT contain UNC paths: ' . $s);
    }
}
