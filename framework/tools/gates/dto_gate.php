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

use Coretsia\Tools\Spikes\_support\ConsoleOutput;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

require_once __DIR__ . '/../spikes/_support/bootstrap.php';

$frameworkRoot = str_replace('\\', '/', dirname(__DIR__, 2));

$subGates = [
    'tools/gates/dto_marker_consistency_gate.php',
    'tools/gates/dto_no_logic_gate.php',
    'tools/gates/dto_shape_gate.php',
];

foreach ($subGates as $subGate) {
    $subGatePath = $frameworkRoot . '/' . $subGate;

    if (!is_file($subGatePath)) {
        continue;
    }

    $pipes = [];
    $process = @proc_open(
        [PHP_BINARY, $subGatePath],
        [
            0 => ['pipe', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ],
        $pipes,
        $frameworkRoot,
    );

    if (!is_resource($process)) {
        ConsoleOutput::codeWithDiagnostics(
            ErrorCodes::CORETSIA_DTO_GATE_FAILED,
            [
                $subGate . ': dto_sub_gate_process_start_failed',
            ],
        );

        exit(1);
    }

    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        exit($exitCode > 0 && $exitCode <= 255 ? $exitCode : 1);
    }
}

exit(0);
