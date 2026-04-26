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

namespace Coretsia\Devtools\CliSpikes\Command;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrap;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrapFailedException;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesExitCodeMapper;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesPaths;
use Coretsia\Platform\Cli\Error\ErrorCodes as CliErrorCodes;
use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\workspace\WorkspaceSyncEntryWorkflow;

final class WorkspaceSyncDryRunCommand implements CommandInterface
{
    private const string COMMAND_SIGNATURE = 'workspace:sync --dry-run';
    private const string COMMAND_ID = 'workspace:sync';
    private const string MODE = 'dry-run';

    private const string MSG_INVALID_ARGUMENTS = 'invalid-arguments';
    private const string MSG_SPIKES_BOOTSTRAP_FAILED = 'spikes-bootstrap-failed';
    private const string MSG_REPO_ROOT_UNAVAILABLE = 'repo-root-unavailable';
    private const string MSG_FIXTURE_INVALID = 'fixture-invalid';
    private const string MSG_WORKSPACE_ROOT_INVALID = 'workspace-root-invalid';
    private const string MSG_ENTRY_WORKFLOW_MISSING = 'workspace-sync-entry-workflow-missing';
    private const string MSG_ENTRY_RESULT_INVALID = 'workspace-sync-entry-result-invalid';
    private const string MSG_UNHANDLED_THROWABLE = 'workspace-sync-failed';

    public function name(): string
    {
        return self::COMMAND_SIGNATURE;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $tokens = $input->tokens();

        try {
            $options = self::parseOptions($tokens);
        } catch (\Throwable) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_INVALID, self::MSG_INVALID_ARGUMENTS);

            return SpikesExitCodeMapper::failure();
        }

        try {
            SpikesBootstrap::requireOnceFromGlobals();
        } catch (SpikesBootstrapFailedException $e) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, $e->reason());

            return SpikesExitCodeMapper::failure();
        } catch (\Throwable) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_SPIKES_BOOTSTRAP_FAILED);

            return SpikesExitCodeMapper::failure();
        }

        try {
            $paths = SpikesPaths::fromServerGlobals();
            $repoRoot = self::normalizePath($paths->repoRoot());
            $repoRoot = \rtrim($repoRoot, '/');

            if ($repoRoot === '') {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_REPO_ROOT_UNAVAILABLE);

                return SpikesExitCodeMapper::failure();
            }

            $fixture = $options['fixture'];

            $target = [
                'type' => $fixture === null ? 'repo' : 'fixture',
            ];
            if ($fixture !== null) {
                $target['name'] = $fixture;
            }

            $workspaceRoot = $fixture === null
                ? $repoRoot
                : self::joinPath($repoRoot, 'framework/tools/spikes/fixtures/' . $fixture);

            if (!\is_dir($workspaceRoot)) {
                if ($fixture !== null) {
                    $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_INVALID, self::MSG_FIXTURE_INVALID);

                    return SpikesExitCodeMapper::failure();
                }

                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_WORKSPACE_ROOT_INVALID);

                return SpikesExitCodeMapper::failure();
            }

            if (!\class_exists(WorkspaceSyncEntryWorkflow::class)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_ENTRY_WORKFLOW_MISSING);

                return SpikesExitCodeMapper::failure();
            }

            $entry = WorkspaceSyncEntryWorkflow::run($workspaceRoot, false);

            if (!self::isValidEntry($entry, self::MODE)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_ENTRY_RESULT_INVALID);

                return SpikesExitCodeMapper::failure();
            }

            $payload = self::buildPayload($target, $entry);

            if ($options['format'] === 'text') {
                self::renderText($output, $payload);
            } else {
                $output->json($payload);
            }

            return SpikesExitCodeMapper::success();
        } catch (DeterministicException $e) {
            $output->error($e->code(), $e->getMessage());

            return SpikesExitCodeMapper::failure();
        } catch (\Throwable) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_UNHANDLED_THROWABLE);

            return SpikesExitCodeMapper::failure();
        }
    }

    /**
     * @param list<string> $tokens
     * @return array{fixture:?string,format:'json'|'text'}
     */
    private static function parseOptions(array $tokens): array
    {
        $fixture = null;
        $format = 'json';

        $n = \count($tokens);
        for ($i = 0; $i < $n; $i++) {
            $t = (string)$tokens[$i];

            if (\str_starts_with($t, '--fixture=')) {
                $fixture = (string)\substr($t, \strlen('--fixture='));
                continue;
            }

            if ($t === '--fixture') {
                $fixture = ($i + 1) < $n ? (string)$tokens[$i + 1] : '';
                $i++;
                continue;
            }

            if (\str_starts_with($t, '--format=')) {
                $format = (string)\substr($t, \strlen('--format='));
                continue;
            }

            if ($t === '--format') {
                $format = ($i + 1) < $n ? (string)$tokens[$i + 1] : '';
                $i++;
                continue;
            }

            if ($t === '--json') {
                $format = 'json';
                continue;
            }

            if ($t === '--text') {
                $format = 'text';
                continue;
            }
        }

        if ($fixture !== null) {
            $fixture = self::normalizePath($fixture);

            if (
                $fixture === ''
                || \str_contains($fixture, '/')
                || \preg_match('/^[A-Za-z0-9_-]+$/', $fixture) !== 1
            ) {
                throw new \RuntimeException(self::MSG_INVALID_ARGUMENTS);
            }
        }

        $format = self::normalizePath($format);
        if ($format !== 'json' && $format !== 'text') {
            throw new \RuntimeException(self::MSG_INVALID_ARGUMENTS);
        }

        return [
            'fixture' => $fixture,
            'format' => $format,
        ];
    }

    private static function isValidEntry(mixed $entry, string $expectedMode): bool
    {
        if (!\is_array($entry) || \array_is_list($entry)) {
            return false;
        }

        if (($entry['mode'] ?? null) !== $expectedMode) {
            return false;
        }

        $result = $entry['result'] ?? null;

        return \is_array($result) && !\array_is_list($result);
    }

    /**
     * @param array{type:'repo'|'fixture',name?:string} $target
     * @param array{
     *   mode:'dry-run',
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
     * } $entry
     * @return array<string, mixed>
     */
    private static function buildPayload(array $target, array $entry): array
    {
        $payload = [];
        $payload['command'] = self::COMMAND_ID;
        $payload['mode'] = $entry['mode'];
        $payload['target'] = $target;
        $payload['changedPaths'] = $entry['result']['changedPaths'];
        $payload['files'] = $entry['result']['files'];
        $payload['packageIndex'] = $entry['result']['packageIndex'];

        return $payload;
    }

    private static function renderText(OutputInterface $output, array $payload): void
    {
        $output->text(self::COMMAND_ID . ' (' . self::MODE . ')');

        $target = $payload['target'] ?? [];
        if (\is_array($target) && ($target['type'] ?? null) === 'fixture' && \is_string($target['name'] ?? null)) {
            $output->text('target: fixture:' . (string)$target['name']);
        } else {
            $output->text('target: repo');
        }

        $changed = $payload['changedPaths'] ?? [];
        if (!\is_array($changed) || $changed === []) {
            $output->text('changedPaths: (none)');
            return;
        }

        $output->text('changedPaths:');
        foreach ($changed as $p) {
            if (!\is_string($p) || $p === '') {
                continue;
            }
            $output->text('- ' . $p);
        }
    }

    private static function joinPath(string $base, string $rel): string
    {
        $b = \rtrim(self::normalizePath($base), '/');
        $r = \ltrim(self::normalizePath($rel), '/');

        return $r === '' ? $b : ($b . '/' . $r);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }
}
