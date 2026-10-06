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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use PHPUnit\Framework\TestCase;

final class KernelOpsHostBootsWithoutCurrentGenerationTest extends TestCase
{
    public function testReturnedContainerResolvesSharedOpsFacadeWithoutRunningAnOperation(): void
    {
        $root = \sys_get_temp_dir()
            . '/coretsia-kernel-ops-host-no-current-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root, 0777, true);

        try {
            $container = new KernelOpsHostBooter()->boot(
                new KernelOpsHostInput($root),
            );
            $afterBoot = self::tree($root);

            $interface = $container->get(KernelOpsInterface::class);
            $facade = $container->get(KernelOpsFacade::class);

            self::assertInstanceOf(KernelOpsInterface::class, $interface);
            self::assertInstanceOf(KernelOpsFacade::class, $facade);
            self::assertSame($facade, $interface);
            self::assertSame($interface, $container->get(KernelOpsInterface::class));
            self::assertSame($facade, $container->get(KernelOpsFacade::class));

            self::assertSame(
                $afterBoot,
                self::tree($root),
                'Resolving Kernel Ops bindings must not execute or publish an operation.',
            );

            $source = self::source();

            self::assertMatchesRegularExpression(
                '/KernelOpsInterface::class,\s*static function \(Container \$container\): KernelOpsInterface \{\s*' .
                '\$facade = \$container->get\(KernelOpsFacade::class\)/s',
                $source,
            );
            self::assertStringNotContainsString('ArtifactGenerationLocator', $source);
            self::assertStringNotContainsString('currentGeneration', $source);

            foreach (
                [
                    'validateConfig(',
                    'debugConfig(',
                    'compileConfig(',
                    'hashConfig(',
                    'verifyCache(',
                    'debugModules(',
                ] as $operationCall
            ) {
                self::assertStringNotContainsString($operationCall, $source);
            }
        } finally {
            self::removeTree($root);
        }
    }

    /**
     * @return list<string>
     */
    private static function tree(string $root): array
    {
        if (!\is_dir($root)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS,
            ),
        );
        $paths = [];

        foreach ($iterator as $item) {
            $paths[] = \str_replace('\\', '/', \substr($item->getPathname(), \strlen($root) + 1));
        }

        \sort($paths, \SORT_STRING);

        return $paths;
    }

    private static function source(): string
    {
        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsHostBooter.php');

        self::assertIsString($source);

        return $source;
    }

    private static function removeTree(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        $items = \scandir($path);

        if (!\is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (\is_dir($itemPath)) {
                self::removeTree($itemPath);
            } else {
                @\unlink($itemPath);
            }
        }

        @\rmdir($path);
    }
}
