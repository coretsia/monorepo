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
use PHPUnit\Framework\TestCase;

final class AllCommandsRedactionSafeTest extends TestCase
{
    public function testAllCommandsAreRedactionSafeAndDoNotLeakAbsolutePathsOrSecrets(): void
    {
        $repoRoot = $this->repoRoot();

        self::assertFileExists($repoRoot . '/coretsia', 'Repo root coretsia launcher MUST exist.');
        self::assertFileExists(
            $repoRoot . '/framework/tools/spikes/_support/bootstrap.php',
            'Tools spikes bootstrap MUST exist.'
        );

        // Prerequisites that commands rely on:
        self::assertTrue(\class_exists('Coretsia\\Tools\\Spikes\\fingerprint\\FingerprintCalculator'));
        self::assertTrue(\class_exists('Coretsia\\Tools\\Spikes\\config_merge\\ConfigMerger'));
        self::assertTrue(\class_exists('Coretsia\\Tools\\Spikes\\workspace\\WorkspaceSyncWorkflow'));
        self::assertTrue(\class_exists('Coretsia\\Tools\\Spikes\\deptrac\\GraphArtifactBuilder'));

        $fixtureAbs = $repoRoot . '/framework/tools/spikes/fixtures/deptrac_min/package_index_ok.php';
        self::assertFileExists($fixtureAbs, 'Deptrac fixture MUST exist.');

        $outRel = 'framework/tools/spikes/_artifacts/_test_all_commands_redaction';
        $outAbs = $repoRoot . '/' . $outRel;

        if (\is_dir($outAbs)) {
            $this->rmDirRecursive($outAbs);
        }

        $this->withLauncherAtRepoRoot($repoRoot, function () use ($outRel): void {
            $cases = [
                ['cmd' => new DoctorCommand(), 'tokens' => []],
                ['cmd' => new SpikeFingerprintCommand(), 'tokens' => []],
                ['cmd' => new SpikeConfigDebugCommand(), 'tokens' => ['--key=cli.commands']],
                ['cmd' => new DeptracGraphCommand(), 'tokens' => ['--json', '--fixture=deptrac_min/package_index_ok.php', '--out=' . $outRel]],
                ['cmd' => new WorkspaceSyncDryRunCommand(), 'tokens' => ['--format=json']],
                ['cmd' => new WorkspaceSyncApplyCommand(), 'tokens' => ['--apply']],
            ];

            foreach ($cases as $case) {
                $cmd = $case['cmd'];
                $tokens = $case['tokens'];

                $input = new class($tokens) implements InputInterface {
                    /** @var list<string> */
                    private array $tokens;

                    /**
                     * @param list<string> $tokens
                     */
                    public function __construct(array $tokens)
                    {
                        $this->tokens = $tokens;
                    }

                    public function tokens(): array
                    {
                        return $this->tokens;
                    }
                };

                $output = new BufferingOutput();

                $exit = $cmd->run($input, $output);

                self::assertSame(0, $exit, 'Command MUST succeed: ' . \get_class($cmd));
                self::assertSame([], $output->errors(), 'Command MUST NOT emit error(): ' . \get_class($cmd));

                foreach ($output->jsonPayloads() as $payload) {
                    $this->assertPayloadDoesNotContainAbsolutePaths($payload);
                }
                foreach ($output->textLines() as $line) {
                    $this->assertStringIsSafe($line);
                }

                $blob = $output->blob();

                $lower = \strtolower($blob);
                $forbidden = [
                    'authorization',
                    'password',
                    'php://stdout',
                    'php://stderr',
                    'php://output',
                    '.env',
                    'bearer ',
                    'set-cookie',
                ];

                foreach ($forbidden as $needle) {
                    self::assertFalse(
                        \str_contains($lower, $needle),
                        'Output MUST NOT leak secret markers ("' . $needle . '") in ' . \get_class($cmd)
                    );
                }
            }
        });

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
        self::assertFalse(\str_contains($s, '\\'), 'MUST NOT contain backslashes: ' . $s);
        self::assertFalse(\str_starts_with($s, '/'), 'MUST NOT contain absolute POSIX paths: ' . $s);
        self::assertSame(0, \preg_match('/(?i)\b[A-Z]:[\\\\\/]/', $s), 'MUST NOT contain drive-letter paths: ' . $s);
        self::assertFalse(\str_starts_with($s, '\\\\'), 'MUST NOT contain UNC paths: ' . $s);
    }
}

/**
 * Minimal in-test OutputInterface implementation to capture output.
 */
final class BufferingOutput implements OutputInterface
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
        $this->errors[] = ['code' => $code, 'message' => $message];
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

    public function blob(): string
    {
        $chunks = [];

        foreach ($this->json as $p) {
            $chunks[] = (string)\json_encode($p, \JSON_UNESCAPED_SLASHES);
        }

        foreach ($this->text as $t) {
            $chunks[] = $t;
        }

        foreach ($this->errors as $e) {
            $chunks[] = $e['code'] . ':' . $e['message'];
        }

        return \implode("\n", $chunks);
    }
}
