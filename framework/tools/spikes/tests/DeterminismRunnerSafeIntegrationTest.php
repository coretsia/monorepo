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

namespace Coretsia\Tools\Spikes\tests;

use Coretsia\Tools\Spikes\_support\ConsoleOutput;
use Coretsia\Tools\Spikes\_support\DeterminismRunner;
use Coretsia\Tools\Spikes\_support\ProcessResult;
use Coretsia\Tools\Spikes\_support\ProcessRunnerInterface;
use PHPUnit\Framework\TestCase;

final class DeterminismRunnerSafeIntegrationTest extends TestCase
{
    /** @var resource|null */
    private $memOut = null;

    /** @var resource|null */
    private $memErr = null;

    /** @var mixed */
    private $prevOut = null;

    /** @var mixed */
    private $prevErr = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure classes are loaded in a deterministic way for stream swapping.
        require_once __DIR__ . '/../_support/ConsoleOutput.php';
        require_once __DIR__ . '/../_support/ProcessResult.php';
        require_once __DIR__ . '/../_support/ProcessRunnerInterface.php';
        require_once __DIR__ . '/../_support/DeterminismRunner.php';

        $this->memOut = fopen('php://memory', 'wb+');
        $this->memErr = fopen('php://memory', 'wb+');

        self::assertIsResource($this->memOut);
        self::assertIsResource($this->memErr);

