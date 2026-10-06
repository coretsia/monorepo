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
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use PHPUnit\Framework\TestCase;

final class KernelOpsPublicOperationsE2ETest extends TestCase
{
    public function testPublicOperationsExecuteThroughBootedSourceHost(): void
    {
        $root = \sys_get_temp_dir()
            . '/coretsia-kernel-ops-public-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root, 0777, true);

        try {
            $container = new KernelOpsHostBooter()->boot(
                new KernelOpsHostInput($root),
            );

            $ops = $container->get(KernelOpsInterface::class);

            self::assertInstanceOf(KernelOpsInterface::class, $ops);

            $request = new KernelOpsRequest('console');

            $validate = $ops->validateConfig($request);

            self::assertSame('success', $validate->outcome());
            self::assertNull($validate->reason());
            self::assertSame(
                ['counts', 'valid'],
                \array_keys($validate->data()),
            );
            self::assertTrue($validate->data()['valid']);

            $debug = $ops->debugConfig($request);

            self::assertSame('success', $debug->outcome());
            self::assertSame(
                ['explain'],
                \array_keys($debug->data()),
            );

            $beforeHash = self::tree($root);

            $hash = $ops->hashConfig($request);

            self::assertSame('success', $hash->outcome());
            self::assertSame(
                ['generation_id'],
                \array_keys($hash->data()),
            );
            self::assertSame(
                $beforeHash,
                self::tree($root),
                'hashConfig() must not write application artifacts.',
            );

            $generationId = $hash->data()['generation_id'];

            self::assertIsString($generationId);
            self::assertNotSame('', $generationId);

            $compile = $ops->compileConfig($request);

            self::assertSame('success', $compile->outcome());
            self::assertSame(
                ['artifacts', 'generation_id'],
                \array_keys($compile->data()),
            );
            self::assertSame(
                $generationId,
                $compile->data()['generation_id'],
            );
            self::assertCount(4, $compile->data()['artifacts']);

            foreach ($compile->data()['artifacts'] as $artifact) {
                self::assertSame(
                    ['basename', 'identity'],
                    \array_keys($artifact),
                );
            }

            $verify = $ops->verifyCache($request);

            self::assertSame('success', $verify->outcome());
            self::assertSame(
                [
                    'artifacts',
                    'current_generation_id',
                    'expected_generation_id',
                    'state',
                ],
                \array_keys($verify->data()),
            );
            self::assertSame('clean', $verify->data()['state']);
            self::assertSame(
                $generationId,
                $verify->data()['current_generation_id'],
            );
            self::assertSame(
                $generationId,
                $verify->data()['expected_generation_id'],
            );
            self::assertCount(4, $verify->data()['artifacts']);

            foreach ($verify->data()['artifacts'] as $artifact) {
                self::assertSame(
                    [
                        'basename',
                        'existing_byte_count',
                        'expected_byte_count',
                        'name',
                        'reason',
                        'status',
                    ],
                    \array_keys($artifact),
                );

                self::assertArrayNotHasKey('path', $artifact);
            }

            $modules = $ops->debugModules($request);

            self::assertSame('success', $modules->outcome());
            self::assertSame(
                ['enabled', 'excluded', 'topological_order'],
                \array_keys($modules->data()),
            );
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
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $paths = [];

        foreach ($iterator as $item) {
            $paths[] = \str_replace(
                '\\',
                '/',
                \substr($item->getPathname(), \strlen($root) + 1),
            );
        }

        \sort($paths, \SORT_STRING);

        return $paths;
    }

    private static function removeTree(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $item) {
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
