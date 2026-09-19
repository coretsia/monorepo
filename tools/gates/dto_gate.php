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

use Coretsia\Tools\Support\ConsoleOutput;
use Coretsia\Tools\Support\ErrorCodes;
use Coretsia\Tools\Support\GateRuntime;
use Coretsia\Tools\Support\RepositoryContext;

require_once __DIR__ . '/../support/ConsoleOutput.php';
require_once __DIR__ . '/../support/ErrorCodes.php';
require_once __DIR__ . '/../support/GateRuntime.php';
require_once __DIR__ . '/../support/RepositoryContext.php';

(static function (array $argv): void {
    try {
        $repository = RepositoryContext::discoverFrom(__DIR__);
        $scanRoot = GateRuntime::resolveOptionalRepositoryScanRoot(
            $repository,
            $argv,
        );

        $subGates = [
            'tools/gates/dto_shape_gate.php',
            'tools/gates/dto_marker_consistency_gate.php',
            'tools/gates/dto_no_logic_gate.php',
        ];

        foreach ($subGates as $subGate) {
            $subGateCandidate = $repository->resolve($subGate);

            if (!\is_file($subGateCandidate)) {
                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_DTO_GATE_FAILED,
                    [
                        $subGate . ': dto_sub_gate_missing',
                    ],
                );

                exit(1);
            }

            if (!\is_readable($subGateCandidate)) {
                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_DTO_GATE_FAILED,
                    [
                        $subGate . ': dto_sub_gate_unreadable',
                    ],
                );

                exit(1);
            }

            $subGatePath = $repository->resolveExistingFile($subGateCandidate);

            $processResult = GateRuntime::withSuppressedErrors(
                static fn () => coretsia_dto_gate_open_sub_gate_process(
                    $subGatePath,
                    $repository->repoRoot(),
                    $scanRoot,
                ),
            );

            $process = $processResult[0];
            $pipes = $processResult[1];

            if (!\is_resource($process)) {
                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_DTO_GATE_FAILED,
                    [
                        $subGate . ': dto_sub_gate_process_start_failed',
                    ],
                );

                exit(1);
            }

            $stdin = $pipes[0] ?? null;

            if (\is_resource($stdin)) {
                GateRuntime::withSuppressedErrors(
                    static function () use ($stdin): void {
                        \fclose($stdin);
                    },
                );
            }

            $exitCode = \proc_close($process);

            if ($exitCode !== 0) {
                exit($exitCode > 0 && $exitCode <= 255 ? $exitCode : 1);
            }
        }

        exit(0);
    } catch (\Throwable) {
        ConsoleOutput::codeWithDiagnostics(
            ErrorCodes::CORETSIA_DTO_GATE_FAILED,
            [],
        );

        exit(1);
    }
})(
    isset($_SERVER['argv']) && \is_array($_SERVER['argv'])
        ? $_SERVER['argv']
        : [],
);

/**
 * @return array{0: resource|false, 1: array<int, resource>}
 */
function coretsia_dto_gate_open_sub_gate_process(
    string $subGatePath,
    string $repoRoot,
    ?string $scanRoot,
): array {
    /** @var array<int, resource> $pipes */
    $pipes = [];

    $command = [
        PHP_BINARY,
        $subGatePath,
    ];

    if ($scanRoot !== null) {
        $command[] = '--path=' . $scanRoot;
    }

    $process = \proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ],
        $pipes,
        $repoRoot,
    );

    return [$process, $pipes];
}