        [$this->prevOut, $this->prevErr] = self::swapConsoleStreams($this->memOut, $this->memErr);
    }

    protected function tearDown(): void
    {
        // Always reset injection hook (avoid cross-test coupling).
        DeterminismRunner::setProcessRunner(null);

        self::restoreConsoleStreams($this->prevOut, $this->prevErr);

        if (is_resource($this->memOut)) {
            fclose($this->memOut);
        }
        if (is_resource($this->memErr)) {
            fclose($this->memErr);
        }

        parent::tearDown();
    }

    public function test_success_is_silent_and_executes_expected_sequence_without_spawning_processes(): void
    {
        $repoRoot = self::repoRootPath();

        $runner = new RecordingProcessRunner(
            repoRoot: $repoRoot,
            gitResults: [
                // #1 assertGitAvailable
                new ProcessResult(0, '', ''),
                // #2 assertWorktreeClean (pre-run1)
                new ProcessResult(0, '', ''),
                // #3 assertWorktreeClean (between runs)
                new ProcessResult(0, '', ''),
                // #4 assertWorktreeClean (post-run2)
                new ProcessResult(0, '', ''),
            ],
            composerExitCodes: [0, 0],
        );

        DeterminismRunner::setProcessRunner($runner);

        $exit = DeterminismRunner::main();

        self::assertSame(0, $exit);
        self::assertSame('', $this->readAll($this->memOut));
        self::assertSame('', $this->readAll($this->memErr));

        // Integration assertions (sequence + invariants).
        self::assertSame(4, $runner->gitCallsCount());
        self::assertSame(2, $runner->composerCallsCount());

        foreach ($runner->composerTmpRoots() as $tmp) {
            self::assertNotSame('', $tmp);
            self::assertFalse(self::isInside(self::canonicalize($tmp), self::canonicalize($repoRoot)), 'tmp MUST be outside repo root');
        }
    }

    public function test_git_required_failure_is_deterministic_and_safe(): void
    {
        $repoRoot = self::repoRootPath();

        $runner = new RecordingProcessRunner(
            repoRoot: $repoRoot,
            gitResults: [
                // assertGitAvailable fails
                new ProcessResult(1, '', 'git missing'),
            ],
            composerExitCodes: [],
        );

        DeterminismRunner::setProcessRunner($runner);

        $exit = DeterminismRunner::main();

        self::assertSame(1, $exit);

        $payload = $this->pickPayload();
        $lines = self::lines($payload);

        self::assertSame('CORETSIA_DETERMINISM_GIT_REQUIRED', $lines[0] ?? '');
        self::assertSame('git-required', $lines[1] ?? '');

        self::assertPayloadIsSafe($payload);
    }

    public function test_worktree_dirty_failure_is_deterministic_and_safe(): void
    {
        $repoRoot = self::repoRootPath();

        $runner = new RecordingProcessRunner(
            repoRoot: $repoRoot,
            gitResults: [
                // assertGitAvailable ok
                new ProcessResult(0, '', ''),
                // assertWorktreeClean sees dirty porcelain
                new ProcessResult(0, " M some-file.php\n", ''),
            ],
            composerExitCodes: [],
        );

        DeterminismRunner::setProcessRunner($runner);

        $exit = DeterminismRunner::main();

        self::assertSame(1, $exit);

        $payload = $this->pickPayload();
        $lines = self::lines($payload);

        self::assertSame('CORETSIA_DETERMINISM_WORKTREE_DIRTY', $lines[0] ?? '');
        self::assertSame('worktree-dirty', $lines[1] ?? '');

        self::assertPayloadIsSafe($payload);
    }

    public function test_failure_output_is_exactly_code_plus_reason_and_does_not_echo_captured_git_output(): void
    {
        $repoRoot = self::repoRootPath();

        $runner = new RecordingProcessRunner(
            repoRoot: $repoRoot,
            gitResults: [
                // #1 assertGitAvailable ok
                new ProcessResult(0, '', ''),

                // #2 assertWorktreeClean: porcelain output is non-empty (dirty),
                // and includes intentionally UNSAFE content that MUST NOT be printed by DeterminismRunner.
                new ProcessResult(
                    0,
                    "TOKEN=supersecret\n/home/user/.env\nC:\\x\n",
                    "see /Users/user/.env\n\\\\server\\share\\file\n"
                ),
            ],
            composerExitCodes: [],
        );

        DeterminismRunner::setProcessRunner($runner);

        $exit = DeterminismRunner::main();

        self::assertSame(1, $exit);

        $payload = $this->pickPayload();
        $lines = self::lines($payload);

        // Hard contract: only CODE + reason, no extra lines.
        self::assertCount(2, $lines, $payload);
        self::assertSame('CORETSIA_DETERMINISM_WORKTREE_DIRTY', $lines[0] ?? '', $payload);
        self::assertSame('worktree-dirty', $lines[1] ?? '', $payload);

        // Safety: MUST NOT leak captured process output (even if it contains unsafe patterns).
        self::assertPayloadIsSafe($payload);

        // Behavioral: must fail before any composer run.
        self::assertSame(2, $runner->gitCallsCount());
        self::assertSame(0, $runner->composerCallsCount());
    }

    public function test_run1_nonzero_failure_is_deterministic_and_safe(): void
    {
        $repoRoot = self::repoRootPath();

        $runner = new RecordingProcessRunner(
            repoRoot: $repoRoot,
            gitResults: [
                // assertGitAvailable ok
                new ProcessResult(0, '', ''),
                // assertWorktreeClean ok
                new ProcessResult(0, '', ''),
            ],
            composerExitCodes: [2], // run #1 fails
        );

        DeterminismRunner::setProcessRunner($runner);

        $exit = DeterminismRunner::main();

        self::assertSame(1, $exit);

        $payload = $this->pickPayload();
        $lines = self::lines($payload);

        self::assertSame('CORETSIA_DETERMINISM_RERUN_FAILED', $lines[0] ?? '');
        self::assertSame('run1-nonzero', $lines[1] ?? '');

        self::assertPayloadIsSafe($payload);

        self::assertSame(1, $runner->composerCallsCount(), 'run #2 MUST NOT execute if run #1 failed');
    }

    public function test_runner_error_on_composer_spawn_is_deterministic_and_safe(): void
    {
        $repoRoot = self::repoRootPath();

        $runner = new RecordingProcessRunner(
            repoRoot: $repoRoot,
            gitResults: [
                // assertGitAvailable ok
                new ProcessResult(0, '', ''),
                // assertWorktreeClean ok
                new ProcessResult(0, '', ''),
            ],
            composerExitCodes: [null], // proc_open failure simulation
        );

        DeterminismRunner::setProcessRunner($runner);

        $exit = DeterminismRunner::main();

        self::assertSame(1, $exit);

        $payload = $this->pickPayload();
        $lines = self::lines($payload);

        self::assertSame('CORETSIA_DETERMINISM_RERUN_FAILED', $lines[0] ?? '');
        self::assertSame('runner-error', $lines[1] ?? '');

        self::assertPayloadIsSafe($payload);
    }

    private function pickPayload(): string
    {
        // ConsoleOutput writes to "stdout" stream by default; stderr is also possible.
        $stderr = trim($this->readAll($this->memErr));
        if ($stderr !== '') {
            return $stderr;
        }

        return trim($this->readAll($this->memOut));
    }

    /**
     * @param resource $h
     */
    private function readAll($h): string
    {
        self::assertIsResource($h);

        rewind($h);
        $s = stream_get_contents($h);
        if ($s === false) {
            return '';
        }

        return (string)$s;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $payload): array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return [];
        }

        $parts = preg_split('/\r\n|\r|\n/', $payload);
        if (!is_array($parts)) {
            return [];
        }

        $out = [];
        foreach ($parts as $p) {
            if (!is_string($p)) {
                continue;
            }
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $out[] = $p;
        }

        /** @var list<string> $out */
        return $out;
    }

    private static function assertPayloadIsSafe(string $payload): void
    {
        self::assertSame(0, preg_match('~(?i)\b[A-Z]:[\\\\/]~', $payload), 'payload MUST NOT contain drive-letter paths');
        self::assertSame(0, preg_match('~\\\\\\\\\S+~', $payload), 'payload MUST NOT contain UNC paths');
        self::assertFalse(str_contains($payload, '/home/'), 'payload MUST NOT contain /home/');
        self::assertFalse(str_contains($payload, '/Users/'), 'payload MUST NOT contain /Users/');
        self::assertSame(0, preg_match('~\b\w+\s*=\s*\S+~', $payload), 'payload MUST NOT contain dotenv-like patterns');
        self::assertFalse(str_contains($payload, "\x1B"), 'payload MUST NOT contain ANSI escapes');
    }

    /**
     * @param resource $out
     * @param resource $err
     * @return array{0:mixed,1:mixed}
     */
    private static function swapConsoleStreams($out, $err): array
    {
        $ref = new \ReflectionClass(ConsoleOutput::class);

        $pOut = $ref->getProperty('stdout');
        $pErr = $ref->getProperty('stderr');

        $prevOut = $pOut->getValue();
        $prevErr = $pErr->getValue();

        $pOut->setValue(null, $out);
        $pErr->setValue(null, $err);

        return [$prevOut, $prevErr];
    }

    private static function restoreConsoleStreams($prevOut, $prevErr): void
    {
        $ref = new \ReflectionClass(ConsoleOutput::class);

        $pOut = $ref->getProperty('stdout');
        $pErr = $ref->getProperty('stderr');

        $pOut->setValue(null, $prevOut);
        $pErr->setValue(null, $prevErr);
    }

    private static function repoRootPath(): string
    {
        // tests dir: framework/tools/spikes/tests
        $frameworkRoot = realpath(__DIR__ . '/../../..');
        if (!is_string($frameworkRoot) || $frameworkRoot === '') {
            throw new \RuntimeException('framework-root-missing');
        }

        $repoRoot = realpath($frameworkRoot . '/..');
        if (!is_string($repoRoot) || $repoRoot === '') {
            throw new \RuntimeException('repo-root-missing');
        }

        return $repoRoot;
    }

    private static function canonicalize(string $path): string
    {
        $path = rtrim($path, "\\/");

        if ($path === '') {
            return '';
        }

        $real = realpath($path);
        $canonical = $real !== false ? $real : $path;

        return str_replace('\\', '/', $canonical);
    }

    private static function isInside(string $candidate, string $root): bool
    {
        $candidate = rtrim($candidate, '/');
        $root = rtrim($root, '/');

        if ($candidate === '' || $root === '') {
            return false;
        }

        if ($candidate === $root) {
            return true;
        }

        return str_starts_with($candidate, $root . '/');
    }
}

