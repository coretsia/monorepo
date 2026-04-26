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

(static function (): void {
    /**
     * Execute callable with warnings/notices suppressed (no output pollution).
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    $withSuppressedErrors = static function (callable $fn) {
        \set_error_handler(static function (): bool {
            return true;
        });

        try {
            return $fn();
        } finally {
            \restore_error_handler();
        }
    };

    $toolsRootRuntime = $withSuppressedErrors(static function (): ?string {
        $p = \realpath(__DIR__ . '/..');
        return \is_string($p) ? $p : null;
    });

    if ($toolsRootRuntime === null) {
        $fallbackConsole = __DIR__ . '/../spikes/_support/ConsoleOutput.php';
        if (\is_file($fallbackConsole) && \is_readable($fallbackConsole)) {
            require_once $fallbackConsole;

            \Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics(
                'CORETSIA_DTO_GATE_FAILED',
                [],
            );
        }

        exit(1);
    }

    $bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php';
    $consoleFile = $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php';
    $errorCodesFile = $toolsRootRuntime . '/spikes/_support/ErrorCodes.php';

    /** @var class-string $ConsoleOutput */
    $ConsoleOutput = 'Coretsia\\Tools\\Spikes\\_support\\ConsoleOutput';

    /** @var class-string $ErrorCodes */
    $ErrorCodes = 'Coretsia\\Tools\\Spikes\\_support\\ErrorCodes';

    $fallbackGateFailed = 'CORETSIA_DTO_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackGateFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $code = coretsia_dto_gate_error_code_or_fallback(
                    $ErrorCodes,
                    'CORETSIA_DTO_GATE_FAILED',
                    $code,
                );
            }

            $ConsoleOutput::codeWithDiagnostics($code, []);
        }

        exit(1);
    }

    require_once $bootstrap;

    if (\is_file($consoleFile) && \is_readable($consoleFile)) {
        require_once $consoleFile;
    }

    if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
        require_once $errorCodesFile;
    }

    $codeGateFailed = coretsia_dto_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_DTO_GATE_FAILED',
        $fallbackGateFailed,
    );

    try {
        $frameworkRoot = $withSuppressedErrors(static function (): ?string {
            $p = \realpath(__DIR__ . '/..' . '/..');
            return \is_string($p) ? $p : null;
        });

        if ($frameworkRoot === null || $frameworkRoot === '') {
            throw new \RuntimeException('framework-root-invalid');
        }

        $frameworkRoot = \rtrim(\str_replace('\\', '/', $frameworkRoot), '/');

        $subGates = [
            'tools/gates/dto_marker_consistency_gate.php',
            'tools/gates/dto_no_logic_gate.php',
            'tools/gates/dto_shape_gate.php',
        ];

        foreach ($subGates as $subGate) {
            $subGatePath = $frameworkRoot . '/' . $subGate;

            if (!\is_file($subGatePath)) {
                continue;
            }

            $processResult = $withSuppressedErrors(
                static fn () => coretsia_dto_gate_open_sub_gate_process($subGatePath, $frameworkRoot),
            );

            $process = $processResult[0];
            $pipes = $processResult[1];

            if (!\is_resource($process)) {
                $ConsoleOutput::codeWithDiagnostics(
                    $codeGateFailed,
                    [
                        $subGate . ': dto_sub_gate_process_start_failed',
                    ],
                );

                exit(1);
            }

            $stdin = $pipes[0] ?? null;

            if (\is_resource($stdin)) {
                $withSuppressedErrors(static function () use ($stdin): void {
                    \fclose($stdin);
                });
            }

            $exitCode = \proc_close($process);

            if ($exitCode !== 0) {
                exit($exitCode > 0 && $exitCode <= 255 ? $exitCode : 1);
            }
        }

        exit(0);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeGateFailed, []);
        }

        exit(1);
    }
})();

/**
 * @return array{0: resource|false, 1: array<int, resource>}
 */
function coretsia_dto_gate_open_sub_gate_process(string $subGatePath, string $frameworkRoot): array
{
    /** @var array<int, resource> $pipes */
    $pipes = [];

    $process = \proc_open(
        [PHP_BINARY, $subGatePath],
        [
            0 => ['pipe', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ],
        $pipes,
        $frameworkRoot,
    );

    return [$process, $pipes];
}

function coretsia_dto_gate_error_code_or_fallback(string $errorCodesFqcn, string $constantName, string $fallback): string
{
    $name = $errorCodesFqcn . '::' . $constantName;

    if (\defined($name)) {
        /** @var string $code */
        $code = \constant($name);

        return $code;
    }

    return $fallback;
}
