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
use Coretsia\Tools\Spikes\fingerprint\FingerprintWorkflow;

/**
 * `coretsia spike:fingerprint`
 *
 * Phase 0 intent:
 * - deterministic safe dispatch to the tools-only fingerprint spike
 * - MUST dispatch through SpikesBootstrap (single canonical entrypoint)
 * - MUST NOT leak absolute paths or dotenv values (only hashes/len are allowed)
 * - MUST NOT emit stdout/stderr (OutputInterface only)
 */
final class SpikeFingerprintCommand implements CommandInterface
{
    private const string NAME = 'spike:fingerprint';
    private const string MSG_WORKFLOW_MISSING = 'fingerprint-workflow-missing';
    private const string MSG_RESULT_INVALID = 'fingerprint-workflow-result-invalid';

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            $paths = SpikesPaths::fromServerGlobals();

            // Canonical tools-only entrypoint (single-choice).
            SpikesBootstrap::requireOnce($paths);

            if (!\class_exists(FingerprintWorkflow::class)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_WORKFLOW_MISSING);

                return SpikesExitCodeMapper::failure();
            }

            /** @var mixed $result */
            $result = FingerprintWorkflow::run(false);

            if (!self::isValidWorkflowResult($result)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_RESULT_INVALID);

                return SpikesExitCodeMapper::failure();
            }

            $output->json([
                'command' => self::NAME,
                'ok' => true,
                'fixture_repo_root' => $paths->displayPath($result['fixture_repo_root_abs']),
                'fingerprint' => $result['fingerprint'],
                'bucket_digests' => $result['bucket_digests'],
            ]);

            return SpikesExitCodeMapper::success();
        } catch (SpikesBootstrapFailedException $e) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, $e->reason());

            return SpikesExitCodeMapper::failure();
        } catch (DeterministicException $e) {
            $output->error($e->code(), $e->getMessage());

            return SpikesExitCodeMapper::failure();
        } catch (\Throwable) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, 'uncaught-exception');

            return SpikesExitCodeMapper::failure();
        }
    }

    private static function isValidWorkflowResult(mixed $result): bool
    {
        if (!\is_array($result) || \array_is_list($result)) {
            return false;
        }

        if (
            !isset($result['fixture_repo_root_abs'], $result['fingerprint'], $result['bucket_digests'])
            || !\is_string($result['fixture_repo_root_abs'])
            || !\is_string($result['fingerprint'])
            || !\is_array($result['bucket_digests'])
        ) {
            return false;
        }

        if (\preg_match('/\A[a-f0-9]{64}\z/', $result['fingerprint']) !== 1) {
            return false;
        }

        foreach ($result['bucket_digests'] as $key => $value) {
            if (!\is_string($key) || $key === '' || !\is_string($value)) {
                return false;
            }

            if (\preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
                return false;
            }
        }

        return true;
    }
}