/**
 * Minimal recording fake runner: deterministic queues, no process spawning.
 *
 * @internal
 */
final class RecordingProcessRunner implements ProcessRunnerInterface
{
    private string $repoRoot;

    /** @var list<ProcessResult> */
    private array $gitResults;

    /** @var list<int|null> */
    private array $composerExitCodes;

    /** @var list<array{cmd:string,cwd:string,captureStdout:bool,captureStderr:bool}> */
    private array $runCalls = [];

    /** @var list<array{cmd:string,cwd:string,tmpRoot:string}> */
    private array $silencedCalls = [];

    /**
     * @param list<ProcessResult> $gitResults
     * @param list<int|null> $composerExitCodes
     */
    public function __construct(string $repoRoot, array $gitResults, array $composerExitCodes)
    {
        $this->repoRoot = $repoRoot;
        $this->gitResults = array_values($gitResults);
        $this->composerExitCodes = array_values($composerExitCodes);
    }

    public function run(
        string $command,
        string $cwd,
        ?array $env,
        bool   $captureStdout,
        bool   $captureStderr
    ): ProcessResult
    {
        $this->runCalls[] = [
            'cmd' => self::normalizeCommand($command),
            'cwd' => $cwd,
            'captureStdout' => $captureStdout,
            'captureStderr' => $captureStderr,
        ];

        // We only expect git status here in DeterminismRunner.
        if (self::normalizeCommand($command) !== 'git status --porcelain') {
            return new ProcessResult(1, '', '');
        }

        if ($cwd !== $this->repoRoot) {
            return new ProcessResult(1, '', '');
        }

        if ($this->gitResults === []) {
            return new ProcessResult(1, '', '');
        }

        return array_shift($this->gitResults);
    }

