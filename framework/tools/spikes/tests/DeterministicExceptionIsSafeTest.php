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

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use PHPUnit\Framework\TestCase;

final class DeterministicExceptionIsSafeTest extends TestCase
{
    public function testCreatingDeterministicExceptionWithAnyRegisteredCodeMustBePossible(): void
    {
        $codes = ErrorCodes::all();
        self::assertNotSame([], $codes, 'ErrorCodes::all() MUST NOT be empty');

        $safeMessage = 'deterministic-failure';

        foreach ($codes as $code) {
            $e = new DeterministicException($code, $safeMessage);

            self::assertSame($code, $e->code());
            self::assertSame($safeMessage, $e->getMessage());

            self::assertMessageDoesNotContainAbsolutePathFragments($e->getMessage());
            self::assertMessageDoesNotContainDotenvSecretPatterns($e->getMessage());
        }
    }

    public function testUnsafeMessageCannotLeakAbsolutePathsOrDotenvPatterns(): void
    {
        $codes = ErrorCodes::all();
        self::assertNotSame([], $codes, 'ErrorCodes::all() MUST NOT be empty');

        $code = $codes[0];

        $unsafeMessages = [
            'oops C:\\x',
            'oops C:/x',
            'see \\\\server\\share\\file',
            'see /home/user/.env',
            'see /Users/user/.env',
            'TOKEN=supersecret',
            'PASSWORD = supersecret',
            'AUTH=Bearer xxx',
            'COOKIE=sessionid=xxx',
        ];

        foreach ($unsafeMessages as $unsafe) {
            $observed = '';
            try {
                $e = new DeterministicException($code, $unsafe);
                $observed = $e->getMessage();
            } catch (\Throwable $t) {
                // Allowed: ctor may reject unsafe messages, but any thrown message must also be safe.
                $observed = $t->getMessage();
            }

            self::assertMessageDoesNotContainAbsolutePathFragments($observed);
            self::assertMessageDoesNotContainDotenvSecretPatterns($observed);
        }
    }

    private static function assertMessageDoesNotContainAbsolutePathFragments(string $message): void
    {
        // Windows drive-letter absolute fragments (cemented minimum set): (?i)\b[A-Z]:(\\|/)
        self::assertSame(
            0,
            preg_match('~(?i)\b[A-Z]:[\\\\/]~', $message),
            'Exception message MUST NOT contain Windows drive-letter absolute path fragments'
        );

        // Windows UNC-like absolute fragments: "\\server\share\"-like prefix (\\ followed by a non-whitespace segment)
        self::assertSame(
            0,
            preg_match('~\\\\\\\\\S+~', $message),
            'Exception message MUST NOT contain Windows UNC absolute path fragments'
        );

        // POSIX/macOS home-like absolute fragments (cemented minimum set)
        self::assertFalse(
            str_contains($message, '/home/'),
            'Exception message MUST NOT contain POSIX home-like absolute path fragments (/home/)'
        );

        self::assertFalse(
            str_contains($message, '/Users/'),
            'Exception message MUST NOT contain macOS home-like absolute path fragments (/Users/)'
        );
    }

    private static function assertMessageDoesNotContainDotenvSecretPatterns(string $message): void
    {
        // dotenv-like patterns (KEY=VALUE) for typical secret keys: TOKEN, PASSWORD, AUTH, COOKIE
        self::assertSame(
            0,
            preg_match('~\b(?:TOKEN|PASSWORD|AUTH|COOKIE)\s*=\s*\S+~i', $message),
            'Exception message MUST NOT contain dotenv-like secret patterns (KEY=VALUE)'
        );
    }
}
