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
    public function testConfigValueSourceExposesCanonicalSafeShape(): void
    {
        $source = new ConfigValueSource(
            type: ConfigSourceType::AppConfig,
            root: 'foundation',
            sourceId: 'app.foundation',
            path: 'apps/main/config/foundation.php',
            keyPath: 'container.bindings',
            directive: '@merge',
            precedence: 20,
            redacted: true,
            meta: [
                'zeta' => [
                    'b' => 2,
                    'a' => 1,
                ],
                'alpha' => 'safe',
            ],
        );

        self::assertSame(1, $source->schemaVersion());
        self::assertSame(ConfigSourceType::AppConfig, $source->type());
        self::assertSame('foundation', $source->root());
        self::assertSame('app.foundation', $source->sourceId());
        self::assertSame('apps/main/config/foundation.php', $source->path());
        self::assertSame('container.bindings', $source->keyPath());
        self::assertSame('merge', $source->directive());
        self::assertSame(20, $source->precedence());
        self::assertTrue($source->isRedacted());
        self::assertSame(
            [
                'alpha' => 'safe',
                'zeta' => [
                    'a' => 1,
                    'b' => 2,
                ],
            ],
            $source->meta(),
        );

        self::assertSame(
            [
                'directive' => 'merge',
                'keyPath' => 'container.bindings',
                'meta' => [
                    'alpha' => 'safe',
                    'zeta' => [
                        'a' => 1,
                        'b' => 2,
                    ],
                ],
                'path' => 'apps/main/config/foundation.php',
                'precedence' => 20,
                'redacted' => true,
                'root' => 'foundation',
                'schemaVersion' => 1,
                'sourceId' => 'app.foundation',
                'type' => 'app_config',
            ],
            $source->toArray(),
        );
    }

    public function testDefaultOptionalFieldsPrecedenceRedactionAndMetaAreCanonical(): void
    {
        $source = new ConfigValueSource(
            type: ConfigSourceType::PackageDefault,
            root: 'foundation',
            sourceId: 'core.foundation',
        );

        self::assertSame(1, $source->schemaVersion());
        self::assertSame(ConfigSourceType::PackageDefault, $source->type());
        self::assertSame('foundation', $source->root());
        self::assertSame('core.foundation', $source->sourceId());
        self::assertNull($source->path());
        self::assertNull($source->keyPath());
        self::assertNull($source->directive());
        self::assertSame(0, $source->precedence());
        self::assertFalse($source->isRedacted());
        self::assertSame([], $source->meta());

        self::assertSame(
            [
                'directive' => null,
                'keyPath' => null,
                'meta' => [],
                'path' => null,
                'precedence' => 0,
                'redacted' => false,
                'root' => 'foundation',
                'schemaVersion' => 1,
                'sourceId' => 'core.foundation',
                'type' => 'package_default',
            ],
            $source->toArray(),
        );
    }

    public function testExportedArrayKeyOrderIsDeterministic(): void
    {
        $source = new ConfigValueSource(
            type: ConfigSourceType::GeneratedArtifact,
            root: 'kernel',
            sourceId: 'compiled.kernel',
            path: 'generated/config',
            keyPath: 'boot.providers',
            precedence: 50,
        );

        self::assertSame(
            [
                'directive',
                'keyPath',
                'meta',
                'path',
                'precedence',
                'redacted',
                'root',
                'schemaVersion',
                'sourceId',
                'type',
            ],
            array_keys($source->toArray()),
        );
    }

    public function testRootMustBeNonEmptyLowercaseConfigRootIdentifier(): void
    {
        foreach (['', ' ', 'Foundation', 'foundation-root', 'foundation.root', '1foundation'] as $root) {
            try {
                new ConfigValueSource(
                    type: ConfigSourceType::PackageDefault,
                    root: $root,
                    sourceId: 'core.foundation',
                );

                self::fail('Expected invalid root to be rejected: ' . var_export($root, true));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('root', $exception->getMessage());
            }
        }
    }

    public function testSourceIdMustBeNonEmpty(): void
    {
        foreach (['', ' '] as $sourceId) {
            try {
                new ConfigValueSource(
                    type: ConfigSourceType::AppConfig,
                    root: 'foundation',
                    sourceId: $sourceId,
                );

                self::fail('Expected empty sourceId to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('sourceId', $exception->getMessage());
            }
        }
    }

    public function testPrecedenceMustBeNonNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config value source precedence must be non-negative.');

        new ConfigValueSource(
            type: ConfigSourceType::PackageDefault,
            root: 'foundation',
            sourceId: 'core.foundation',
            precedence: -1,
        );
    }

    public function testPathKeyPathAndSourceIdMustNotContainControlBytes(): void
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

    public function testKeyPathAndSourceIdMustNotContainWhitespace(): void
    {
        foreach (['keyPath', 'sourceId'] as $field) {
            try {
                $this->sourceWithField($field, 'contains whitespace');

                self::fail('Expected invalid ' . $field . ' to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString($field, $exception->getMessage());
            }
        }
    }

    public function testPathKeyPathAndSourceIdMustNotBeAbsolutePathsOrUrls(): void
    {
        foreach (
            [
                '/etc/app/config.php',
                '\\server\\share\\config.php',
                'C:\\app\\config.php',
                'file://config.php',
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

    public function testPathKeyPathAndSourceIdMustNotContainPathTraversal(): void
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

    public function testDirectiveMustBeAllowedAndIsStoredWithoutPrefix(): void
    {
        $source = new ConfigValueSource(
            type: ConfigSourceType::AppConfig,
            root: 'foundation',
            sourceId: 'app.foundation',
            directive: '@replace',
        );

        self::assertSame('replace', $source->directive());
        self::assertSame('replace', $source->toArray()['directive']);
    }

    public function testUnknownDirectiveIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid config value source directive.');

        new ConfigValueSource(
            type: ConfigSourceType::AppConfig,
            root: 'foundation',
            sourceId: 'app.foundation',
            directive: '@unknown',
        );
    }

    public function testMetaMustBeJsonLikeMapWithoutFloatsOrRuntimeValues(): void
    {
        foreach (
            [
                ['meta' => ['rate' => 1.5]],
                ['meta' => ['rate' => NAN]],
                ['meta' => ['rate' => INF]],
                ['meta' => ['object' => new \stdClass()]],
            ] as $case
        ) {
            try {
                new ConfigValueSource(
                    type: ConfigSourceType::Runtime,
                    root: 'kernel',
                    sourceId: 'runtime.kernel',
                    meta: $case['meta'],
                );

                self::fail('Expected invalid meta to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('metadata', $exception->getMessage());
            }
        }
    }

    public function testMetaRootMustBeAMap(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config value source meta must be a map.');

        new ConfigValueSource(
            type: ConfigSourceType::Runtime,
            root: 'kernel',
            sourceId: 'runtime.kernel',
            meta: ['list-item'],
        );
    }

    public function testMetaMustNotUseRawValueOrSecretKeys(): void
    {
        foreach (
            [
                'value',
                'rawValue',
                'configValue',
                'envValue',
                'rawEnvValue',
                'secret',
                'password',
                'token',
                'credential',
                'credentials',
                'privateKey',
                'authorizationHeader',
                'cookie',
                'requestBody',
                'responseBody',
            ] as $key
        ) {
            try {
                new ConfigValueSource(
                    type: ConfigSourceType::Runtime,
                    root: 'kernel',
                    sourceId: 'runtime.kernel',
                    meta: [$key => 'forbidden'],
                );

                self::fail('Expected forbidden meta key to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('metadata key', $exception->getMessage());
            }
        }
    }

    public function testNestedMetaMustNotUseRawValueOrSecretKeys(): void
    {
        try {
            new ConfigValueSource(
                type: ConfigSourceType::Runtime,
                root: 'kernel',
                sourceId: 'runtime.kernel',
                meta: [
                    'safe' => [
                        'token' => 'forbidden',
                    ],
                ],
            );

            self::fail('Expected nested forbidden meta key to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('metadata key', $exception->getMessage());
        }
    }

    public function testPathKeyPathAndSourceIdMustNotContainColons(): void
    {
        foreach (['C:secret', 'config:secret'] as $value) {
            foreach (['path', 'keyPath', 'sourceId'] as $field) {
                try {
                    $this->sourceWithField($field, $value);

                    self::fail('Expected colon in ' . $field . ' to be rejected.');
                } catch (InvalidArgumentException $exception) {
                    self::assertStringContainsString($field, $exception->getMessage());
                }
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
            type: ConfigSourceType::AppConfig,
            root: 'foundation',
            sourceId: $sourceId,
            path: $path,
            keyPath: $keyPath,
        );
    }
}
