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

final class WorkspaceSyncApplyCommand implements CommandInterface
{
    private const string COMMAND_SIGNATURE = 'workspace:sync --apply';
    private const string COMMAND_ID = 'workspace:sync';
    private const string MODE = 'apply';

    private const string MSG_REPO_ROOT_UNRESOLVABLE = 'repo-root-unresolvable';
    private const string MSG_ENTRY_WORKFLOW_MISSING = 'workspace-sync-entry-workflow-missing';
    private const string MSG_ENTRY_RESULT_INVALID = 'workspace-sync-entry-result-invalid';
    private const string MSG_UNHANDLED_THROWABLE = 'workspace-sync-failed';

    public function name(): string
    {
        return self::COMMAND_SIGNATURE;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            $paths = SpikesPaths::fromServerGlobals();

            SpikesBootstrap::requireOnce($paths);

            $workspaceRoot = \rtrim(\str_replace('\\', '/', $paths->repoRoot()), '/');

            if ($workspaceRoot === '' || !\is_dir($workspaceRoot)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_REPO_ROOT_UNRESOLVABLE);

                return SpikesExitCodeMapper::failure();
            }

            if (!\class_exists(WorkspaceSyncEntryWorkflow::class)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_ENTRY_WORKFLOW_MISSING);

                return SpikesExitCodeMapper::failure();
            }

            /** @var mixed $entry */
            $entry = WorkspaceSyncEntryWorkflow::run($workspaceRoot, true);

            if (!self::isValidEntry($entry, self::MODE)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_ENTRY_RESULT_INVALID);

                return SpikesExitCodeMapper::failure();
            }

            $output->json([
                'command' => self::COMMAND_ID,
                'mode' => $entry['mode'],
                'result' => $entry['result'],
            ]);

            return SpikesExitCodeMapper::success();
        } catch (SpikesBootstrapFailedException $e) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, $e->reason());

            return SpikesExitCodeMapper::failure();
        } catch (DeterministicException $e) {
            $output->error($e->code(), $e->getMessage());

            return SpikesExitCodeMapper::failure();
        } catch (\Throwable) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_UNHANDLED_THROWABLE);

            return SpikesExitCodeMapper::failure();
        }
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
}
