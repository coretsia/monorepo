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
use Coretsia\Tools\Spikes\deptrac\DeptracGraphWorkflow;

final class DeptracGraphCommand implements CommandInterface
{
    private const string NAME = 'deptrac:graph';

    private const string DEFAULT_FIXTURE = 'deptrac_min/package_index_ok.php';
    private const string DEFAULT_OUT_DIR_REL = 'framework/tools/spikes/_artifacts/deptrac_graph';

    private const string MSG_WORKFLOW_MISSING = 'deptrac-graph-workflow-missing';
    private const string MSG_RESULT_INVALID = 'deptrac-graph-result-invalid';

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $args = self::parseArgs($input->tokens());

        if ($args['help'] === true) {
            self::renderHelp($output);

            return SpikesExitCodeMapper::success();
        }

        $fixtureRel = self::normalizeFixtureRelOrNull($args['fixture']);
        $outRel = self::normalizeRepoRelativePathOrNull($args['out']) ?? self::DEFAULT_OUT_DIR_REL;

        if ($fixtureRel !== null && !\str_starts_with($fixtureRel, 'deptrac_min/')) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_INVALID, 'fixture-must-be-under-deptrac-min');

            return SpikesExitCodeMapper::failure();
        }

        if ($outRel === '') {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_INVALID, 'output-relpath-invalid');

            return SpikesExitCodeMapper::failure();
        }

        try {
            $paths = SpikesPaths::fromServerGlobals();
            SpikesBootstrap::requireOnce($paths);

            if (!\class_exists(DeptracGraphWorkflow::class)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_WORKFLOW_MISSING);

                return SpikesExitCodeMapper::failure();
            }

            /** @var mixed $result */
            $result = DeptracGraphWorkflow::run(
                $paths->repoRoot(),
                $fixtureRel,
                $outRel,
            );

            if (!self::isValidWorkflowResult($result)) {
                $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, self::MSG_RESULT_INVALID);

                return SpikesExitCodeMapper::failure();
            }

            if ($args['json'] === true) {
                $output->json([
                    'ok' => true,
                    'command' => self::NAME,
                    'fixture' => $result['fixture'],
                    'output_dir' => $result['output_dir'],
                    'files' => $result['files'],
                ]);

                return SpikesExitCodeMapper::success();
            }

            $output->text('ok');
            $output->text('command: ' . self::NAME);
            $output->text('fixture: ' . $result['fixture']);
            $output->text('output_dir: ' . $result['output_dir']);
            $output->text('files:');

            foreach ($result['files'] as $file) {
                $output->text('  - ' . $file);
            }

            return SpikesExitCodeMapper::success();
        } catch (SpikesBootstrapFailedException $e) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, $e->reason());

            return SpikesExitCodeMapper::failure();
        } catch (DeterministicException $e) {
            $output->error($e->code(), $e->getMessage());

            return SpikesExitCodeMapper::failure();
        } catch (\Throwable) {
            $output->error(CliErrorCodes::CORETSIA_CLI_COMMAND_FAILED, 'unhandled-exception');

            return SpikesExitCodeMapper::failure();
        }
    }

    /**
     * @param list<string> $tokens
     * @return array{fixture:?string,out:?string,json:bool,help:bool}
     */
    private static function parseArgs(array $tokens): array
    {
        $fixture = null;
        $out = null;
        $json = false;
        $help = false;

        foreach ($tokens as $t) {
            if ($t === '--json') {
                $json = true;
                continue;
            }

            if ($t === '--help' || $t === '-h') {
                $help = true;
                continue;
            }

            if (\str_starts_with($t, '--fixture=')) {
                $fixture = \substr($t, \strlen('--fixture='));
                continue;
            }

            if (\str_starts_with($t, '--out=')) {
                $out = \substr($t, \strlen('--out='));
                continue;
            }
        }

        return [
            'fixture' => $fixture,
            'out' => $out,
            'json' => $json,
            'help' => $help,
        ];
    }

    private static function renderHelp(OutputInterface $output): void
    {
        $output->text(
            'usage: coretsia '
            . self::NAME
            . ' [--fixture=deptrac_min/package_index_ok.php] [--out=framework/tools/spikes/_artifacts/deptrac_graph] [--json]'
        );
        $output->text('notes:');
        $output->text('  - --fixture MUST be under deptrac_min/');
        $output->text('  - --out MUST be repo-relative (no absolute paths, no "..")');
        $output->text('outputs: deptrac_graph.dot, deptrac_graph.svg, deptrac_graph.html');
    }

    private static function normalizeFixtureRelOrNull(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $s = \trim($raw);
        if ($s === '') {
            return null;
        }

        $s = \str_replace('\\', '/', $s);
        $s = \ltrim($s, '/');

        while (\str_starts_with($s, './')) {
            $s = \substr($s, 2);
        }

        return $s !== '' ? $s : null;
    }

    private static function normalizeRepoRelativePathOrNull(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $s = \trim($raw);
        if ($s === '') {
            return null;
        }

        if (\str_contains($s, '\\')) {
            return null;
        }

        $s = \str_replace('\\', '/', $s);
        $s = \ltrim($s, '/');

        if (
            \str_starts_with($s, '/')
            || \str_starts_with($s, '//')
            || \str_contains($s, '://')
            || \preg_match('~(?i)\A[A-Z]:/~', $s) === 1
            || \preg_match('~(?i)\A[A-Z]:[\\\\/]~', $s) === 1
        ) {
            return null;
        }

        while (\str_starts_with($s, './')) {
            $s = \substr($s, 2);
        }

        $s = \preg_replace('~/+~', '/', $s);
        if (!\is_string($s) || $s === '') {
            return null;
        }

        if ($s === '.' || $s === '..' || \str_starts_with($s, '../') || \str_contains($s, '/../')) {
            return null;
        }

        return $s;
    }

    private static function isValidWorkflowResult(mixed $result): bool
    {
        if (!\is_array($result) || \array_is_list($result)) {
            return false;
        }

        if (
            !isset($result['fixture'], $result['output_dir'], $result['files'])
            || !\is_string($result['fixture'])
            || !\is_string($result['output_dir'])
            || !\is_array($result['files'])
        ) {
            return false;
        }

        foreach ($result['files'] as $file) {
            if (!\is_string($file) || $file === '') {
                return false;
            }
        }

        return true;
    }
}
