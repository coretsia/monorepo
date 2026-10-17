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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Output\FormattedOutput;
use PHPUnit\Framework\TestCase;

final class CliHasSingleProductionOutputSinkContractTest extends TestCase
{
    public function testOnlyConsoleOutputWriterCanWriteDirectlyToStreamsAfterAutoload(): void
    {
        $root = \dirname(__DIR__, 2);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/src'));
        $checked = 0;
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$checked;
            $relative = \str_replace('\\', '/', \substr($file->getPathname(), \strlen($root . '/src/')));
            $tokens = self::tokensWithoutComments($file->getPathname());
            $code = self::tokenSource($tokens);
            foreach ($tokens as $token) {
                if (!\is_array($token)) {
                    continue;
                }
                self::assertNotContains($token[0], [T_ECHO, T_PRINT, T_EXIT, T_OPEN_TAG_WITH_ECHO], $relative);

                if ($token[0] === T_INLINE_HTML) {
                    self::assertSame('', \trim($token[1]), $relative);
                }
            }
            if ($relative === 'Output/ConsoleOutputWriter.php') {
                self::assertMatchesRegularExpression('/\\\\fwrite\s*\(/', $code);
                continue;
            }
            self::assertSame(
                0,
                \preg_match(
                    '~(?<!->|::)(?<![A-Za-z0-9_])\\\\?(?:fwrite|fputs|fprintf|printf|vprintf|file_put_contents|readfile|error_log|print_r|var_dump|debug_zval_dump|var_export)\s*\(~',
                    $code,
                ),
                $relative,
            );
            self::assertSame(
                0,
                \preg_match('~(?<![A-Za-z0-9_$])(?:STDOUT|STDERR)\b|(?i:php://(?:stdout|stderr))~', $code),
                $relative,
            );
            if ($relative === 'Output/TerminalCapabilitiesDetector.php') {
                self::assertStringContainsString('stream_isatty(', $code);
                self::assertSame(0, \preg_match('/\b(?:fwrite|file_put_contents)\s*\(/', $code));
            }
        }
        self::assertGreaterThan(30, $checked);
    }

    public function testBinaryHasOnlyTwoFixedPreAutoloadStderrFailuresAndDelegatesStreams(): void
    {
        $binary = \dirname(__DIR__, 2) . '/bin/coretsia';
        $source = \file_get_contents($binary);
        self::assertIsString($source);
        $code = self::tokenSource(self::tokensWithoutComments($binary));
        $autoloadBoundary = \strpos($code, 'require_once $autoload;');
        self::assertNotSame(false, $autoloadBoundary);
        $pre = \substr($code, 0, $autoloadBoundary);
        $post = \substr($code, $autoloadBoundary + \strlen('require_once $autoload;'));
        $fallback = '@\\fwrite(\\STDERR, "CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable\\n");';
        self::assertSame(1, \substr_count($pre, $fallback));
        self::assertSame(1, \substr_count($post, $fallback));
        $entrypointPosition = \strpos($post, '$entrypoint = @require ');
        self::assertNotSame(false, $entrypointPosition);

        $outsideFallback = \str_replace($fallback, '', $pre . $post);

        self::assertSame(
            0,
            \preg_match(
                '~(?<!->|::)(?<![A-Za-z0-9_])\\\\?(?:fwrite|fputs|fprintf|printf|vprintf|file_put_contents|readfile|error_log|print_r|var_dump|debug_zval_dump|var_export)\s*\(~',
                $outsideFallback,
            ),
        );

        foreach (\token_get_all('<?php ' . $outsideFallback) as $token) {
            if (\is_array($token)) {
                self::assertNotContains($token[0], [T_ECHO, T_PRINT, T_OPEN_TAG_WITH_ECHO]);

                if ($token[0] === T_INLINE_HTML) {
                    self::assertSame('', \trim($token[1]));
                }
            }
        }

        self::assertStringContainsString('stdout: \\STDOUT', $code);
        self::assertStringContainsString('stderr: \\STDERR', $code);
    }

    public function testWriterOwnsFinalNewlineAndWritesExactlyOncePerNonemptyStream(): void
    {
        $stdout = \fopen('php://temp', 'w+');
        $stderr = \fopen('php://temp', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        try {
            new ConsoleOutputWriter($stdout, $stderr)->write(new FormattedOutput('safe-output', 'safe-error'));
            \rewind($stdout);
            \rewind($stderr);
            self::assertSame("safe-output\n", \stream_get_contents($stdout));
            self::assertSame("safe-error\n", \stream_get_contents($stderr));
        } finally {
            \fclose($stdout);
            \fclose($stderr);
        }
    }

    /** @return list<array|string> */
    private static function tokensWithoutComments(string $path): array
    {
        $source = \file_get_contents($path);
        self::assertIsString($source);
        return \array_values(
            \array_filter(
                \token_get_all($source),
                static fn ($token): bool => !\is_array($token)
                    || !\in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true),
            ),
        );
    }

    /** @param list<array|string> $tokens */
    private static function tokenSource(array $tokens): string
    {
        return \implode('', \array_map(static fn ($token): string => \is_array($token) ? $token[1] : $token, $tokens));
    }
}
