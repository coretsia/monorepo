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
use Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncApplyCommand;
use PHPUnit\Framework\TestCase;

final class WorkspaceSyncApplyCommandTest extends TestCase
{
    public function testWorkspaceSyncApplyRunsAndDoesNotMutateFrameworkComposerJson(): void
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

        $frameworkComposer = $repoRoot . '/framework/composer.json';
        self::assertFileExists($frameworkComposer, 'framework/composer.json MUST exist.');

        $before = \file_get_contents($frameworkComposer);
        self::assertIsString($before, 'Must be able to read framework/composer.json.');

        $payload = null;

        $this->withLauncherAtRepoRoot($repoRoot, function () use (&$payload): void {
            $cmd = new WorkspaceSyncApplyCommand();

            $input = $this->createStub(InputInterface::class);
            $input->method('tokens')->willReturn(['--apply']);

            $output = $this->createMock(OutputInterface::class);
            $output->expects(self::once())
                ->method('json')
                ->willReturnCallback(static function (array $data) use (&$payload): void {
                    $payload = $data;
                });
            $output->expects(self::never())->method('error');

            $exit = $cmd->run($input, $output);

            self::assertSame(0, $exit, 'workspace:sync (apply) MUST succeed with exit code 0.');
        });

        $after = \file_get_contents($frameworkComposer);
        self::assertIsString($after);

        self::assertSame(
            $before,
            $after,
            'workspace:sync apply MUST NOT mutate framework/composer.json during package tests.'
        );

        self::assertIsArray($payload);
        self::assertSame('workspace:sync', $payload['command'] ?? null);
        self::assertSame('apply', $payload['mode'] ?? null);

        $result = $payload['result'] ?? null;
        self::assertIsArray($result, 'workspace:sync apply MUST return structured result.');

        $this->assertPayloadDoesNotContainAbsolutePaths($payload);
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
