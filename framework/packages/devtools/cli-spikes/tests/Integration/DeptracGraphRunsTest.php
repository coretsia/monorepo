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
use PHPUnit\Framework\TestCase;

final class DeptracGraphRunsTest extends TestCase
{
    public function testDeptracGraphCommandBuildsArtifactsToRepoRelativeDir(): void
    {
        $repoRoot = $this->repoRoot();

        self::assertFileExists($repoRoot . '/coretsia', 'Repo root coretsia launcher MUST exist.');
        self::assertFileExists(
            $repoRoot . '/framework/tools/spikes/_support/bootstrap.php',
            'Tools spikes bootstrap MUST exist.'
        );

        self::assertTrue(
            \class_exists('Coretsia\\Tools\\Spikes\\deptrac\\GraphArtifactBuilder'),
            'Deptrac graph builder MUST exist (prerequisite 0.80.0).'
        );

        $fixtureAbs = $repoRoot . '/framework/tools/spikes/fixtures/deptrac_min/package_index_ok.php';
        self::assertFileExists($fixtureAbs, 'Deptrac fixture MUST exist: deptrac_min/package_index_ok.php');

        $outRel = 'framework/tools/spikes/_artifacts/_test_deptrac_graph';
        $outAbs = $repoRoot . '/' . $outRel;

        if (\is_dir($outAbs)) {
            $this->rmDirRecursive($outAbs);
        }

        $payload = null;

        $this->withLauncherAtRepoRoot($repoRoot, function () use (&$payload, $outRel): void {
            $cmd = new DeptracGraphCommand();

            $input = new DeptracGraphRunsParsedInput([
                '--json',
                '--fixture=deptrac_min/package_index_ok.php',
                '--out=' . $outRel,
            ]);

            $output = $this->createMock(OutputInterface::class);
            $output->expects(self::once())
                ->method('json')
                ->willReturnCallback(static function (array $data) use (&$payload): void {
                    $payload = $data;
                });
            $output->expects(self::never())->method('error');

            $exit = $cmd->run($input, $output);
            self::assertSame(0, $exit, 'deptrac:graph MUST succeed with exit code 0.');
        });

        self::assertIsArray($payload);
        self::assertSame(true, $payload['ok'] ?? null);
        self::assertSame('deptrac:graph', $payload['command'] ?? null);
        self::assertSame($outRel, $payload['output_dir'] ?? null);

        $files = $payload['files'] ?? null;
        self::assertIsArray($files, 'deptrac:graph MUST return files list.');
        self::assertNotEmpty($files, 'deptrac:graph MUST return non-empty files list.');

        foreach ($files as $rel) {
            self::assertIsString($rel);
            self::assertStringStartsWith($outRel . '/', $rel, 'Reported artifact MUST be under output_dir.');
            $abs = $repoRoot . '/' . $rel;
            self::assertFileExists($abs, 'Artifact MUST exist: ' . $rel);
        }

        $this->assertPayloadDoesNotContainAbsolutePaths($payload);

        if (\is_dir($outAbs)) {
            $this->rmDirRecursive($outAbs);
        }
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

    private function rmDirRecursive(string $dir): void
    {
        $dir = \rtrim($dir, '/');

        if (!\is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }

            $p = $item->getPathname();
            if ($item->isFile() || $item->isLink()) {
                @\unlink($p);
                continue;
            }

            if ($item->isDir()) {
                @\rmdir($p);
            }
        }

        @\rmdir($dir);
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
            'Payload MUST NOT contain drive-letter paths: ' . $s
        );
        self::assertFalse(\str_starts_with($s, '\\\\'), 'Payload MUST NOT contain UNC paths: ' . $s);
    }
}

final class DeptracGraphRunsParsedInput implements InputInterface
{
    /**
     * @var list<string>
     */
    private array $tokens;

    /**
     * @var list<string>
     */
    private array $arguments;

    /**
     * @var array<string, string|bool|list<string>|null>
     */
    private array $options;

    /**
     * @param list<string> $tokens
     */
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;

        [$this->arguments, $this->options] = self::parseTokens($tokens);
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    public function commandName(): string
    {
        return 'deptrac:graph';
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, string|bool|list<string>|null>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }

    public function option(string $name): string|bool|array|null
    {
        return $this->options[$name] ?? null;
    }

    /**
     * @param list<string> $tokens
     * @return array{0: list<string>, 1: array<string, string|bool|list<string>|null>}
     */
    private static function parseTokens(array $tokens): array
    {
        $arguments = [];
        $options = [];

        $count = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '') {
                continue;
            }

            if (!\str_starts_with($token, '--')) {
                $arguments[] = $token;
                continue;
            }

            $raw = \substr($token, 2);

            if ($raw === '') {
                continue;
            }

            if (\str_contains($raw, '=')) {
                [$name, $value] = \explode('=', $raw, 2);

                if ($name !== '') {
                    $options[$name] = $value;
                }

                continue;
            }

            $next = $tokens[$i + 1] ?? null;

            if (\is_string($next) && $next !== '' && !\str_starts_with($next, '-')) {
                $options[$raw] = $next;
                $i++;

                continue;
            }

            $options[$raw] = true;
        }

        return [$arguments, $options];
    }
}
