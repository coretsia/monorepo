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
use Coretsia\Platform\Cli\Error\ErrorCodes as CliErrorCodes;
use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes as SpikeErrorCodes;
use Coretsia\Tools\Spikes\config_merge\ConfigDebugWorkflow;

/**
 * Phase 0 spike command: debug config merge trace for a dot-key.
 *
 * Contract:
 * - MUST dispatch through SpikesBootstrap (tools-only code).
 * - MUST NOT print to stdout/stderr; OutputInterface is the single authority.
 * - MUST be deterministic and safe (no secret values; no absolute paths).
 * - MUST NOT implement config_merge spike logic locally.
 *
 * CLI:
 * - coretsia spike:config:debug --key=<dot.key> [--scenario=<scenario-id>]
 */
final class SpikeConfigDebugCommand implements CommandInterface
{
    /**
     * Cemented default scenario for deterministic output.
     */
    private const string DEFAULT_SCENARIO_ID = 'baseline.defaults_only.all_middleware_slots_present';

    private const string MSG_WORKFLOW_MISSING = 'config-debug-workflow-missing';
    private const string MSG_RESULT_INVALID = 'config-debug-result-invalid';

    public function name(): string
    {
        return 'spike:config:debug';
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $tokens = $input->tokens();

        $key = self::normalizeKey(self::readOptionValue($tokens, 'key'));
        $scenarioId = self::normalizeScenarioId(
            self::readOptionValue($tokens, 'scenario') ?? self::DEFAULT_SCENARIO_ID
        );

        if ($key === null) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_INVALID, 'missing-required-flag-key');
            return SpikesExitCodeMapper::failure();
        }

        if ($scenarioId === null) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_INVALID, 'scenario-id-invalid');
            return SpikesExitCodeMapper::failure();
        }

        try {
            SpikesBootstrap::requireOnceFromGlobals();
        } catch (SpikesBootstrapFailedException $e) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, $e->reason());
            return SpikesExitCodeMapper::failure();
        } catch (\Throwable) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, 'unexpected-bootstrap-failure');
            return SpikesExitCodeMapper::failure();
        }

        try {
            if (!\class_exists(ConfigDebugWorkflow::class)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_WORKFLOW_MISSING);
                return SpikesExitCodeMapper::failure();
            }

            /** @var mixed $result */
            $result = ConfigDebugWorkflow::run($scenarioId, $key);

            if (!self::isValidWorkflowResult($result)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_RESULT_INVALID);
                return SpikesExitCodeMapper::failure();
            }

            $payload = [
                'schema_version' => $result['schema_version'],
                'command' => $this->name(),
                'scenario' => $result['scenario'],
                'key' => $result['key'],
                'resolved' => $result['resolved'],
                'trace' => $result['trace'],
            ];

            $output->json($payload);

            return SpikesExitCodeMapper::success();
        } catch (DeterministicException $e) {
            if (
                $e->code() === SpikeErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID
                && self::isCliConfigInvalidMessage($e->getMessage())
            ) {
                $output->error(CliErrorCodes::CORETSIA_CLI_CONFIG_INVALID, $e->getMessage());
                return SpikesExitCodeMapper::failure();
            }

            $output->error($e->code(), $e->getMessage());
            return SpikesExitCodeMapper::failure();
        } catch (\Throwable) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, 'unexpected-exception');
            return SpikesExitCodeMapper::failure();
        }
    }

    /**
     * Supports:
     * - --key=value
     * - --key value
     */
    private static function readOptionValue(array $tokens, string $name): ?string
    {
        $needleEq = '--' . $name . '=';

        $n = \count($tokens);
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i] ?? null;
            if (!\is_string($t) || $t === '') {
                continue;
            }

            if (\str_starts_with($t, $needleEq)) {
                $v = \substr($t, \strlen($needleEq));
                return \is_string($v) ? $v : null;
            }

            if ($t === '--' . $name) {
                $next = $tokens[$i + 1] ?? null;
                return \is_string($next) ? $next : null;
            }
        }

        return null;
    }

    private static function normalizeKey(?string $key): ?string
    {
        if (!\is_string($key)) {
            return null;
        }

        $key = \trim($key);
        if ($key === '') {
            return null;
        }

        if (\str_contains($key, "\0") || \str_contains($key, "\r") || \str_contains($key, "\n")) {
            return null;
        }

        if (\preg_match('/\A[A-Za-z0-9._-]+\z/', $key) !== 1) {
            return null;
        }

        return $key;
    }

    private static function normalizeScenarioId(string $scenarioId): ?string
    {
        $scenarioId = \trim($scenarioId);
        if ($scenarioId === '') {
            return null;
        }

        if (\str_contains($scenarioId, "\0") || \str_contains($scenarioId, "\r") || \str_contains($scenarioId, "\n")) {
            return null;
        }

        if (\preg_match('/\A[A-Za-z0-9._:-]+\z/', $scenarioId) !== 1) {
            return null;
        }

        return $scenarioId;
    }

    private static function isCliConfigInvalidMessage(string $message): bool
    {
        return \in_array(
            $message,
            [
                'unknown-scenario-id',
                'config-merge-scenarios-missing',
                'config-merge-scenarios-invalid',
                'scenario-inputs-missing',
                'scenario-inputs-shape-invalid',
            ],
            true
        );
    }

    private static function isValidWorkflowResult(mixed $result): bool
    {
        if (!\is_array($result) || \array_is_list($result)) {
            return false;
        }

        return isset(
                $result['schema_version'],
                $result['scenario'],
                $result['key'],
                $result['resolved'],
                $result['trace'],
            )
            && \is_int($result['schema_version'])
            && \is_string($result['scenario'])
            && \is_string($result['key'])
            && \is_array($result['resolved'])
            && \is_array($result['trace']);
    }
}
