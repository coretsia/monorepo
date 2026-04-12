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
use Coretsia\Devtools\CliSpikes\Command\SpikeFingerprintCommand;
use PHPUnit\Framework\TestCase;

final class SpikeFingerprintGoldenOkTest extends TestCase
{
    public function testSpikeFingerprintIsDeterministicAndSafeAcrossTwoRuns(): void
    {
        $repoRoot = $this->repoRoot();
        self::assertFileExists($repoRoot . '/coretsia', 'Repo root coretsia launcher MUST exist.');
        self::assertFileExists(
            $repoRoot . '/framework/tools/spikes/_support/bootstrap.php',
            'Tools spikes bootstrap MUST exist.'
        );

        self::assertTrue(
            \class_exists('Coretsia\\Tools\\Spikes\\fingerprint\\FingerprintCalculator'),
            'Fingerprint spike class MUST exist (prerequisite 0.60.0).'
        );

        $payload1 = null;
        $payload2 = null;

        $this->withLauncherAtRepoRoot($repoRoot, function () use (&$payload1, &$payload2): void {
            $cmd = new SpikeFingerprintCommand();

            $input = $this->createStub(InputInterface::class);
            $input->method('tokens')->willReturn([]);

            $output1 = $this->createMock(OutputInterface::class);
            $output1->expects(self::once())
                ->method('json')
                ->willReturnCallback(static function (array $data) use (&$payload1): void {
                    $payload1 = $data;
                });
            $output1->expects(self::never())->method('error');

            $exit1 = $cmd->run($input, $output1);
            self::assertSame(0, $exit1, 'spike:fingerprint MUST succeed with exit code 0.');

            $output2 = $this->createMock(OutputInterface::class);
            $output2->expects(self::once())
                ->method('json')
                ->willReturnCallback(static function (array $data) use (&$payload2): void {
                    $payload2 = $data;
                });
            $output2->expects(self::never())->method('error');

            $exit2 = $cmd->run($input, $output2);
            self::assertSame(0, $exit2, 'spike:fingerprint MUST be rerunnable and succeed.');
        });

        self::assertIsArray($payload1);
        self::assertIsArray($payload2);

        self::assertSame($payload1, $payload2, 'spike:fingerprint payload MUST be deterministic across two runs.');

        self::assertSame('spike:fingerprint', $payload1['command'] ?? null);
        self::assertSame(true, $payload1['ok'] ?? null);

        $fingerprint = $payload1['fingerprint'] ?? null;
        self::assertIsString($fingerprint);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $fingerprint, 'fingerprint MUST be sha256 hex.');

        $bucketDigests = $payload1['bucket_digests'] ?? null;
        self::assertIsArray($bucketDigests, 'bucket_digests MUST be present.');

        foreach ($bucketDigests as $k => $v) {
            self::assertIsString($k);
            self::assertIsString($v);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $v, 'bucket digest MUST be sha256 hex.');
        }

        $this->assertPayloadDoesNotContainAbsolutePaths($payload1);
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