    public function runSilenced(string $command, string $cwd, array $env): ?int
    {
        $tmpRoot = (string)($env['CORETSIA_SPIKES_TMP'] ?? '');

        $this->silencedCalls[] = [
            'cmd' => self::normalizeCommand($command),
            'cwd' => $cwd,
            'tmpRoot' => $tmpRoot,
        ];

        if (!self::isComposerSpikeTestCommand($command)) {
            return null;
        }

        if ($cwd !== $this->repoRoot) {
            return null;
        }

        if ($this->composerExitCodes === []) {
            return null;
        }

        return array_shift($this->composerExitCodes);
    }

    public function gitCallsCount(): int
    {
        $n = 0;
        foreach ($this->runCalls as $c) {
            if ($c['cmd'] === 'git status --porcelain') {
                $n++;
            }
        }
        return $n;
    }

    public function composerCallsCount(): int
    {
        $n = 0;
        foreach ($this->silencedCalls as $c) {
            if (self::isComposerSpikeTestCommand($c['cmd'])) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @return list<string>
     */
    public function composerTmpRoots(): array
    {
        $out = [];
        foreach ($this->silencedCalls as $c) {
            $out[] = $c['tmpRoot'];
        }
        return $out;
    }

    private static function normalizeCommand(string $command): string
    {
        $command = trim($command);
        $command = preg_replace('/\s+/', ' ', $command) ?? $command;
        return $command;
    }

    private static function isComposerSpikeTestCommand(string $command): bool
    {
        $command = self::normalizeCommand($command);

        // Hard-allowlist the known deterministic variants.
        if ($command === 'composer spike:test') {
            return true;
        }
        if ($command === 'composer --no-interaction spike:test') {
            return true;
        }

        // Future-proofing: still accept "composer <flags> spike:test"
        // but only if it is clearly the spike:test script invocation.
        if (!str_starts_with($command, 'composer ')) {
            return false;
        }

        return str_contains(' ' . $command . ' ', ' spike:test ');
    }
}
