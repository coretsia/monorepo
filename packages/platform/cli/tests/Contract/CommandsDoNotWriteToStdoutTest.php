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

use PHPUnit\Framework\TestCase;

final class CommandsDoNotWriteToStdoutTest extends TestCase
{
    public function testEveryProductionCommandAvoidsDirectOutput(): void
    {
        $commandRoot = \dirname(__DIR__, 2) . '/src/Command';
        self::assertDirectoryExists($commandRoot);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($commandRoot, \FilesystemIterator::SKIP_DOTS),
        );
        $scanned = 0;
        $violations = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            ++$scanned;
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $tokens = \token_get_all($source);

            foreach ($tokens as $index => $token) {
                if (!\is_array($token)) {
                    continue;
                }

                [$type, $value, $line] = $token;
                $reason = null;

                if (\in_array($type, [\T_ECHO, \T_PRINT, \T_OPEN_TAG_WITH_ECHO], true)) {
                    $reason = 'direct-output-expression';
                } elseif (
                    \in_array($type, [\T_STRING, \T_NAME_FULLY_QUALIFIED, \T_NAME_QUALIFIED], true)
                ) {
                    $segments = \explode('\\', $value);
                    $name = \strtolower($segments[\count($segments) - 1]);

                    if (\in_array($name, ['stdout', 'stderr'], true)) {
                        $reason = 'direct-stream-constant';
                    } elseif (\in_array($name, ['printf', 'var_dump', 'print_r', 'error_log'], true)) {
                        for ($next = $index + 1; isset($tokens[$next]); ++$next) {
                            $candidate = $tokens[$next];
                            if (
                                \is_array($candidate)
                                && \in_array($candidate[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)
                            ) {
                                continue;
                            }

                            if ($candidate === '(') {
                                $reason = 'direct-output-function';
                            }
                            break;
                        }
                    }
                } elseif (
                    \in_array($type, [\T_CONSTANT_ENCAPSED_STRING, \T_ENCAPSED_AND_WHITESPACE], true)
                    && \preg_match('~php://(?:stdout|stderr|output)\b~i', $value) === 1
                ) {
                    $reason = 'direct-output-stream';
                }

                if ($reason !== null) {
                    $violations[] = $file->getFilename() . ':' . $line . ':' . $reason;
                }
            }
        }

        self::assertGreaterThan(0, $scanned);
        self::assertSame([], $violations);
    }
}
