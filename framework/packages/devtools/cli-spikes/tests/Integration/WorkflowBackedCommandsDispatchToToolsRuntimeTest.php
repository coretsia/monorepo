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
use Coretsia\Devtools\CliSpikes\Command\SpikeConfigDebugCommand;
use Coretsia\Devtools\CliSpikes\Command\SpikeFingerprintCommand;
use Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncApplyCommand;
use Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncDryRunCommand;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class WorkflowBackedCommandsDispatchToToolsRuntimeTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSpikeFingerprintCommandDispatchesToToolsWorkflow(): void
    {
        $repoRoot = $this->repoRoot();

        AliasFingerprintWorkflowFake::reset();
        AliasFingerprintWorkflowFake::$fixtureRepoRootAbs = $repoRoot;

        self::assertFalse(
            \class_exists('Coretsia\\Tools\\Spikes\\fingerprint\\FingerprintWorkflow', false),
            'FingerprintWorkflow MUST NOT be loaded before alias proof test.'
        );

        self::assertTrue(
            \class_alias(
                AliasFingerprintWorkflowFake::class,
                'Coretsia\\Tools\\Spikes\\fingerprint\\FingerprintWorkflow'
            ),
            'Failed to alias fake FingerprintWorkflow.'
        );

        $payload = null;

        $this->withLauncherAtRepoRoot($repoRoot, function () use (&$payload): void {
            $command = new SpikeFingerprintCommand();
            $input = $this->stubInput([]);
            $output = new DispatchProofOutput();

            $exit = $command->run($input, $output);

            self::assertSame(0, $exit, 'spike:fingerprint MUST succeed through aliased tools workflow.');
            self::assertSame([], $output->errors(), 'spike:fingerprint MUST NOT emit error().');

            $jsonPayloads = $output->jsonPayloads();
            self::assertCount(1, $jsonPayloads);

            $payload = $jsonPayloads[0];
        });

        self::assertSame(1, AliasFingerprintWorkflowFake::$calls, 'Fingerprint fake workflow MUST be called exactly once.');
        self::assertIsArray($payload);
        self::assertSame('spike:fingerprint', $payload['command'] ?? null);
        self::assertSame(true, $payload['ok'] ?? null);
        self::assertSame('.', $payload['fixture_repo_root'] ?? null);
        self::assertSame(\str_repeat('a', 64), $payload['fingerprint'] ?? null);
        self::assertSame(
            ['fake-bucket' => \str_repeat('b', 64)],
            $payload['bucket_digests'] ?? null
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSpikeConfigDebugCommandDispatchesToToolsWorkflow(): void
    {
        AliasConfigDebugWorkflowFake::reset();

        self::assertFalse(
            \class_exists('Coretsia\\Tools\\Spikes\\config_merge\\ConfigDebugWorkflow', false),
            'ConfigDebugWorkflow MUST NOT be loaded before alias proof test.'
        );

        self::assertTrue(
            \class_alias(
                AliasConfigDebugWorkflowFake::class,
                'Coretsia\\Tools\\Spikes\\config_merge\\ConfigDebugWorkflow'
            ),
            'Failed to alias fake ConfigDebugWorkflow.'
        );

        $repoRoot = $this->repoRoot();
        $payload = null;

        $this->withLauncherAtRepoRoot($repoRoot, function () use (&$payload): void {
            $command = new SpikeConfigDebugCommand();
            $input = $this->stubInput(['--key=cli.commands']);
            $output = new DispatchProofOutput();

            $exit = $command->run($input, $output);

            self::assertSame(0, $exit, 'spike:config:debug MUST succeed through aliased tools workflow.');
            self::assertSame([], $output->errors(), 'spike:config:debug MUST NOT emit error().');

            $jsonPayloads = $output->jsonPayloads();
            self::assertCount(1, $jsonPayloads);

            $payload = $jsonPayloads[0];
        });

        self::assertCount(1, AliasConfigDebugWorkflowFake::$calls, 'Config debug fake workflow MUST be called exactly once.');
        self::assertSame(
            ['scenario' => 'baseline.defaults_only.all_middleware_slots_present', 'key' => 'cli.commands'],
            AliasConfigDebugWorkflowFake::$calls[0]
        );

        self::assertIsArray($payload);
        self::assertSame(1, $payload['schema_version'] ?? null);
        self::assertSame('spike:config:debug', $payload['command'] ?? null);
        self::assertSame('baseline.defaults_only.all_middleware_slots_present', $payload['scenario'] ?? null);
        self::assertSame('cli.commands', $payload['key'] ?? null);
        self::assertSame(
            [
                'present' => true,
                'kind' => 'scalar',
                'meta' => [
                    'type' => 'string',
                    'length' => 4,
                ],
            ],
            $payload['resolved'] ?? null
        );

        $trace = $payload['trace'] ?? null;
        self::assertIsArray($trace);
        self::assertSame('fake:config-debug-workflow', $trace[0]['file'] ?? null);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDeptracGraphCommandDispatchesToToolsWorkflow(): void
    {
        AliasDeptracGraphWorkflowFake::reset();

        self::assertFalse(
            \class_exists('Coretsia\\Tools\\Spikes\\deptrac\\DeptracGraphWorkflow', false),
            'DeptracGraphWorkflow MUST NOT be loaded before alias proof test.'
        );

        self::assertTrue(
            \class_alias(
                AliasDeptracGraphWorkflowFake::class,
                'Coretsia\\Tools\\Spikes\\deptrac\\DeptracGraphWorkflow'
            ),
            'Failed to alias fake DeptracGraphWorkflow.'
        );

        $repoRoot = $this->repoRoot();
        $payload = null;

        $this->withLauncherAtRepoRoot($repoRoot, function () use (&$payload): void {
            $command = new DeptracGraphCommand();
            $input = $this->stubInput([
                '--json',
                '--fixture=deptrac_min/package_index_ok.php',
                '--out=framework/tools/spikes/_artifacts/_dispatch_proof',
            ]);
            $output = new DispatchProofOutput();

            $exit = $command->run($input, $output);

            self::assertSame(0, $exit, 'deptrac:graph MUST succeed through aliased tools workflow.');
            self::assertSame([], $output->errors(), 'deptrac:graph MUST NOT emit error().');

            $jsonPayloads = $output->jsonPayloads();
            self::assertCount(1, $jsonPayloads);

            $payload = $jsonPayloads[0];
        });

        self::assertCount(1, AliasDeptracGraphWorkflowFake::$calls, 'Deptrac graph fake workflow MUST be called exactly once.');

        $call = AliasDeptracGraphWorkflowFake::$calls[0];
        self::assertSame($repoRoot, $call['repoRoot']);
        self::assertSame('deptrac_min/package_index_ok.php', $call['fixture']);
        self::assertSame('framework/tools/spikes/_artifacts/_dispatch_proof', $call['outputDir']);

        self::assertIsArray($payload);
        self::assertSame(true, $payload['ok'] ?? null);
        self::assertSame('deptrac:graph', $payload['command'] ?? null);
        self::assertSame('fake/deptrac/fixture.php', $payload['fixture'] ?? null);
        self::assertSame('fake/output/dir', $payload['output_dir'] ?? null);
        self::assertSame(
            [
                'fake/output/dir/deptrac_graph.dot',
                'fake/output/dir/deptrac_graph.svg',
                'fake/output/dir/deptrac_graph.html',
            ],
            $payload['files'] ?? null
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWorkspaceSyncCommandsDispatchToToolsEntryWorkflow(): void
    {
        AliasWorkspaceSyncEntryWorkflowFake::reset();

        self::assertFalse(
            \class_exists('Coretsia\\Tools\\Spikes\\workspace\\WorkspaceSyncEntryWorkflow', false),
            'WorkspaceSyncEntryWorkflow MUST NOT be loaded before alias proof test.'
        );

        self::assertTrue(
            \class_alias(
                AliasWorkspaceSyncEntryWorkflowFake::class,
                'Coretsia\\Tools\\Spikes\\workspace\\WorkspaceSyncEntryWorkflow'
            ),
            'Failed to alias fake WorkspaceSyncEntryWorkflow.'
        );

        $repoRoot = $this->repoRoot();

        $dryRunPayload = null;
        $applyPayload = null;

        $this->withLauncherAtRepoRoot($repoRoot, function () use (&$dryRunPayload, &$applyPayload): void {
            $dryRun = new WorkspaceSyncDryRunCommand();
            $dryRunInput = $this->stubInput(['--format=json']);
            $dryRunOutput = new DispatchProofOutput();

            $dryRunExit = $dryRun->run($dryRunInput, $dryRunOutput);

            self::assertSame(0, $dryRunExit, 'workspace:sync --dry-run MUST succeed through aliased tools workflow.');
            self::assertSame([], $dryRunOutput->errors(), 'workspace:sync --dry-run MUST NOT emit error().');

            $dryRunJsonPayloads = $dryRunOutput->jsonPayloads();
            self::assertCount(1, $dryRunJsonPayloads);
            $dryRunPayload = $dryRunJsonPayloads[0];

            $apply = new WorkspaceSyncApplyCommand();
            $applyInput = $this->stubInput(['--apply']);
            $applyOutput = new DispatchProofOutput();

            $applyExit = $apply->run($applyInput, $applyOutput);

            self::assertSame(0, $applyExit, 'workspace:sync --apply MUST succeed through aliased tools workflow.');
            self::assertSame([], $applyOutput->errors(), 'workspace:sync --apply MUST NOT emit error().');

            $applyJsonPayloads = $applyOutput->jsonPayloads();
            self::assertCount(1, $applyJsonPayloads);
            $applyPayload = $applyJsonPayloads[0];
        });

        self::assertCount(2, AliasWorkspaceSyncEntryWorkflowFake::$calls, 'Workspace entry fake workflow MUST be called twice.');

        self::assertSame(
            ['workspaceRoot' => $repoRoot, 'apply' => false],
            AliasWorkspaceSyncEntryWorkflowFake::$calls[0]
        );

        self::assertSame(
            ['workspaceRoot' => $repoRoot, 'apply' => true],
            AliasWorkspaceSyncEntryWorkflowFake::$calls[1]
        );

        self::assertIsArray($dryRunPayload);
        self::assertSame('workspace:sync', $dryRunPayload['command'] ?? null);
        self::assertSame('dry-run', $dryRunPayload['mode'] ?? null);
        self::assertSame(['type' => 'repo'], $dryRunPayload['target'] ?? null);

        $dryRunChangedPaths = $dryRunPayload['changedPaths'] ?? null;
        self::assertIsArray($dryRunChangedPaths);
        self::assertSame('fake/workspace/dry-run.php', $dryRunChangedPaths[0] ?? null);

        self::assertIsArray($applyPayload);
        self::assertSame('apply', $applyPayload['mode'] ?? null);

        $applyResult = $applyPayload['result'] ?? null;
        self::assertIsArray($applyResult);

        $applyChangedPaths = $applyResult['changedPaths'] ?? null;
        self::assertIsArray($applyChangedPaths);
        self::assertSame('fake/workspace/apply.php', $applyChangedPaths[0] ?? null);
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
}

/**
 * Minimal recording OutputInterface for runtime dispatch-proof assertions.
 */
final class DispatchProofOutput implements OutputInterface
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

final class AliasFingerprintWorkflowFake
{
    public static string $fixtureRepoRootAbs = '';
    public static int $calls = 0;

    public static function reset(): void
    {
        self::$fixtureRepoRootAbs = '';
        self::$calls = 0;
    }

    /**
     * @return array{
     *   fixture_repo_root_abs:string,
     *   fingerprint:string,
     *   bucket_digests:array<string,string>
     * }
     */
    public static function run(bool $includeSnapshots = false): array
    {
        self::$calls++;

        return [
            'fixture_repo_root_abs' => self::$fixtureRepoRootAbs,
            'fingerprint' => \str_repeat('a', 64),
            'bucket_digests' => [
                'fake-bucket' => \str_repeat('b', 64),
            ],
        ];
    }
}

final class AliasConfigDebugWorkflowFake
{
    /** @var list<array{scenario:string,key:string}> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    /**
     * @return array{
     *   schema_version:int,
     *   scenario:string,
     *   key:string,
     *   resolved: array{
     *     present: bool,
     *     kind: 'scalar',
     *     meta: array{type:string,length:int}
     *   },
     *   trace: list<array{sourceType:string,file:string,keyPath:string,directiveApplied:bool}>
     * }
     */
    public static function run(string $scenarioId, string $key): array
    {
        self::$calls[] = [
            'scenario' => $scenarioId,
            'key' => $key,
        ];

        return [
            'schema_version' => 1,
            'scenario' => $scenarioId,
            'key' => $key,
            'resolved' => [
                'present' => true,
                'kind' => 'scalar',
                'meta' => [
                    'type' => 'string',
                    'length' => 4,
                ],
            ],
            'trace' => [
                [
                    'sourceType' => 'fake',
                    'file' => 'fake:config-debug-workflow',
                    'keyPath' => $key,
                    'directiveApplied' => false,
                ],
            ],
        ];
    }
}

final class AliasDeptracGraphWorkflowFake
{
    /** @var list<array{repoRoot:string,fixture:?string,outputDir:string}> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    /**
     * @return array{
     *   fixture:string,
     *   output_dir:string,
     *   files:list<string>
     * }
     */
    public static function run(string $repoRootAbs, ?string $fixtureRelativePath, string $outputDirRelative): array
    {
        self::$calls[] = [
            'repoRoot' => \rtrim(\str_replace('\\', '/', $repoRootAbs), '/'),
            'fixture' => $fixtureRelativePath,
            'outputDir' => $outputDirRelative,
        ];

        return [
            'fixture' => 'fake/deptrac/fixture.php',
            'output_dir' => 'fake/output/dir',
            'files' => [
                'fake/output/dir/deptrac_graph.dot',
                'fake/output/dir/deptrac_graph.svg',
                'fake/output/dir/deptrac_graph.html',
            ],
        ];
    }
}

final class AliasWorkspaceSyncEntryWorkflowFake
{
    /** @var list<array{workspaceRoot:string,apply:bool}> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    /**
     * @return array{
     *   mode:'dry-run'|'apply',
     *   result: array{
     *     changedPaths:list<string>,
     *     files:list<array{
     *       path:string,
     *       changed:bool,
     *       oldLen:int,
     *       newLen:int,
     *       oldSha256:string,
     *       newSha256:string
     *     }>,
     *     packageIndex:array{count:int,sha256:string}
     *   }
     * }
     */
    public static function run(string $workspaceRoot, bool $apply): array
    {
        $workspaceRoot = \rtrim(\str_replace('\\', '/', $workspaceRoot), '/');

        self::$calls[] = [
            'workspaceRoot' => $workspaceRoot,
            'apply' => $apply,
        ];

        $path = $apply ? 'fake/workspace/apply.php' : 'fake/workspace/dry-run.php';

        return [
            'mode' => $apply ? 'apply' : 'dry-run',
            'result' => [
                'changedPaths' => [$path],
                'files' => [
                    [
                        'path' => $path,
                        'changed' => true,
                        'oldLen' => 1,
                        'newLen' => 2,
                        'oldSha256' => \str_repeat('c', 64),
                        'newSha256' => \str_repeat('d', 64),
                    ],
                ],
                'packageIndex' => [
                    'count' => 1,
                    'sha256' => \str_repeat('e', 64),
                ],
            ],
        ];
    }
}
