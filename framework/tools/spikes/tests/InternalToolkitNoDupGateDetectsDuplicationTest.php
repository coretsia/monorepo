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

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class InternalToolkitNoDupGateDetectsDuplicationTest extends TestCase
{
    public function testGateFailsOnForbiddenSymbolDeclaration(): void
    {
        $gate = $this->resolveGateOrSkip();

        $tmp = $this->makeTempDir('toolkit-no-dup-dup-');

        try {
            $this->writePhp(
                $tmp . '/tools/Dup.php',
                <<<'PHP'
                <?php
                declare(strict_types=1);

                final class Dup
                {
                    public function toSnake(): void
                    {
                    }
                }
                PHP
            );

            $res = $this->runGate($gate, $tmp);

            self::assertSame(1, $res['exit_code'], 'gate must fail');
            self::assertSame('CORETSIA_TOOLKIT_DUPLICATION_DETECTED', $res['code_line'], 'must emit duplication code');
        } finally {
            $this->rmTree($tmp);
        }
    }

    public function testGatePassesOnAllowlistedWrapperDelegatingToInternalToolkit(): void
    {
        $gate = $this->resolveGateOrSkip();

        $tmp = $this->makeTempDir('toolkit-no-dup-wrapper-');

        try {
            $this->writePhp(
                $tmp . '/spikes/any/StableJsonEncoder.php',
                <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace Any;

                final class StableJsonEncoder
                {
                    public static function encodeStable(mixed $value): string
                    {
                        return \Coretsia\Devtools\InternalToolkit\Json::encodeStable($value);
                    }
                }
                PHP
            );

            $res = $this->runGate($gate, $tmp);

            self::assertSame(0, $res['exit_code'], 'gate must pass');
            self::assertSame('', $res['raw_output'], 'gate must be silent on pass');
        } finally {
            $this->rmTree($tmp);
        }
    }

    public function testGatePassesOnAllowlistedJsonEncodeInComposerJsonCanonicalizer(): void
    {
        $gate = $this->resolveGateOrSkip();

        $tmp = $this->makeTempDir('toolkit-no-dup-json-allow-');

        try {
            $this->writePhp(
                $tmp . '/spikes/workspace/ComposerJsonCanonicalizer.php',
                <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace Coretsia\Tools\Spikes\Workspace;

                final class ComposerJsonCanonicalizer
                {
                    public static function encodeCanonical(array $composerJson): string
                    {
                        return (string)\json_encode(
                            $composerJson,
                            \JSON_PRETTY_PRINT
                            | \JSON_UNESCAPED_SLASHES
                            | \JSON_UNESCAPED_UNICODE
                            | \JSON_THROW_ON_ERROR
                        );
                    }
                }
                PHP
            );

            $res = $this->runGate($gate, $tmp);

            self::assertSame(0, $res['exit_code'], 'gate must pass');
            self::assertSame('', $res['raw_output'], 'gate must be silent on pass');
        } finally {
            $this->rmTree($tmp);
        }
    }

    public function testGateFailsOnJsonEncodeCall(): void
    {
        $gate = $this->resolveGateOrSkip();

        $tmp = $this->makeTempDir('toolkit-no-dup-json-');

        try {
            $this->writePhp(
                $tmp . '/tools/JsonEncode.php',
                <<<'PHP'
                <?php
                declare(strict_types=1);

                $x = \json_encode(['a' => 1]);
                PHP
            );

            $res = $this->runGate($gate, $tmp);

            self::assertSame(1, $res['exit_code'], 'gate must fail');
            self::assertSame('CORETSIA_TOOLKIT_JSON_ENCODE_FORBIDDEN', $res['code_line'], 'must emit json-forbidden code');
        } finally {
            $this->rmTree($tmp);
        }
    }

    private function resolveGateOrSkip(): string
    {
        $toolsRoot = \realpath(__DIR__ . '/../..');
        if (!\is_string($toolsRoot) || $toolsRoot === '') {
            self::markTestSkipped('tools_root_unresolvable');
        }

        $gate = $toolsRoot . '/gates/internal_toolkit_no_dup_gate.php';
        $bootstrap = $toolsRoot . '/spikes/_support/bootstrap.php';

        if (!\is_file($gate) || !\is_readable($gate)) {
            self::markTestSkipped('gate_missing_or_unreadable');
        }

        // NOTE (cemented): gate loads bootstrap from real repo tools root (derived from gate location).
        if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
            self::markTestSkipped('bootstrap_missing_or_unreadable');
        }

        return $gate;
    }

    private function makeTempDir(string $prefix): string
    {
        $base = \rtrim((string)\sys_get_temp_dir(), '\\/');
        $dir = $base . '/' . $prefix . \bin2hex(\random_bytes(8));

        if (!@\mkdir($dir, 0777, true) && !\is_dir($dir)) {
            self::fail('failed_to_create_temp_dir');
        }

        return $dir;
    }

    private function writePhp(string $path, string $code): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir) && !@\mkdir($dir, 0777, true) && !\is_dir($dir)) {
            self::fail('failed_to_create_dir');
        }

        $bytes = @\file_put_contents($path, $code);
        if (!\is_int($bytes) || $bytes <= 0) {
            self::fail('failed_to_write_file');
        }
    }

    /**
     * Run gate as a separate process in --path=<scanRoot> mode.
     *
     * @return array{exit_code:int, raw_output:string, code_line:string}
     */
    private function runGate(string $gatePath, string $scanRoot): array
    {
        $cmd = [\PHP_BINARY, $gatePath, '--path=' . $scanRoot];

        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @\proc_open($cmd, $spec, $pipes);
        if (!\is_resource($proc)) {
            self::fail('failed_to_start_gate_process');
        }

        $stdout = '';
        $stderr = '';
        $exitCode = 1;

        try {
            @\fclose($pipes[0]);

            $stdout = (string)\stream_get_contents($pipes[1]);
            $stderr = (string)\stream_get_contents($pipes[2]);

            @\fclose($pipes[1]);
            @\fclose($pipes[2]);

            $exitCode = (int)\proc_close($proc);
        } finally {
            if (\is_resource($proc)) {
                @\proc_terminate($proc);
            }
        }

        // Prefer stderr if present; otherwise stdout. Keeps CODE line stable if channels differ.
        $out = $stderr !== '' ? $stderr : $stdout;

        $out = \str_replace(["\r\n", "\r"], "\n", $out);
        $outTrim = \trim($out);

        $codeLine = '';
        if ($outTrim !== '') {
            $lines = \explode("\n", $outTrim);
            $codeLine = \trim((string)($lines[0] ?? ''));
        }

        return [
            'exit_code' => $exitCode,
            'raw_output' => $outTrim,
            'code_line' => $codeLine,
        ];
    }

    private function rmTree(string $path): void
    {
        if ($path === '' || !\file_exists($path)) {
            return;
        }

        if (\is_file($path) || \is_link($path)) {
            @\unlink($path);
            return;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $fi) {
            if (!$fi instanceof SplFileInfo) {
                continue;
            }

            $p = $fi->getPathname();

            if ($fi->isDir() && !$fi->isLink()) {
                @\rmdir($p);
                continue;
            }

            @\unlink($p);
        }

        @\rmdir($path);
    }
}
