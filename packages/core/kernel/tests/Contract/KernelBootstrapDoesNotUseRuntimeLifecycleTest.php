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

namespace Coretsia\Kernel\Tests\Contract;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class KernelBootstrapDoesNotUseRuntimeLifecycleTest extends TestCase
{
    /**
     * @var array<string, list<string>>
     */
    private const array FORBIDDEN_NEEDLES = [
        'ResetOrchestrator' => [
            'ResetOrchestrator',
            'Coretsia\Foundation\Runtime\Reset\ResetOrchestrator',
            'Coretsia\\Foundation\\Runtime\\Reset\\ResetOrchestrator',
        ],
        'TagRegistry' => [
            'TagRegistry',
            'Coretsia\Foundation\Tag\TagRegistry',
            'Coretsia\\Foundation\\Tag\\TagRegistry',
        ],
        'ResetInterface' => [
            'ResetInterface',
            'Coretsia\Contracts\Runtime\ResetInterface',
            'Coretsia\\Contracts\\Runtime\\ResetInterface',
        ],
        'KernelRuntime' => [
            'KernelRuntime',
            'Coretsia\Kernel\Runtime\KernelRuntime',
            'Coretsia\\Kernel\\Runtime\\KernelRuntime',
        ],
        'kernel.reset' => [
            'kernel.reset',
        ],
    ];

    public function testBootstrapLayerDoesNotUseRuntimeLifecycleServicesOrResetTags(): void
    {
        $bootRoot = self::kernelRoot() . '/src/Boot';

        self::assertDirectoryExists($bootRoot);

        $violations = [];

        foreach (self::phpFiles($bootRoot) as $file) {
            $contents = \file_get_contents($file);

            self::assertIsString($contents);

            foreach (self::FORBIDDEN_NEEDLES as $label => $needles) {
                foreach ($needles as $needle) {
                    if (!\str_contains($contents, $needle)) {
                        continue;
                    }

                    $violations[] = self::relativeToKernelRoot(
                        $file
                    ) . ': forbidden bootstrap runtime lifecycle reference: ' . $label;

                    break;
                }
            }
        }

        $violations = \array_values(\array_unique($violations));
        \sort($violations, \SORT_STRING);

        self::assertSame(
            [],
            $violations,
            "Bootstrap Phase A must not depend on runtime lifecycle/reset services.\n" . \implode("\n", $violations),
        );
    }

    private static function kernelRoot(): string
    {
        $root = \realpath(__DIR__ . '/../..');

        self::assertIsString($root);

        return \str_replace('\\', '/', $root);
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $root): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            if (!$fileInfo->isFile()) {
                continue;
            }

            $path = \str_replace('\\', '/', $fileInfo->getPathname());

            if (!\str_ends_with($path, '.php')) {
                continue;
            }

            $files[] = $path;
        }

        \sort($files, \SORT_STRING);

        return $files;
    }

    private static function relativeToKernelRoot(string $file): string
    {
        $kernelRoot = self::kernelRoot();
        $file = \str_replace('\\', '/', $file);

        if (\str_starts_with($file, $kernelRoot . '/')) {
            return \substr($file, \strlen($kernelRoot) + 1);
        }

        return $file;
    }
}
