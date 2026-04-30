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

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigValueSourceShapeContractTest extends TestCase
{
    public function test_config_value_source_exposes_canonical_safe_shape(): void
    {
        $source = new ConfigValueSource(
            ConfigSourceType::ApplicationConfig,
            'foundation',
            'app.config/foundation',
            'container.bindings',
            'app.foundation',
            precedence: 20,
            redacted: true,
        );

        self::assertSame(ConfigSourceType::ApplicationConfig, $source->type());
        self::assertSame('foundation', $source->root());
        self::assertSame('app.config/foundation', $source->path());
        self::assertSame('container.bindings', $source->keyPath());
        self::assertSame('app.foundation', $source->sourceId());
        self::assertSame(20, $source->precedence());
        self::assertTrue($source->isRedacted());

        self::assertSame(
            [
                'keyPath' => 'container.bindings',
                'path' => 'app.config/foundation',
                'precedence' => 20,
                'redacted' => true,
                'root' => 'foundation',
                'sourceId' => 'app.foundation',
                'type' => 'application_config',
            ],
            $source->toArray(),
        );
    }

    public function test_default_source_id_precedence_and_redaction_are_canonical(): void
    {
        $source = new ConfigValueSource(
            ConfigSourceType::PackageDefaults,
            'foundation',
            '',
            '',
        );

        self::assertNull($source->sourceId());
        self::assertSame(0, $source->precedence());
        self::assertFalse($source->isRedacted());

        self::assertSame(
            [
                'keyPath' => '',
                'path' => '',
                'precedence' => 0,
                'redacted' => false,
                'root' => 'foundation',
                'sourceId' => null,
                'type' => 'package_defaults',
            ],
            $source->toArray(),
        );
    }

    public function test_exported_array_key_order_is_deterministic(): void
    {
        $source = new ConfigValueSource(
            ConfigSourceType::GeneratedArtifact,
            'kernel',
            'generated/config',
            'boot.providers',
            'compiled.kernel',
            precedence: 50,
        );

        self::assertSame(
            [
                'keyPath',
                'path',
                'precedence',
                'redacted',
                'root',
                'sourceId',
                'type',
            ],
            array_keys($source->toArray()),
        );
    }

    public function test_root_must_be_non_empty_lowercase_config_root_identifier(): void
    {
        foreach (['', ' ', 'Foundation', 'foundation-root', 'foundation.root', '1foundation'] as $root) {
            try {
                new ConfigValueSource(
                    ConfigSourceType::PackageDefaults,
                    $root,
                    '',
                    '',
                );

                self::fail('Expected invalid root to be rejected: ' . var_export($root, true));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('root', $exception->getMessage());
            }
        }
    }

    public function test_precedence_must_be_non_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config value source precedence must be non-negative.');

        new ConfigValueSource(
            ConfigSourceType::PackageDefaults,
            'foundation',
            '',
            '',
            precedence: -1,
        );
    }

    public function test_path_key_path_and_source_id_must_not_contain_control_bytes(): void
    {
        foreach (["line\nbreak", "line\rbreak", "null\0byte"] as $value) {
            foreach (['path', 'keyPath', 'sourceId'] as $field) {
                try {
                    $this->sourceWithField($field, $value);

                    self::fail('Expected invalid ' . $field . ' to be rejected.');
                } catch (InvalidArgumentException $exception) {
                    self::assertStringContainsString($field, $exception->getMessage());
                }
            }
        }
    }

    public function test_path_key_path_and_source_id_must_not_be_absolute_paths_or_urls(): void
    {
        foreach (
            [
                '/etc/app/config.php',
                '\\server\\share\\config.php',
                'C:\\app\\config.php',
                'file://config.php'
            ] as $value
        ) {
            foreach (['path', 'keyPath', 'sourceId'] as $field) {
                try {
                    $this->sourceWithField($field, $value);

                    self::fail('Expected unsafe ' . $field . ' to be rejected.');
                } catch (InvalidArgumentException $exception) {
                    self::assertStringContainsString($field, $exception->getMessage());
                }
            }
        }
    }

    public function test_path_key_path_and_source_id_must_not_contain_path_traversal(): void
    {
        foreach (['..', '../config', 'config/../secret', 'config/..', '..\\config', 'config\\..\\secret'] as $value) {
            foreach (['path', 'keyPath', 'sourceId'] as $field) {
                try {
                    $this->sourceWithField($field, $value);

                    self::fail('Expected traversal in ' . $field . ' to be rejected.');
                } catch (InvalidArgumentException $exception) {
                    self::assertStringContainsString($field, $exception->getMessage());
                }
            }
        }
    }

    public function test_source_id_must_not_be_empty_when_provided(): void
    {
        foreach (['', ' '] as $sourceId) {
            try {
                new ConfigValueSource(
                    ConfigSourceType::ApplicationConfig,
                    'foundation',
                    'app.config',
                    'container',
                    $sourceId,
                );

                self::fail('Expected empty sourceId to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('sourceId', $exception->getMessage());
            }
        }
    }

    private function sourceWithField(string $field, string $value): ConfigValueSource
    {
        $path = 'logical/path';
        $keyPath = 'logical.key';
        $sourceId = 'logical.source';

        if ($field === 'path') {
            $path = $value;
        }

        if ($field === 'keyPath') {
            $keyPath = $value;
        }

        if ($field === 'sourceId') {
            $sourceId = $value;
        }

        return new ConfigValueSource(
            ConfigSourceType::ApplicationConfig,
            'foundation',
            $path,
            $keyPath,
            $sourceId,
        );
    }
}
