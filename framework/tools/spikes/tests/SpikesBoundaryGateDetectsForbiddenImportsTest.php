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

namespace Coretsia\Tools\Spikes\tests;

use Coretsia\Tools\Spikes\_support\ErrorCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract evidence for spikes_boundary_gate.php.
 *
 * Cemented behavior:
 * - runs gate with --path=<tempToolsRoot> (scan-only root override)
 * - bootstrap/ConsoleOutput/ErrorCodes are loaded from real repo runtime tools root (derived from gate file location)
 * - temp tree does NOT need a runnable bootstrap
 */
final class SpikesBoundaryGateDetectsForbiddenImportsTest extends TestCase
{
    #[DataProvider('cases')]
    public function testGateBehavesDeterministicallyViaPathOverride(
        callable $arrange,
        bool     $shouldPass,
        ?string  $expectedCode,
        array    $expectedDiagnostics
    ): void
    {
        $tempToolsRoot = self::createTempDir('coretsia_spikes_boundary_gate_');

        try {
            self::mkdirp($tempToolsRoot . '/spikes');
            self::mkdirp($tempToolsRoot . '/gates');

            $arrange($tempToolsRoot);

            [$exitCode, $stdout, $stderr] = self::runGate($tempToolsRoot);

            if ($shouldPass) {
                self::assertSame(0, $exitCode, 'Gate must exit 0 on pass.');
                self::assertSame('', self::normalizeRaw($stdout . $stderr), 'Gate must print nothing on pass.');
                return;
            }

            self::assertSame(1, $exitCode, 'Gate must exit 1 on fail.');

            $lines = self::normalizeLines($stdout, $stderr);
            self::assertNotSame([], $lines, 'Gate must print CODE line on fail.');

            self::assertSame($expectedCode, $lines[0], 'Line 1 must be deterministic CODE only.');

            $actualDiagnostics = \array_slice($lines, 1);
            self::assertSame(
                $expectedDiagnostics,
                $actualDiagnostics,
                'Diagnostics must be stable, minimal, and sorted by scan-root-relative path.'
            );
        } finally {
            self::rmTree($tempToolsRoot);
        }
    }

