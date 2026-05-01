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

namespace Coretsia\Contracts\Tests\Contract;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ProfilingContractsDoNotDependOnPsr7ContractTest extends TestCase
{
    public function test_profiling_contracts_do_not_reference_psr7_types(): void
    {
        $profilingSourceDirectory = dirname(__DIR__, 2) . '/src/Observability/Profiling';

        self::assertDirectoryExists($profilingSourceDirectory);

        foreach (self::phpFiles($profilingSourceDirectory) as $file) {
            $contents = file_get_contents($file);

            self::assertIsString($contents);

            self::assertStringNotContainsString(
                'Psr\Http\Message',
                $contents,
                sprintf('Profiling contract file must not reference PSR-7 types: %s', $file),
            );
        }
    }

    /**
     * @return iterable<string>
     */
    private static function phpFiles(string $directory): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            yield $file->getPathname();
        }
    }
}
