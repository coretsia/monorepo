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

use Coretsia\Contracts\Module\ModuleDescriptor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleDescriptorSchemaVersionTest extends TestCase
{
    public function test_exposes_initial_schema_version(): void
    {
        $descriptor = ModuleDescriptor::fromLayerAndSlug('core', 'kernel');

        self::assertSame(1, ModuleDescriptor::SCHEMA_VERSION);
        self::assertSame(1, $descriptor->schemaVersion());
        self::assertSame(1, $descriptor->toArray()['schemaVersion']);
    }

    public function test_exports_stable_descriptor_shape(): void
    {
        $descriptor = ModuleDescriptor::fromLayerAndSlug(
            layer: 'platform',
            slug: 'cli',
            composerName: 'coretsia/platform-cli',
            packageKind: 'runtime',
            moduleClass: 'Coretsia\\Platform\\Cli\\CliModule',
            capabilities: ['runtime.cli', 'debug.modules'],
            metadata: [
                'zeta' => [
                    'enabled' => true,
                    'level' => 1,
                ],
                'alpha' => null,
            ],
        );

        $exported = $descriptor->toArray();

        self::assertSame([
            'capabilities',
            'composerName',
            'layer',
            'metadata',
            'moduleClass',
            'moduleId',
            'packageKind',
            'schemaVersion',
            'slug',
        ], array_keys($exported));

        self::assertSame(1, $exported['schemaVersion']);
        self::assertSame('platform.cli', $exported['moduleId']);
        self::assertSame('platform', $exported['layer']);
        self::assertSame('cli', $exported['slug']);
        self::assertSame('coretsia/platform-cli', $exported['composerName']);
        self::assertSame('runtime', $exported['packageKind']);
        self::assertSame('Coretsia\\Platform\\Cli\\CliModule', $exported['moduleClass']);

        self::assertExportedJsonLikeValue($exported);
    }

    #[DataProvider('invalidMetadataValues')]
    public function test_rejects_non_json_like_metadata_values(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ModuleDescriptor::fromLayerAndSlug(
            layer: 'core',
            slug: 'kernel',
            metadata: [
                'invalid' => $value,
            ],
        );
    }

    public function test_descriptor_optional_strings_reject_unsafe_control_characters(): void
    {
        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                composerName: "coretsia/http\0hidden",
            ),
        );

        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                packageKind: "runtime\x01kind",
            ),
        );

        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                moduleClass: "Coretsia\\Platform\\Http\\HttpModule\x7F",
            ),
        );
    }

    public function test_descriptor_capabilities_reject_unsafe_control_characters(): void
    {
        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                capabilities: ["http.server\0hidden"],
            ),
        );

        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                capabilities: ["http.server\x01hidden"],
            ),
        );

        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                capabilities: ["http.server\x7F"],
            ),
        );
    }

    public function test_descriptor_metadata_keys_reject_unsafe_control_characters(): void
    {
        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                metadata: [
                    "owner\0hidden" => 'platform',
                ],
            ),
        );

        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                metadata: [
                    "owner\x01hidden" => 'platform',
                ],
            ),
        );
    }

    public function test_descriptor_metadata_string_values_reject_unsafe_control_characters(): void
    {
        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                metadata: [
                    'owner' => "platform\0hidden",
                ],
            ),
        );

        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                metadata: [
                    'owner' => "platform\x01hidden",
                ],
            ),
        );

        self::assertInvalidArgument(
            static fn (): ModuleDescriptor => ModuleDescriptor::fromLayerAndSlug(
                layer: 'platform',
                slug: 'http',
                metadata: [
                    'owner' => "platform\x7F",
                ],
            ),
        );
    }

    public function test_rejects_resource_metadata_value(): void
    {
        $resource = fopen('php://memory', 'rb');

        if ($resource === false) {
            self::fail('Unable to create in-memory resource for test.');
        }

        try {
            $this->expectException(\InvalidArgumentException::class);

            ModuleDescriptor::fromLayerAndSlug(
                layer: 'core',
                slug: 'kernel',
                metadata: [
                    'invalid' => $resource,
                ],
            );
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    /**
     * @return iterable<string,array{0:mixed}>
     */
    public static function invalidMetadataValues(): iterable
    {
        yield 'float' => [1.25];
        yield 'object' => [new \stdClass()];
        yield 'closure' => [static fn (): null => null];
        yield 'nested-float' => [['value' => 1.25]];
        yield 'list-containing-float' => [[1, 1.25]];
        yield 'integer-keyed-map' => [[1 => 'value']];
    }

    private static function assertInvalidArgument(callable $callback): void
    {
        try {
            $callback();
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);

            return;
        }

        self::fail('Expected InvalidArgumentException to be thrown.');
    }

    private static function assertExportedJsonLikeValue(mixed $value): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            self::assertTrue(true);

            return;
        }

        self::assertIsArray($value);

        if (array_is_list($value)) {
            foreach ($value as $item) {
                self::assertExportedJsonLikeValue($item);
            }

            return;
        }

        $keys = array_keys($value);

        foreach ($keys as $key) {
            self::assertIsString($key);
        }

        $sortedKeys = $keys;
        sort($sortedKeys, \SORT_STRING);

        self::assertSame($sortedKeys, $keys);

        foreach ($value as $item) {
            self::assertExportedJsonLikeValue($item);
        }
    }
}
