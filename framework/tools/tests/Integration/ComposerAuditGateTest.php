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

use PHPUnit\Framework\TestCase;

final class ComposerAuditGateTest extends TestCase
{
    public function testCleanAuditPassesWithoutOutput(): void
    {
        $repoRoot = $this->createFixtureRepoRoot('composer_audit_clean_');
        $fakeComposer = $this->writeFakeComposer(
            $repoRoot,
            [
                'root' => $this->fixturePath('audit_clean.json'),
                'framework' => $this->fixturePath('audit_clean.json'),
                'skeleton' => $this->fixturePath('audit_clean.json'),
            ],
            [
                'root' => 0,
                'framework' => 0,
                'skeleton' => 0,
            ],
        );

        $result = $this->runGate([
            '--path=' . $repoRoot,
            '--composer=' . $fakeComposer,
        ]);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testAdvisoryFoundFailsDeterministically(): void
    {
        $repoRoot = $this->createFixtureRepoRoot('composer_audit_advisory_');
        $fakeComposer = $this->writeFakeComposer(
            $repoRoot,
            [
                'root' => $this->fixturePath('audit_clean.json'),
                'framework' => $this->fixturePath('audit_with_advisories.json'),
                'skeleton' => $this->fixturePath('audit_clean.json'),
            ],
            [
                'root' => 0,
                'framework' => 1,
                'skeleton' => 0,
            ],
        );

        $result = $this->runGate([
            '--path=' . $repoRoot,
            '--composer=' . $fakeComposer,
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);

        $lines = $this->stderrLines($result['stderr']);

        self::assertSame(
            [
                'CORETSIA_COMPOSER_AUDIT_FAILED',
                'framework:vendor/package:PKSA-abcd-efgh-ijkl',
            ],
            $lines,
        );

        $this->assertOutputIsRedacted($result['stderr'], $repoRoot);
    }

    public function testScanFailureFailsWithScanFailedCode(): void
    {
        $repoRoot = $this->createFixtureRepoRoot('composer_audit_scan_failed_');
        $fakeComposer = $this->writeFakeComposer(
            $repoRoot,
            [
                'root' => $this->fixturePath('audit_scan_failed.json'),
                'framework' => $this->fixturePath('audit_clean.json'),
                'skeleton' => $this->fixturePath('audit_clean.json'),
            ],
            [
                'root' => 1,
                'framework' => 0,
                'skeleton' => 0,
            ],
        );

        $result = $this->runGate([
            '--path=' . $repoRoot,
            '--composer=' . $fakeComposer,
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);

        self::assertSame(
            ['CORETSIA_COMPOSER_AUDIT_SCAN_FAILED'],
            $this->stderrLines($result['stderr']),
        );

        $this->assertOutputIsRedacted($result['stderr'], $repoRoot);
    }

    public function testComposerAuditNonZeroWithValidAdvisoriesIsFindingNotScanFailure(): void
    {
        $repoRoot = $this->createFixtureRepoRoot('composer_audit_nonzero_advisory_');
        $fakeComposer = $this->writeFakeComposer(
            $repoRoot,
            [
                'root' => $this->fixturePath('audit_clean.json'),
                'framework' => $this->fixturePath('audit_with_advisories.json'),
                'skeleton' => $this->fixturePath('audit_clean.json'),
            ],
            [
                'root' => 0,
                'framework' => 1,
                'skeleton' => 0,
            ],
            [
                'framework' => true,
            ],
        );

        $result = $this->runGate([
            '--path=' . $repoRoot,
            '--composer=' . $fakeComposer,
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);

        self::assertSame(
            [
                'CORETSIA_COMPOSER_AUDIT_FAILED',
                'framework:vendor/package:PKSA-abcd-efgh-ijkl',
            ],
            $this->stderrLines($result['stderr']),
        );

        $this->assertOutputIsRedacted($result['stderr'], $repoRoot);
    }

    public function testComposerExecutableCannotBeRunFailsWithScanFailedCode(): void
    {
        $repoRoot = $this->createFixtureRepoRoot('composer_audit_missing_composer_');

        $result = $this->runGate([
            '--path=' . $repoRoot,
            '--composer=' . $repoRoot . '/missing-composer.php',
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            ['CORETSIA_COMPOSER_AUDIT_SCAN_FAILED'],
            $this->stderrLines($result['stderr']),
        );

        $this->assertOutputIsRedacted($result['stderr'], $repoRoot);
    }

    public function testInvalidAuditJsonFailsWithScanFailedCode(): void
    {
        $repoRoot = $this->createFixtureRepoRoot('composer_audit_invalid_json_');
        $invalidJson = $repoRoot . '/invalid-audit.json';

        $this->writeFile($invalidJson, "not-json\n");

        $fakeComposer = $this->writeFakeComposer(
            $repoRoot,
            [
                'root' => $invalidJson,
                'framework' => $this->fixturePath('audit_clean.json'),
                'skeleton' => $this->fixturePath('audit_clean.json'),
            ],
            [
                'root' => 1,
                'framework' => 0,
                'skeleton' => 0,
            ],
        );

        $result = $this->runGate([
            '--path=' . $repoRoot,
            '--composer=' . $fakeComposer,
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            ['CORETSIA_COMPOSER_AUDIT_SCAN_FAILED'],
            $this->stderrLines($result['stderr']),
        );

        $this->assertOutputIsRedacted($result['stderr'], $repoRoot);
    }

    public function testComposerNonZeroWithCleanAuditJsonFailsWithScanFailedCode(): void
    {
        $repoRoot = $this->createFixtureRepoRoot('composer_audit_nonzero_clean_');
        $fakeComposer = $this->writeFakeComposer(
            $repoRoot,
            [
                'root' => $this->fixturePath('audit_clean.json'),
                'framework' => $this->fixturePath('audit_clean.json'),
                'skeleton' => $this->fixturePath('audit_clean.json'),
            ],
            [
                'root' => 1,
                'framework' => 0,
                'skeleton' => 0,
            ],
        );

        $result = $this->runGate([
            '--path=' . $repoRoot,
            '--composer=' . $fakeComposer,
        ]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            ['CORETSIA_COMPOSER_AUDIT_SCAN_FAILED'],
            $this->stderrLines($result['stderr']),
        );

        $this->assertOutputIsRedacted($result['stderr'], $repoRoot);
    }

    public function testEmptyComposerLocksAreTreatedAsCleanNoOp(): void
    {
        $repoRoot = $this->createFixtureRepoRoot('composer_audit_empty_locks_');

        $this->writeEmptyComposerLock($repoRoot . '/composer.lock');
        $this->writeEmptyComposerLock($repoRoot . '/framework/composer.lock');
        $this->writeEmptyComposerLock($repoRoot . '/skeleton/composer.lock');

        $fakeComposer = $this->writeFakeComposer(
            $repoRoot,
            [
                'root' => $this->fixturePath('audit_scan_failed.json'),
                'framework' => $this->fixturePath('audit_scan_failed.json'),
                'skeleton' => $this->fixturePath('audit_scan_failed.json'),
            ],
            [
                'root' => 1,
                'framework' => 1,
                'skeleton' => 1,
            ],
        );

        $result = $this->runGate([
            '--path=' . $repoRoot,
            '--composer=' . $fakeComposer,
        ]);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    /**
     * @param list<string> $args
     * @return array{exit:int,stdout:string,stderr:string}
     */
    private function runGate(array $args): array
    {
        $frameworkRoot = $this->frameworkRoot();
        $gate = $frameworkRoot . '/tools/gates/composer_audit_gate.php';

        self::assertFileExists($gate);

        $cmd = \array_merge(
            [\PHP_BINARY, $gate],
            $args,
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open($cmd, $descriptorSpec, $pipes, $frameworkRoot);

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
     * @param array{root:string,framework:string,skeleton:string} $fixtureByRoot
     * @param array{root:int,framework:int,skeleton:int} $exitByRoot
     * @param array<string,bool> $stderrByRoot
     */
    private function writeFakeComposer(
        string $repoRoot,
        array $fixtureByRoot,
        array $exitByRoot,
        array $stderrByRoot = [],
    ): string {
        $path = $repoRoot . '/fake-composer.php';

        $fixtures = \var_export($fixtureByRoot, true);
        $exits = \var_export($exitByRoot, true);
        $stderr = \var_export($stderrByRoot, true);

        $this->writeFile(
            $path,
            <<<PHP
<?php

declare(strict_types=1);

\$fixtures = {$fixtures};
\$exits = {$exits};
\$stderr = {$stderr};

\$cwd = getcwd();
\$cwd = is_string(\$cwd) ? rtrim(str_replace('\\\\', '/', \$cwd), '/') : '';

\$label = 'root';
if (str_ends_with(\$cwd, '/framework')) {
    \$label = 'framework';
}
if (str_ends_with(\$cwd, '/skeleton')) {
    \$label = 'skeleton';
}

\$fixture = \$fixtures[\$label] ?? null;
if (!is_string(\$fixture) || !is_file(\$fixture)) {
    exit(99);
}

\$payload = file_get_contents(\$fixture);
if (!is_string(\$payload)) {
    exit(98);
}

if ((\$stderr[\$label] ?? false) === true) {
    fwrite(STDERR, \$payload);
} else {
    fwrite(STDOUT, \$payload);
}

exit((int)(\$exits[\$label] ?? 0));

PHP,
        );

        return $path;
    }

    private function createFixtureRepoRoot(string $prefix): string
    {
        $repoRoot = $this->makeTempDir($prefix);

        $this->writeComposerJson($repoRoot . '/composer.json', 'fixture/root');
        $this->writeComposerJson($repoRoot . '/framework/composer.json', 'fixture/framework');
        $this->writeComposerJson($repoRoot . '/skeleton/composer.json', 'fixture/skeleton');

        $this->writeComposerLock($repoRoot . '/composer.lock');
        $this->writeComposerLock($repoRoot . '/framework/composer.lock');
        $this->writeComposerLock($repoRoot . '/skeleton/composer.lock');

        return $repoRoot;
    }

    private function writeComposerJson(string $path, string $name): void
    {
        $this->writeFile(
            $path,
            <<<JSON
{
  "name": "{$name}",
  "type": "project",
  "require": {
    "php": "^8.4"
  }
}

JSON,
        );
    }

    private function writeComposerLock(string $path): void
    {
        $this->writeFile(
            $path,
            <<<'JSON'
{
  "packages": [
    {
      "name": "fixture/package",
      "version": "1.0.0"
    }
  ],
  "packages-dev": []
}

JSON,
        );
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

    private function fixturePath(string $name): string
    {
        return $this->frameworkRoot() . '/tools/tests/Fixtures/ComposerAudit/' . $name;
    }

    private function frameworkRoot(): string
    {
        return \rtrim(\str_replace('\\', '/', \dirname(__DIR__, 3)), '/');
    }

    private function makeTempDir(string $prefix): string
    {
        $base = \sys_get_temp_dir();
        $dir = \rtrim(\str_replace('\\', '/', $base), '/') . '/' . $prefix . \bin2hex(\random_bytes(6));

        self::assertTrue(\mkdir($dir, 0777, true));

        return $dir;
    }

    private function writeFile(string $path, string $contents): void
    {
        $dir = \dirname($path);

        if (!\is_dir($dir)) {
            self::assertTrue(\mkdir($dir, 0777, true));
        }

        self::assertNotFalse(\file_put_contents($path, $contents));
    }

    private function assertOutputIsRedacted(string $output, string $tmpRoot): void
    {
        $normalized = \str_replace('\\', '/', $output);

        self::assertStringNotContainsString(\str_replace('\\', '/', $tmpRoot), $normalized);
        self::assertStringNotContainsString('https://', $normalized);
        self::assertStringNotContainsString('Fixture advisory title', $normalized);
        self::assertStringNotContainsString('Stack trace', $normalized);
        self::assertStringNotContainsString('RuntimeException', $normalized);
    }

    private function writeEmptyComposerLock(string $path): void
    {
        $this->writeFile(
            $path,
            <<<'JSON'
{
  "packages": [],
  "packages-dev": []
}

JSON,
        );
    }
}
