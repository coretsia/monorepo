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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\DependencySync\Composer\ComposerManifestStore;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use PHPUnit\Framework\TestCase;

final class ComposerManifestStoreTest extends TestCase
{
    public function testReadsAndEncodesManifestWithoutChangingJsonObjectSemantics(): void
    {
        $root = self::root();

        try {
            $bytes = "{\n"
                . "  \"require\": {\"php\": \"^8.4\"},\n"
                . "  \"extra\": {\n"
                . "    \"numericObject\": {\n"
                . "      \"01\": \"leading\",\n"
                . "      \"123\": \"integer-looking\"\n"
                . "    },\n"
                . "    \"emptyObject\": {},\n"
                . "    \"emptyList\": [],\n"
                . "    \"float\": 1.25\n"
                . "  }\n"
                . "}\n";

            \file_put_contents($root . '/composer.json', $bytes);

            $store = new ComposerManifestStore();
            $manifest = $store->readManifest($root);
            $encoded = $store->encodeManifest($manifest['document']);
            $decoded = \json_decode(
                $encoded,
                false,
                512,
                \JSON_THROW_ON_ERROR,
            );

            self::assertInstanceOf(
                \stdClass::class,
                $decoded->extra->emptyObject,
            );
            self::assertSame([], $decoded->extra->emptyList);
            self::assertSame(1.25, $decoded->extra->float);
            self::assertInstanceOf(
                \stdClass::class,
                $decoded->extra->numericObject,
            );
            self::assertSame(
                'leading',
                $decoded->extra->numericObject->{'01'},
            );
            self::assertSame(
                'integer-looking',
                $decoded->extra->numericObject->{'123'},
            );
            self::assertStringStartsWith('sha256:', $manifest['identity']);
        } finally {
            self::remove($root);
        }
    }

    public function testRejectsDuplicateKeysMalformedJsonAndUnsafeNumbers(): void
    {
        $root = self::root();

        try {
            $store = new ComposerManifestStore();

            foreach (
                [
                    '{"require":{},"require":{}}',
                    '{"require":{},"extra":{"x":1,"x":2}}',
                    '{"require":{},"requ\u0069re":{}}',
                    "{\"require\":{},\"x\":\"\xFF\"}",
                    '{"require":',
                    '{"require":{},"x":1e400}',
                    '{"require":{},"x":9223372036854775808}',
                ] as $bytes
            ) {
                \file_put_contents($root . '/composer.json', $bytes);

                try {
                    $store->readManifest($root);
                    self::fail('Expected invalid Composer manifest.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID,
                        $exception->errorCode(),
                    );
                }
            }
        } finally {
            self::remove($root);
        }
    }

    public function testRejectsComposerJsonSymlinkSwitch(): void
    {
        $root = self::root();

        try {
            $manifestPath = $root . '/composer.json';
            $target = $root . '/actual.json';
            $store = new ComposerManifestStore();

            \file_put_contents($manifestPath, '{"require":{"php":"^8.4"}}');
            $store->readManifest($root);

            self::assertTrue(\unlink($manifestPath));
            \file_put_contents($target, '{"require":{"php":"^8.4"}}');

            if (
                !\function_exists('symlink')
                || !@\symlink($target, $manifestPath)
            ) {
                $method = new \ReflectionMethod(ComposerManifestStore::class, 'readRequiredDocument');
                $path = $method->getFileName();

                self::assertIsString($path);

                $lines = \file($path);

                self::assertIsArray($lines);

                $source = \implode(
                    '',
                    \array_slice(
                        $lines,
                        $method->getStartLine() - 1,
                        $method->getEndLine() - $method->getStartLine() + 1,
                    ),
                );

                self::assertStringContainsString('\is_link($path)', $source);

                return;
            }

            try {
                $store->readManifest($root);
                self::fail('Expected symlink manifest rejection.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID,
                    $exception->errorCode(),
                );
            }
        } finally {
            self::remove($root);
        }
    }

    private static function root(): string
    {
        $path = \sys_get_temp_dir()
            . '/coretsia-manifest-store-'
            . \bin2hex(\random_bytes(8));

        \mkdir($path, 0777, true);

        return $path;
    }

    private static function remove(string $path): void
    {
        if (\is_link($path) || \is_file($path)) {
            @\unlink($path);
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }

        @\rmdir($path);
    }
}