    public static function cases(): iterable
    {
        // 1) spikes: forbidden import (use ...)
        yield 'spikes/use-forbidden-namespace-fails' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/spikes/forbidden_use.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    use Coretsia\Kernel\Foo;

                    final class T {
                        public function run(): void {
                            new Foo();
                        }
                    }
                    PHP
                );
            },
            false,
            ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT,
            [
                'spikes/forbidden_use.php: forbidden-import:Coretsia\\Kernel',
            ],
        ];

        // 2) spikes: internal-toolkit import (allowed) => pass
        yield 'spikes/internal-toolkit-import-passes' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/spikes/internal_toolkit_ok.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    new \Coretsia\Devtools\InternalToolkit\Json();
                    PHP
                );
            },
            true,
            null,
            [],
        ];

        // 3) spikes: instanceof forbidden => fail (import root must be detected from tokens)
        yield 'spikes/instanceof-forbidden-fails' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/spikes/forbidden_instanceof.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    $x = null;
                    if ($x instanceof \Coretsia\Foundation\X) {
                        // noop
                    }
                    PHP
                );
            },
            false,
            ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT,
            [
                'spikes/forbidden_instanceof.php: forbidden-import:Coretsia\\Foundation',
            ],
        ];

        // 4) spikes: forbidden type-hint => fail
        yield 'spikes/typehint-forbidden-fails' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/spikes/forbidden_typehint.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    function f(\Coretsia\Foundation\X $x): void {}
                    PHP
                );
            },
            false,
            ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT,
            [
                'spikes/forbidden_typehint.php: forbidden-import:Coretsia\\Foundation',
            ],
        ];

        // 5) spikes: "use Coretsia\Foundation;" => fail (root equals forbidden root)
        yield 'spikes/use-root-namespace-fails' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/spikes/forbidden_use_root.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    use Coretsia\Foundation;

                    final class T {}
                    PHP
                );
            },
            false,
            ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT,
            [
                'spikes/forbidden_use_root.php: forbidden-import:Coretsia\\Foundation',
            ],
        ];

        // 6) spikes: require with concatenated literal parts => forbidden path
        yield 'spikes/require-concat-framework-packages-src-fails' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/spikes/forbidden_path_concat.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    require 'framework/packages/' . 'core/x/src/y.php';
                    PHP
                );
            },
            false,
            ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH,
            [
                'spikes/forbidden_path_concat.php: forbidden-path',
            ],
        ];

        // 7) spikes: require direct internal-toolkit path under framework/packages/**/src/** => forbidden path (no exceptions)
        yield 'spikes/require-internal-toolkit-by-path-fails' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/spikes/forbidden_path_internal_toolkit.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    require 'framework/packages/devtools/internal-toolkit/src/Json.php';
                    PHP
                );
            },
            false,
            ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH,
            [
                'spikes/forbidden_path_internal_toolkit.php: forbidden-path',
            ],
        ];

        // 8) spikes/fixtures: may contain FQCN as data => PASS (excluded scope by construction)
        yield 'spikes/fixtures-fqcn-data-is-excluded-and-passes' => [
            static function (string $root): void {
                self::mkdirp($root . '/spikes/fixtures');
                self::writePhp(
                    $root . '/spikes/fixtures/fqcn_data.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    $x = \Coretsia\Foundation\X::class;
                    PHP
                );
            },
            true,
            null,
            [],
        ];

        // 9) spikes: require __DIR__ . '/../packages/.../src/...' => forbidden path (fragment match)
        yield 'spikes/require-dir-parent-packages-src-fails' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/spikes/forbidden_path_dir_dotdot.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    require __DIR__ . '/../packages/core/x/src/y.php';
                    PHP
                );
            },
            false,
            ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH,
            [
                'spikes/forbidden_path_dir_dotdot.php: forbidden-path',
            ],
        ];

        // 10) spikes: require __DIR__ . '\..\packages\...\src\...' => forbidden path (Windows slashes; normalized by gate)
        yield 'spikes/require-dir-backslash-parent-packages-src-fails' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/spikes/forbidden_path_dir_backslash.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    require __DIR__ . '\\..\\packages\\core\\x\\src\\y.php';
                    PHP
                );
            },
            false,
            ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH,
            [
                'spikes/forbidden_path_dir_backslash.php: forbidden-path',
            ],
        ];

        // 11) gates: forbidden namespace import under gates/** => fail (same boundary rules)
        yield 'gates/use-forbidden-namespace-fails' => [
            static function (string $root): void {
                self::writePhp(
                    $root . '/gates/forbidden_gate.php',
                    <<<'PHP'
                    <?php
                    declare(strict_types=1);

                    use Coretsia\Foundation\X;

                    final class GateLike {}
                    PHP
                );
            },
            false,
            ErrorCodes::CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT,
            [
                'gates/forbidden_gate.php: forbidden-import:Coretsia\\Foundation',
            ],
        ];
    }

    private static function runGate(string $tempToolsRoot): array
    {
        $gate = self::gatePath();

        $cmd = [
            \PHP_BINARY,
            $gate,
            '--path=' . $tempToolsRoot,
        ];

        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = \proc_open(
            $cmd,
            $spec,
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );

        if (!\is_resource($proc)) {
            self::fail('Failed to start gate process.');
        }

        \fclose($pipes[0]);

        $stdout = (string)\stream_get_contents($pipes[1]);
        $stderr = (string)\stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exitCode = \proc_close($proc);

        return [(int)$exitCode, $stdout, $stderr];
    }

    private static function gatePath(): string
    {
        $p = \realpath(__DIR__ . '/../../gates/spikes_boundary_gate.php');
        if (!\is_string($p) || $p === '') {
            self::fail('Gate file path cannot be resolved.');
        }

        return $p;
    }

    private static function normalizeLines(string $stdout, string $stderr): array
    {
        $raw = self::normalizeRaw($stderr . $stdout);
        if ($raw === '') {
            return [];
        }

        return \explode("\n", $raw);
    }

    private static function normalizeRaw(string $raw): string
    {
        $raw = \str_replace(["\r\n", "\r"], "\n", $raw);

        // Gate output must be lines with trailing "\n"; normalize to no trailing newline for comparisons.
        return \rtrim($raw, "\n");
    }

    private static function writePhp(string $path, string $code): void
    {
        self::mkdirp(\dirname($path));

        $bytes = \file_put_contents($path, $code . "\n");
        if (!\is_int($bytes) || $bytes <= 0) {
            self::fail('Failed to write test php file: ' . $path);
        }
    }

    private static function mkdirp(string $dir): void
    {
        if (\is_dir($dir)) {
            return;
        }

        if (!@\mkdir($dir, 0777, true) && !\is_dir($dir)) {
            self::fail('Failed to create directory: ' . $dir);
        }
    }

    private static function createTempDir(string $prefix): string
    {
        $base = \sys_get_temp_dir();
        $id = \bin2hex(\random_bytes(8));

        $dir = $base . \DIRECTORY_SEPARATOR . $prefix . $id;

        self::mkdirp($dir);

        $real = \realpath($dir);
        if (!\is_string($real) || $real === '') {
            self::fail('Temp dir realpath failed.');
        }

        return $real;
    }

    private static function rmTree(string $dir): void
    {
        if ($dir === '' || !\is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $fi) {
            if (!$fi instanceof \SplFileInfo) {
                continue;
            }

            $p = $fi->getPathname();
            if ($fi->isDir()) {
                @\rmdir($p);
                continue;
            }

            @\unlink($p);
        }

        @\rmdir($dir);
    }
}
