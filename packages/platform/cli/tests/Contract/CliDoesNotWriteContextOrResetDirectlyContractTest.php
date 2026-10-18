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

use Coretsia\Contracts\Context\ContextAccessorInterface;
use PHPUnit\Framework\TestCase;

final class CliDoesNotWriteContextOrResetDirectlyContractTest extends TestCase
{
    public function testProductionCliSourceOnlyReadsContextThroughTheContractsAccessor(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $checked = 0;
        $sawContextAccessor = false;
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$checked;
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $code = '';
            foreach (\token_get_all($source) as $token) {
                if (\is_array($token) && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= \is_array($token) ? $token[1] : $token;
            }
            $sawContextAccessor = $sawContextAccessor || \str_contains($code, ContextAccessorInterface::class)
                || \str_contains($code, 'ContextAccessorInterface');
            foreach (['ContextStore', 'ResetOrchestrator', 'ResetInterface', 'CorrelationIdProvider'] as $forbidden) {
                self::assertSame(
                    0,
                    \preg_match('~(?<![A-Za-z0-9_])' . $forbidden . '(?![A-Za-z0-9_])~', $code),
                    $file->getFilename(),
                );
            }
            self::assertSame(
                0,
                \preg_match(
                    '/ReservedTags::(?:KERNEL_RESET|RESET)|->(?:reset|resetAll|registerResetHook|writeContext|setContext)\s*\(|->(?:set|put|remove|clear)\s*\(\s*ContextKeys::/',
                    $code,
                ),
                $file->getFilename(),
            );
            self::assertSame(
                0,
                \preg_match('/\b(?:tagRegistry|registry)\s*->\s*all\s*\([^)]*(?:reset|kernel\.reset)/i', $code),
                $file->getFilename(),
            );
            self::assertSame(
                0,
                \preg_match('/[\x27\x22]kernel\.reset[\x27\x22]/i', $code),
                $file->getFilename(),
            );
        }
        self::assertGreaterThan(30, $checked);
        self::assertTrue($sawContextAccessor);
    }
}
