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
use Coretsia\Tools\Spikes\_support\FixtureRoot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FixtureRootRejectsParentTraversalTest extends TestCase
{
    /**
     * @return iterable<string, array{0:string}>
     */
    public static function invalidFixtureRelativePaths(): iterable
    {
        // Parent traversal
        yield 'parent-traversal' => ['../x'];
        yield 'parent-traversal-nested' => ['repo_min/../x'];

        // Absolute paths (minimum set)
        yield 'posix-absolute' => ['/etc/passwd'];
        yield 'windows-drive-letter' => ['C:\\x'];
        yield 'windows-drive-letter-forward-slash' => ['C:/x'];
        yield 'windows-rooted' => ['\\x'];
        yield 'windows-unc' => ['\\\\server\\share\\file'];

        // Empty / dot-segments
        yield 'empty' => [''];
        yield 'dot-only' => ['.'];
        yield 'dot-segment-prefix' => ['./x'];
        yield 'dot-segment-middle' => ['a/./b'];
        yield 'dot-segment-suffix' => ['x/.'];
    }

    #[DataProvider('invalidFixtureRelativePaths')]
    public function testFixtureRootPathRejectsInvalidInput(string $input): void
    {
        try {
            FixtureRoot::path($input);
            self::fail('Expected DeterministicException was not thrown.');
        } catch (DeterministicException $e) {
            self::assertSame(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID, $e->code());
            self::assertSame('fixture-path-invalid', $e->getMessage());

            // Empty string is trivially "contained" in any string; skip that case explicitly.
            if ($input !== '') {
                self::assertStringNotContainsString($input, $e->getMessage());
            }
        } catch (\Throwable $e) {
            self::fail('Expected DeterministicException, got: ' . $e::class);
        }
    }
}
