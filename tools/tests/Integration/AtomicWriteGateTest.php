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

namespace Coretsia\Tools\Tests\Integration;

use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class AtomicWriteGateTest extends ToolContractTestCase
{
    public function testUnsafeFilePutContentsFailsDeterministically(): void
    {
        $fixtureRoot = $this->makeFixtureRoot('atomic_write_gate_unsafe_');
        $allowlist = $fixtureRoot . '/allowlist.php';

        $this->writeBytesExact(
            $fixtureRoot . '/UnsafeWriter.php',
            <<<'PHP'
<?php

declare(strict_types=1);

file_put_contents(__DIR__ . '/out.txt', 'unsafe');

PHP,
        );

        $this->writeBytesExact(
            $allowlist,
            <<<'PHP'
<?php

declare(strict_types=1);

return [];

PHP,
        );

        $result = $this->runGate([
            '--path=' . $fixtureRoot,
            '--allowlist=' . $allowlist,
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);

        $lines = $this->stderrLines($result['stderr']);

        self::assertSame('CORETSIA_ATOMIC_WRITE_VIOLATION', $lines[0] ?? null);
        self::assertContains('tools/runtime-fixture/UnsafeWriter.php:5', $lines);

        $this->assertOutputIsRedacted($result['stderr'], $fixtureRoot);
    }

    public function testReadOnlyFopenPasses(): void
    {
        $fixtureRoot = $this->makeFixtureRoot('atomic_write_gate_readonly_');
        $allowlist = $fixtureRoot . '/allowlist.php';

        $this->writeBytesExact(
            $fixtureRoot . '/ReadOnly.php',
            <<<'PHP'
<?php

declare(strict_types=1);

$handle = fopen(__FILE__, 'rb');
if (is_resource($handle)) {
    fclose($handle);
}

PHP,
        );

        $this->writeBytesExact(
            $allowlist,
            <<<'PHP'
<?php

declare(strict_types=1);

return [];

PHP,
        );

        $result = $this->runGate([
            '--path=' . $fixtureRoot,
            '--allowlist=' . $allowlist,
        ]);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testWritableFopenFailsDeterministically(): void
    {
        $fixtureRoot = $this->makeFixtureRoot('atomic_write_gate_fopen_');
        $allowlist = $fixtureRoot . '/allowlist.php';

        $this->writeBytesExact(
            $fixtureRoot . '/WritableFopen.php',
            <<<'PHP'
<?php

declare(strict_types=1);

$handle = fopen(__DIR__ . '/out.txt', 'wb');

PHP,
        );

        $this->writeBytesExact(
            $allowlist,
            <<<'PHP'
<?php

declare(strict_types=1);

return [];

PHP,
        );

        $result = $this->runGate([
            '--path=' . $fixtureRoot,
            '--allowlist=' . $allowlist,
        ]);

        self::assertSame(1, $result['exit']);

        $lines = $this->stderrLines($result['stderr']);

        self::assertSame('CORETSIA_ATOMIC_WRITE_VIOLATION', $lines[0] ?? null);
        self::assertContains('tools/runtime-fixture/WritableFopen.php:5', $lines);

        $this->assertOutputIsRedacted($result['stderr'], $fixtureRoot);
    }

    public function testInvalidAllowlistFailsWithGateFailedCode(): void
    {
        $fixtureRoot = $this->makeFixtureRoot('atomic_write_gate_bad_allowlist_');
        $allowlist = $fixtureRoot . '/allowlist.php';

        $this->writeBytesExact(
            $fixtureRoot . '/Safe.php',
            <<<'PHP'
<?php

declare(strict_types=1);

$value = 1;

PHP,
        );

        $this->writeBytesExact(
            $allowlist,
            <<<'PHP'
<?php

declare(strict_types=1);

return [
    [
        'path' => '/absolute.php',
        'reason' => 'bad',
    ],
];

PHP,
        );

        $result = $this->runGate([
            '--path=' . $fixtureRoot,
            '--allowlist=' . $allowlist,
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);

        $lines = $this->stderrLines($result['stderr']);

        self::assertSame(['CORETSIA_ATOMIC_WRITE_GATE_FAILED'], $lines);
        $this->assertOutputIsRedacted($result['stderr'], $fixtureRoot);
    }

    public function testMissingScanRootFailsWithGateFailedCode(): void
    {
        $fixtureRoot = $this->makeFixtureRoot('atomic_write_gate_missing_root_');
        $allowlist = $fixtureRoot . '/allowlist.php';
        $missingRoot = $fixtureRoot . '/missing';

        $this->writeBytesExact(
            $allowlist,
            <<<'PHP'
<?php

declare(strict_types=1);

return [];

PHP,
        );

        $result = $this->runGate([
            '--path=' . $missingRoot,
            '--allowlist=' . $allowlist,
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);

        $lines = $this->stderrLines($result['stderr']);

        self::assertSame(['CORETSIA_ATOMIC_WRITE_GATE_FAILED'], $lines);
        $this->assertOutputIsRedacted($result['stderr'], $fixtureRoot);
    }

    /**
     * @param list<string> $args
     * @return array{exit:int,stdout:string,stderr:string}
     */
    private function runGate(array $args): array
    {
        $allowlistPath = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--allowlist=')) {
                $allowlistPath = substr($arg, strlen('--allowlist='));
                break;
            }
        }

        self::assertIsString($allowlistPath);

        $repository = RepositoryContext::discoverFrom($allowlistPath);
        $repoRoot = $repository->repoRoot();
        $gate = $repository->resolveExistingFile('tools/gates/atomic_write_gate.php');

        $cmd = \array_merge(
            [\PHP_BINARY, $gate],
            $args,
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open($cmd, $descriptorSpec, $pipes, $repoRoot);

        self::assertIsResource($process);

        \fclose($pipes[0]);

        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exit = \proc_close($process);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return [
            'exit' => $exit,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * @return list<string>
     */
    private function stderrLines(string $stderr): array
    {
        $stderr = \str_replace(["\r\n", "\r"], "\n", $stderr);
        $stderr = \trim($stderr);

        if ($stderr === '') {
            return [];
        }

        return \explode("\n", $stderr);
    }

    private function makeFixtureRoot(string $prefix): string
    {
        $repoRoot = $this->tempDir($prefix);

        $this->ensureDir($repoRoot . '/packages');
        $this->ensureDir($repoRoot . '/tools/gates');
        $this->copyDir(
            $this->repoRoot() . '/tools/support',
            $repoRoot . '/tools/support',
        );

        $this->writeBytesExact(
            $repoRoot . '/composer.json',
            "{\n"
            . "  \"name\": \"coretsia/atomic-write-fixture\",\n"
            . "  \"type\": \"project\"\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $repoRoot . '/tools/gates/atomic_write_gate.php',
            $this->readBytes(
                $this->repoRoot() . '/tools/gates/atomic_write_gate.php',
            ),
        );

        $this->writeBytesExact(
            $repoRoot . '/vendor/autoload.php',
            <<<'PHP'
<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../tools/support/*.php') ?: [] as $path) {
    if (basename($path) === 'bootstrap.php') {
        continue;
    }

    require_once $path;
}

PHP,
        );

        $fixtureRoot = $repoRoot . '/tools/runtime-fixture';
        $this->ensureDir($fixtureRoot);

        return $fixtureRoot;
    }

    private function assertOutputIsRedacted(string $output, string $tmpRoot): void
    {
        $normalized = \str_replace('\\', '/', $output);

        self::assertStringNotContainsString(\str_replace('\\', '/', $tmpRoot), $normalized);
        self::assertStringNotContainsString('file_put_contents(', $normalized);
        self::assertStringNotContainsString('fopen(', $normalized);
        self::assertStringNotContainsString('Stack trace', $normalized);
        self::assertStringNotContainsString('RuntimeException', $normalized);
    }
}
