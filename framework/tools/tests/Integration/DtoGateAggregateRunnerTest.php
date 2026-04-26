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

namespace Coretsia\Tools\Tests\Integration;

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;
use RuntimeException;

final class DtoGateAggregateRunnerTest extends ToolContractTestCase
{
    /**
     * @var list<string>
     */
    private const array SUB_GATES = [
        'dto_marker_consistency_gate.php',
        'dto_no_logic_gate.php',
        'dto_shape_gate.php',
    ];

    public function testAggregateRunnerInvokesMaterializedSubGatesInDeterministicOrder(): void
    {
        $logPath = $this->tempDir('coretsia-dto-gate-log') . '/order.log';

        $this->withTemporaryDtoSubGates(
            [
                'dto_marker_consistency_gate.php' => $this->passingSubGate($logPath, 'marker'),
                'dto_shape_gate.php' => $this->passingSubGate($logPath, 'shape'),
            ],
            function () use ($logPath): void {
                [$code, $output] = $this->runDtoGate();

                self::assertSame(0, $code);
                self::assertSame('', $output);
                self::assertSame("marker\nshape\n", $this->readBytes($logPath));
            },
        );
    }

    public function testAggregateRunnerStopsOnFirstFailureAndPassesOutputThroughUnchanged(): void
    {
        $logPath = $this->tempDir('coretsia-dto-gate-log') . '/failure.log';
        $expectedOutput = "CORETSIA_FAKE_DTO_FAILURE\nframework/packages/demo/src/BrokenDto.php: fake_reason\n";

        $this->withTemporaryDtoSubGates(
            [
                'dto_marker_consistency_gate.php' => $this->passingSubGate($logPath, 'marker'),
                'dto_no_logic_gate.php' => $this->failingSubGate($logPath, 'no-logic', $expectedOutput, 37),
                'dto_shape_gate.php' => $this->passingSubGate($logPath, 'shape'),
            ],
            function () use ($logPath, $expectedOutput): void {
                [$code, $output] = $this->runDtoGate();

                self::assertSame(37, $code);
                self::assertSame($expectedOutput, $output);
                self::assertSame("marker\nno-logic\n", $this->readBytes($logPath));
            },
        );
    }

    public function testAggregateRunnerSuccessExitsZeroAndPrintsNothing(): void
    {
        $logPath = $this->tempDir('coretsia-dto-gate-log') . '/success.log';

        $this->withTemporaryDtoSubGates(
            [
                'dto_marker_consistency_gate.php' => $this->passingSubGate($logPath, 'marker'),
                'dto_no_logic_gate.php' => $this->passingSubGate($logPath, 'no-logic'),
                'dto_shape_gate.php' => $this->passingSubGate($logPath, 'shape'),
            ],
            function () use ($logPath): void {
                [$code, $output] = $this->runDtoGate();

                self::assertSame(0, $code);
                self::assertSame('', $output);
                self::assertSame("marker\nno-logic\nshape\n", $this->readBytes($logPath));
            },
        );
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runDtoGate(): array
    {
        return $this->runPhp(
            $this->frameworkRoot() . '/tools/gates/dto_gate.php',
            [],
            $this->frameworkRoot(),
        );
    }

    /**
     * @param array<string,string> $scripts
     */
    private function withTemporaryDtoSubGates(array $scripts, callable $callback): void
    {
        $gateDir = $this->frameworkRoot() . '/tools/gates';
        $backups = [];

        foreach (self::SUB_GATES as $file) {
            $path = $gateDir . '/' . $file;
            $backups[$file] = is_file($path) ? $this->readBytes($path) : null;

            if (is_file($path) && !@unlink($path)) {
                throw new RuntimeException('Cannot remove temporary DTO sub-gate target: ' . $path);
            }
        }

        try {
            foreach ($scripts as $file => $script) {
                if (!in_array($file, self::SUB_GATES, true)) {
                    throw new RuntimeException('Unexpected DTO sub-gate fixture: ' . $file);
                }

                $this->writeBytesExact($gateDir . '/' . $file, $script);
            }

            $callback();
        } finally {
            foreach (self::SUB_GATES as $file) {
                $path = $gateDir . '/' . $file;

                if (is_file($path) && !@unlink($path)) {
                    throw new RuntimeException('Cannot remove temporary DTO sub-gate: ' . $path);
                }

                if (is_string($backups[$file])) {
                    $this->writeBytesExact($path, $backups[$file]);
                }
            }
        }
    }

    private function passingSubGate(string $logPath, string $token): string
    {
        return $this->subGateScript($logPath, $token, '', 0);
    }

    private function failingSubGate(string $logPath, string $token, string $output, int $exitCode): string
    {
        return $this->subGateScript($logPath, $token, $output, $exitCode);
    }

    private function subGateScript(string $logPath, string $token, string $output, int $exitCode): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "file_put_contents(" . var_export($logPath, true) . ", " . var_export($token . "\n", true) . ", FILE_APPEND);\n"
            . ($output === '' ? '' : 'fwrite(STDOUT, ' . var_export($output, true) . ");\n")
            . "exit({$exitCode});\n";
    }
}
